import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { UsersRepository } from '../src/users/users.repository';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('users/settings PostgreSQL invariants', () => {
  it('migrates up and rolls Step 05 back without disturbing Step 04', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM information_schema.columns
              WHERE table_schema = 'mymoneymap'
                AND table_name = 'users'
                AND column_name IN (
                  'theme','desired_language','onboard_step','needs_tutorial','tutorial_seen'
                )`,
          )
        ).rows[0]?.count,
      ).toBe('5');

      await migrateOneDown(database);
      await migrateOneDown(database);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM information_schema.columns
              WHERE table_schema = 'mymoneymap'
                AND table_name = 'users'
                AND column_name = 'email_verified_at'`,
          )
        ).rows[0]?.count,
      ).toBe('1');
    });
  });

  it('enforces supported theme, locale, onboarding, tutorial, role, and calendar-date values', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const id = await insertUser(pool);
      for (const [column, value] of [
        ['theme', 'unknown-theme'],
        ['desired_language', 'el'],
        ['onboard_step', '7'],
        ['role', 'owner'],
      ]) {
        await expect(
          pool.query(`UPDATE mymoneymap.users SET ${column} = $2 WHERE id = $1`, [id, value]),
        ).rejects.toMatchObject({ code: '23514' });
      }
      await expect(
        pool.query(
          `UPDATE mymoneymap.users
              SET tutorial_seen = true, needs_tutorial = true
            WHERE id = $1`,
          [id],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query(`UPDATE mymoneymap.users SET date_of_birth = '2026-02-30' WHERE id = $1`, [id]),
      ).rejects.toMatchObject({ code: '22008' });
    });
  });

  it('advances theme onboarding without regression and completes tutorial idempotently under concurrency', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const id = await insertUser(pool);
      const repository = new UsersRepository(pool);
      const first = new Date('2026-07-29T10:00:00.000Z');
      const second = new Date('2026-07-29T11:00:00.000Z');

      expect((await repository.updateTheme(id, 'celestial-tide', first))?.onboardStep).toBe(2);
      await pool.query('UPDATE mymoneymap.users SET onboard_step = 6 WHERE id = $1', [id]);
      expect((await repository.updateTheme(id, 'dune-mirage', second))?.onboardStep).toBe(6);

      const completions = await Promise.all([
        repository.completeTutorial(id, first),
        repository.completeTutorial(id, second),
      ]);
      expect(completions.every((user) => user?.tutorialSeen && !user.needsTutorial)).toBe(true);
      const persisted = await repository.findById(id);
      expect(persisted).toMatchObject({ tutorialSeen: true, needsTutorial: false });
    });
  });
});

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
       (id,email,password_hash,full_name,date_of_birth,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic User','1990-01-01',now(),now(),now())`,
    [id, `${id}@example.test`],
  );
  return id;
}
