import { promises as fs } from 'node:fs';
import path from 'node:path';
import { sql } from 'kysely';
import { createDatabase } from '../src/platform/database/create-database';
import { DatabaseTransactionService } from '../src/platform/database/database-transaction.service';
import {
  assertSchemaMatchesExpected,
  expectedSchemaFingerprint,
} from '../src/platform/database/expected-schema';
import {
  getMigrationStatus,
  migrateOneDown,
  migrateToLatest,
} from '../src/platform/database/migration-runner';
import { registeredMigrations } from '../src/platform/database/migrations/migration-provider';
import { readSchemaFingerprint } from '../src/platform/database/schema-fingerprint';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('PostgreSQL baseline and migration system', () => {
  it('creates the exact domain-free baseline from an empty database', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);

      const fingerprint = await readSchemaFingerprint(pool);
      expect(() => assertSchemaMatchesExpected(fingerprint)).not.toThrow();
      expect(await getMigrationStatus(database)).toEqual([
        expect.objectContaining({
          name: '20260729000000_database_baseline',
          executedAt: expect.any(Date),
        }),
      ]);

      expect(fingerprint.relations.map(({ schema, name }) => `${schema}.${name}`)).toEqual([
        'mymoneymap_meta.kysely_migration',
        'mymoneymap_meta.kysely_migration_lock',
      ]);
      expect(JSON.stringify(fingerprint).toLowerCase()).not.toContain('admin');
      expect(JSON.stringify(fingerprint).toLowerCase()).not.toContain('users');
    });
  });

  it('rolls back one migration and deterministically restores the same baseline', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const firstFingerprint = await readSchemaFingerprint(pool);

      const rollback = await migrateOneDown(database);
      expect(rollback.results).toEqual([
        {
          migrationName: '20260729000000_database_baseline',
          direction: 'Down',
          status: 'Success',
        },
      ]);
      expect((await readSchemaFingerprint(pool)).schemas).toEqual(['mymoneymap_meta']);

      await migrateToLatest(database);
      expect(await readSchemaFingerprint(pool)).toEqual(firstFingerprint);
    });
  });

  it('serializes concurrent migration runners without duplicate execution', async () => {
    await withIsolatedPostgresDatabase(async ({ database, policy, pool }) => {
      const secondConnection = createDatabase(policy);
      try {
        await Promise.all([migrateToLatest(database), migrateToLatest(secondConnection.database)]);
      } finally {
        await secondConnection.database.destroy();
      }

      const ledger = await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap_meta.kysely_migration',
      );
      expect(ledger.rows[0]?.count).toBe('1');
      const concurrentFingerprint = await readSchemaFingerprint(pool);
      expect(() => assertSchemaMatchesExpected(concurrentFingerprint)).not.toThrow();
    });
  });

  it('detects unapproved schema drift', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await pool.query('CREATE TABLE mymoneymap.unexpected_drift (id integer PRIMARY KEY)');
      const driftedFingerprint = await readSchemaFingerprint(pool);

      expect(() => assertSchemaMatchesExpected(driftedFingerprint)).toThrow(
        'PostgreSQL schema drift detected',
      );
    });
  });

  it('rolls back all writes when the transaction helper callback fails', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await pool.query('CREATE TABLE mymoneymap.transaction_probe (value text NOT NULL)');
      const transactions = new DatabaseTransactionService(database);

      await expect(
        transactions.execute(async (transaction) => {
          await sql`INSERT INTO mymoneymap.transaction_probe (value) VALUES ('synthetic')`.execute(
            transaction,
          );
          throw new Error('synthetic rollback');
        }),
      ).rejects.toThrow('synthetic rollback');

      const result = await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap.transaction_probe',
      );
      expect(result.rows[0]?.count).toBe('0');
    });
  });

  it('keeps every legacy migration, including the unsafe admin seed, outside the target runner', async () => {
    const legacyMigrationDirectory = path.resolve(process.cwd(), 'migrations');
    const targetStatusNames = Object.keys(registeredMigrations);
    const legacyFiles = (await fs.readdir(legacyMigrationDirectory)).filter((name) =>
      name.endsWith('.sql'),
    );

    expect(legacyFiles).toHaveLength(42);
    expect(legacyFiles).toContain('028_default_admin.sql');
    expect(targetStatusNames).toEqual(['20260729000000_database_baseline']);
    expect(targetStatusNames).not.toContain('028_default_admin');
  });

  it('fails when PostgreSQL is unavailable instead of skipping successfully', async () => {
    const unavailable = createDatabase({
      connectionString: 'postgresql://synthetic:synthetic@127.0.0.1:1/unavailable',
      tlsMode: 'disable',
      poolMax: 1,
      connectionTimeoutMs: 100,
      idleTimeoutMs: 100,
      maxLifetimeSeconds: 30,
    });

    try {
      await expect(migrateToLatest(unavailable.database)).rejects.toThrow(
        'Database migration failed before execution',
      );
    } finally {
      await unavailable.database.destroy();
    }
  });

  it('keeps the committed expected fingerprint meaningful', () => {
    expect(expectedSchemaFingerprint.schemas).toEqual(['mymoneymap', 'mymoneymap_meta']);
  });
});
