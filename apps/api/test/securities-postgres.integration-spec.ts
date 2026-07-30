import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';
import type { Pool } from 'pg';

jest.setTimeout(30_000);

describe('securities PostgreSQL migration contract', () => {
  it('migrates Step 15 up and rolls its schema back without disturbing Step 14', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'securities_trades')).toBe('mymoneymap.securities_trades');
      expect(await relation(pool, 'securities_lots')).toBe('mymoneymap.securities_lots');
      expect(await relation(pool, 'securities_refresh_jobs')).toBe(
        'mymoneymap.securities_refresh_jobs',
      );

      await migrateOneDown(database);
      await migrateOneDown(database);
      await migrateOneDown(database);
      await migrateOneDown(database);
      expect(await relation(pool, 'securities_trades')).toBeNull();
      expect(await relation(pool, 'securities_lots')).toBeNull();
      expect(await relation(pool, 'securities_refresh_jobs')).toBeNull();
      expect(await relation(pool, 'investments')).toBe('mymoneymap.investments');

      await migrateToLatest(database);
      expect(await relation(pool, 'securities_trades')).toBe('mymoneymap.securities_trades');
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
