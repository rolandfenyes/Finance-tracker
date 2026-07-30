import { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { LEGACY_RELATION_MAPPINGS } from '../src/legacy-migration/legacy-schema.manifest';
import { LegacyMigrationRepository } from '../src/legacy-migration/legacy-migration.repository';
import { LegacySourceExtractor } from '../src/legacy-migration/legacy-source-extractor';
import { LegacyTransformer } from '../src/legacy-migration/legacy-transformer';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('legacy migration rehearsal (PostgreSQL)', () => {
  it('extracts through a read-only transaction and persists an idempotent anonymized rehearsal', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool, policy }) => {
      await migrateToLatest(database);
      await createSyntheticLegacySchema(pool);
      const sourcePool = new Pool({ connectionString: policy.connectionString, max: 1 });

      try {
        const snapshot = await new LegacySourceExtractor(sourcePool).extract();
        expect(snapshot.schema.version).toBe('recorded-035');
        expect(snapshot.schema.blockingCodes).toEqual([]);
        expect(snapshot.rows.users).toHaveLength(1);

        const plan = new LegacyTransformer().transform(snapshot);
        expect(plan.blockingCodes).toEqual([]);
        const repository = new LegacyMigrationRepository(pool);
        const first = await repository.persistRehearsal(
          plan,
          'rehearsal',
          new Date('2026-01-20T00:00:00.000Z'),
        );
        const repeated = await repository.persistRehearsal(
          plan,
          'rehearsal',
          new Date('2026-01-21T00:00:00.000Z'),
        );

        expect(first.status).toBe('completed');
        expect(first.reused).toBe(false);
        expect(repeated).toEqual({ ...first, reused: true });

        const batches = await pool.query(
          'SELECT count(*)::int AS count FROM mymoneymap.legacy_migration_batches',
        );
        expect(batches.rows[0].count).toBe(1);
        const duplicateRows = await pool.query(
          `SELECT count(*)::int AS total,
                  count(DISTINCT id)::int AS distinct_ids
             FROM mymoneymap.legacy_migration_row_ledger`,
        );
        expect(duplicateRows.rows[0].total).toBe(duplicateRows.rows[0].distinct_ids);

        const reports = await pool.query<{ report: string }>(
          `SELECT concat_ws(' ',source_table,source_key_hash,user_key_hash,domain,reason_code,
                            array_to_string(detail_codes,' ')) AS report
             FROM mymoneymap.legacy_migration_quarantine`,
        );
        expect(JSON.stringify(reports.rows)).not.toContain('user@example.test');
        expect(JSON.stringify(reports.rows)).not.toContain('synthetic-password-hash');
      } finally {
        await sourcePool.end();
      }
    });
  });

  it('rolls the Step 20 control schema down and reapplies it', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await migrateOneDown(database);
      const rolledBack = await pool.query<{ name: string | null }>(
        `SELECT to_regclass('mymoneymap.legacy_migration_batches')::text AS name`,
      );
      expect(rolledBack.rows[0]!.name).toBeNull();

      await migrateToLatest(database);
      const reapplied = await pool.query<{ name: string | null }>(
        `SELECT to_regclass('mymoneymap.legacy_migration_batches')::text AS name`,
      );
      expect(reapplied.rows[0]!.name).toBe('mymoneymap.legacy_migration_batches');
    });
  });

  it('fails when the legacy PostgreSQL dependency is unavailable', async () => {
    const unavailable = new Pool({
      connectionString: 'postgresql://invalid:invalid@127.0.0.1:1/invalid',
      connectionTimeoutMillis: 100,
      max: 1,
    });
    try {
      await expect(new LegacySourceExtractor(unavailable).extract()).rejects.toThrow();
    } finally {
      await unavailable.end();
    }
  });
});

async function createSyntheticLegacySchema(pool: Pool): Promise<void> {
  const driftOnly = new Set(['goals.category_id', 'investments.stock_id', 'investments.units']);
  for (const mapping of LEGACY_RELATION_MAPPINGS) {
    const columns = [...mapping.requiredColumns, ...(mapping.optionalColumns ?? [])].filter(
      (column) => !driftOnly.has(`${mapping.sourceTable}.${column}`),
    );
    await pool.query(
      `CREATE TABLE public.${quote(mapping.sourceTable)} (${columns
        .map((column) => `${quote(column)} text`)
        .join(',')})`,
    );
  }
  await pool.query(
    `INSERT INTO public.schema_migrations(filename,run_at)
     VALUES('035_system_configuration.sql','2026-01-01T00:00:00Z')`,
  );
  await pool.query(
    `INSERT INTO public.users
      (id,email,password_hash,full_name,date_of_birth,role,status,email_verified_at,created_at,
       theme,desired_language,onboard_step,needs_tutorial)
     VALUES
      ('1','user@example.test','synthetic-password-hash','Synthetic User','1990-01-01',
       'free','active','2026-01-01T00:00:00Z','2026-01-01T00:00:00Z',
       'verdant-horizon','en','6','false')`,
  );
  await pool.query(`INSERT INTO public.currencies(code,name) VALUES('HUF','Hungarian Forint')`);
  await pool.query(
    `INSERT INTO public.user_currencies(user_id,code,is_main) VALUES('1','HUF','true')`,
  );
  await pool.query(
    `INSERT INTO public.transactions
      (id,user_id,kind,category_id,amount,currency,occurred_on,note,created_at,updated_at)
     VALUES('1','1','income',NULL,'100.00','HUF','2026-01-15',NULL,
            '2026-01-15T12:00:00Z',NULL)`,
  );
}

function quote(identifier: string): string {
  if (!/^[a-z][a-z0-9_]*$/.test(identifier)) {
    throw new Error('unsafe synthetic identifier');
  }
  return `"${identifier}"`;
}
