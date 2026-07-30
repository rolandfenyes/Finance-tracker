import type { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { rollbackMigrationsAfter, withIsolatedPostgresDatabase } from './postgres-test-database';

describe('loans PostgreSQL migration contract', () => {
  it('migrates Step 13 up and rolls it back without disturbing Step 12', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'loans')).toBe('mymoneymap.loans');
      expect(await relation(pool, 'loan_payments')).toBe('mymoneymap.loan_payments');
      expect(await column(pool, 'recurring_rules', 'loan_id')).toBe(true);

      await rollbackMigrationsAfter(database, '20260729120000_generic_investments');
      await migrateOneDown(database);
      expect(await relation(pool, 'loans')).toBe('mymoneymap.loans');
      expect(await column(pool, 'recurring_rules', 'investment_id')).toBe(false);

      await migrateOneDown(database);
      expect(await relation(pool, 'loans')).toBeNull();
      expect(await relation(pool, 'loan_payments')).toBeNull();
      expect(await column(pool, 'recurring_rules', 'loan_id')).toBe(false);
      expect(await relation(pool, 'emergency_reserves')).toBe('mymoneymap.emergency_reserves');

      await migrateToLatest(database);
      expect(await relation(pool, 'loans')).toBe('mymoneymap.loans');
    });
  });
});

async function relation(pool: Pool, name: string): Promise<string | null> {
  return (
    await pool.query<{ relation: string | null }>('SELECT to_regclass($1)::text relation', [
      `mymoneymap.${name}`,
    ])
  ).rows[0]!.relation;
}

async function column(pool: Pool, table: string, name: string): Promise<boolean> {
  return (
    await pool.query<{ present: boolean }>(
      `SELECT EXISTS (
         SELECT 1 FROM information_schema.columns
          WHERE table_schema='mymoneymap' AND table_name=$1 AND column_name=$2
       ) present`,
      [table, name],
    )
  ).rows[0]!.present;
}
