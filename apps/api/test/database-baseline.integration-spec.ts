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
        expect.objectContaining({
          name: '20260729090000_goals',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729100000_emergency_reserve',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729110000_loans',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729120000_generic_investments',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729130000_securities',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729130100_securities_account_guard_revision',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729130200_securities_ledger_guard_revision',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729140000_admin_feedback_system',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729150000_billing',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729160000_notifications_email',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729170000_privacy_audit',
          executedAt: expect.any(Date),
        }),
        expect.objectContaining({
          name: '20260729180000_legacy_migration',
          executedAt: expect.any(Date),
        }),
      ]);

      expect(fingerprint.relations.map(({ schema, name }) => `${schema}.${name}`)).toEqual([
        'mymoneymap.account_recovery_requests',
        'mymoneymap.api_integrations',
        'mymoneymap.basic_incomes',
        'mymoneymap.billing_plans',
        'mymoneymap.billing_promotions',
        'mymoneymap.budget_rules',
        'mymoneymap.categories',
        'mymoneymap.currencies',
        'mymoneymap.email_channel_settings',
        'mymoneymap.email_deliveries',
        'mymoneymap.email_suppressions',
        'mymoneymap.email_templates',
        'mymoneymap.email_verification_tokens',
        'mymoneymap.emergency_reserve_movements',
        'mymoneymap.emergency_reserves',
        'mymoneymap.feedback',
        'mymoneymap.feedback_responses',
        'mymoneymap.fx_conversion_snapshots',
        'mymoneymap.fx_quotes',
        'mymoneymap.goal_contributions',
        'mymoneymap.goals',
        'mymoneymap.idempotency_keys',
        'mymoneymap.investment_movements',
        'mymoneymap.investments',
        'mymoneymap.journal_entries',
        'mymoneymap.journal_legs',
        'mymoneymap.ledger_accounts',
        'mymoneymap.legacy_migration_batches',
        'mymoneymap.legacy_migration_quarantine',
        'mymoneymap.legacy_migration_reconciliation',
        'mymoneymap.legacy_migration_row_ledger',
        'mymoneymap.loan_payments',
        'mymoneymap.loans',
        'mymoneymap.login_audit_events',
        'mymoneymap.passkeys',
        'mymoneymap.privacy_deletion_requests',
        'mymoneymap.privacy_export_artifacts',
        'mymoneymap.privacy_export_requests',
        'mymoneymap.privileged_audit_events',
        'mymoneymap.recurrence_job_events',
        'mymoneymap.recurrence_job_executions',
        'mymoneymap.recurring_occurrences',
        'mymoneymap.recurring_rules',
        'mymoneymap.securities_cash_movements',
        'mymoneymap.securities_clear_requests',
        'mymoneymap.securities_daily_prices',
        'mymoneymap.securities_imports',
        'mymoneymap.securities_instruments',
        'mymoneymap.securities_lot_consumptions',
        'mymoneymap.securities_lots',
        'mymoneymap.securities_portfolios',
        'mymoneymap.securities_positions',
        'mymoneymap.securities_quotes',
        'mymoneymap.securities_realized_results',
        'mymoneymap.securities_refresh_jobs',
        'mymoneymap.securities_trades',
        'mymoneymap.securities_watchlist',
        'mymoneymap.security_audit_events',
        'mymoneymap.system_settings',
        'mymoneymap.user_currencies',
        'mymoneymap.user_email_preferences',
        'mymoneymap.user_invoices',
        'mymoneymap.user_payments',
        'mymoneymap.user_subscriptions',
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
          migrationName: '20260729180000_legacy_migration',
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
      expect(rolledBack.relations.map(({ name }) => name)).toContain('goals');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('goal_contributions');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('emergency_reserves');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('emergency_reserve_movements');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('loans');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('loan_payments');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('investments');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('investment_movements');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('securities_trades');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('feedback');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('privileged_audit_events');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('billing_plans');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('email_deliveries');
      expect(rolledBack.relations.map(({ name }) => name)).toContain('privacy_export_requests');
      expect(rolledBack.relations.map(({ name }) => name)).not.toContain(
        'legacy_migration_batches',
      );
      expect(rolledBack.columns).toContainEqual(
        expect.objectContaining({ relation: 'recurring_rules', name: 'goal_id' }),
      );
      expect(rolledBack.indexes.map(({ name }) => name)).toContain(
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
      expect(ledger.rows[0]?.count).toBe('23');
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
      '20260729090000_goals',
      '20260729100000_emergency_reserve',
      '20260729110000_loans',
      '20260729120000_generic_investments',
      '20260729130000_securities',
      '20260729130100_securities_account_guard_revision',
      '20260729130200_securities_ledger_guard_revision',
      '20260729140000_admin_feedback_system',
      '20260729150000_billing',
      '20260729160000_notifications_email',
      '20260729170000_privacy_audit',
      '20260729180000_legacy_migration',
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
