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
  it('creates the exact approved platform schema from an empty database', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);

      const fingerprint = await readSchemaFingerprint(pool);
      expect(() => assertSchemaMatchesExpected(fingerprint)).not.toThrow();
      expect(await getMigrationStatus(database)).toEqual([
        expect.objectContaining({
          name: '20260729000000_database_baseline',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729010000_idempotency_keys',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729020000_identity_access',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729020100_passkey_revision',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729030000_users_settings',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729040000_ledger_journal',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729050000_currency_fx',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729050100_fx_snapshot_invariant',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729060000_budgeting_categories_income',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729070000_recurrence_scheduling',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729080000_reporting_indexes',
          executedAt: expect.any(Date),
        }),
      ]);

      expect(fingerprint.relations.map(({ schema, name }) => `${schema}.${name}`)).toEqual([
        'mymoneymap.basic_incomes',
        'mymoneymap.budget_rules',
        'mymoneymap.categories',
        'mymoneymap.currencies',
        'mymoneymap.email_verification_tokens',
        'mymoneymap.fx_conversion_snapshots',
        'mymoneymap.fx_quotes',
        'mymoneymap.idempotency_keys',
        'mymoneymap.journal_entries',
        'mymoneymap.journal_legs',
        'mymoneymap.ledger_accounts',
        'mymoneymap.login_audit_events',
        'mymoneymap.passkeys',
        'mymoneymap.recurrence_job_events',
        'mymoneymap.recurrence_job_executions',
        'mymoneymap.recurring_occurrences',
        'mymoneymap.recurring_rules',
        'mymoneymap.user_currencies',
        'mymoneymap.users',
        'mymoneymap_meta.kysely_migration',
        'mymoneymap_meta.kysely_migration_lock',
      ]);
      expect(
        (
          await pool.query<{ count: string }>(
            'SELECT count(*)::text AS count FROM mymoneymap.users',
          )
        ).rows[0]?.count,
      ).toBe('0');
    });
  });

  it('rolls back the latest migration and deterministically reapplies it', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const firstFingerprint = await readSchemaFingerprint(pool);

      const rollback = await migrateOneDown(database);
      expect(rollback.results).toEqual([
        {
          migrationName: '20260729080000_reporting_indexes',
          direction: 'Down',
          status: 'Success',
        },
      ]);
      const rolledBack = await readSchemaFingerprint(pool);
      expect(rolledBack.schemas).toEqual(['mymoneymap', 'mymoneymap_meta']);
      expect(rolledBack.relations.map(({ name }) => name)).toContain('users');
      expect(
        rolledBack.columns.some(({ relation, name }) => relation === 'users' && name === 'theme'),
      ).toBe(true);
      expect(rolledBack.relations.map(({ name }) => name)).toContain('journal_entries');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('fx_quotes');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('budget_rules');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('categories');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('basic_incomes');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('recurring_rules');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('recurrence_job_executions');
      expect(rolledBack.indexes.map(({ name }) => name)).not.toContain(
        'journal_entries_user_period_reporting',
      );
      expect(
        (
          await pool.query<{ present: boolean }>(
            `SELECT EXISTS (
               SELECT 1
                 FROM pg_trigger
                WHERE tgname = 'journal_entries_fx_snapshot_required'
                  AND NOT tgisinternal
             ) AS present`,
          )
        ).rows[0]?.present,
      ).toBe(true);

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
      expect(ledger.rows[0]?.count).toBe('11');
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
    expect(targetStatusNames).toEqual([
      '20260729000000_database_baseline',
      '20260729010000_idempotency_keys',
      '20260729020000_identity_access',
      '20260729020100_passkey_revision',
      '20260729030000_users_settings',
      '20260729040000_ledger_journal',
      '20260729050000_currency_fx',
      '20260729050100_fx_snapshot_invariant',
      '20260729060000_budgeting_categories_income',
      '20260729070000_recurrence_scheduling',
      '20260729080000_reporting_indexes',
    ]);
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
