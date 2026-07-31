import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';
import type { Pool } from 'pg';

describe('SMTP email provider PostgreSQL contract', () => {
  it('accepts SMTP, rolls the provider constraint back safely, and reapplies it', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await pool.query(
        `INSERT INTO mymoneymap.email_channel_settings
           (id,enabled,provider,from_address,created_at,updated_at)
         VALUES (1,false,'smtp','sender@example.test',now(),now())`,
      );
      await expect(providerConstraint(pool)).resolves.toContain("'smtp'");

      const rollback = await migrateOneDown(database);
      expect(rollback.results).toEqual([
        {
          migrationName: '20260731130000_smtp_email_provider',
          direction: 'Down',
          status: 'Success',
        },
      ]);
      await expect(providerConstraint(pool)).resolves.not.toContain("'smtp'");
      await expect(
        pool.query(`UPDATE mymoneymap.email_channel_settings SET provider='smtp' WHERE id=1`),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query<{ enabled: boolean; provider: string }>(
          `SELECT enabled,provider FROM mymoneymap.email_channel_settings WHERE id=1`,
        ),
      ).resolves.toMatchObject({ rows: [{ enabled: false, provider: 'disabled' }] });

      await migrateToLatest(database);
      await expect(providerConstraint(pool)).resolves.toContain("'smtp'");
      await expect(
        pool.query(`UPDATE mymoneymap.email_channel_settings SET provider='smtp' WHERE id=1`),
      ).resolves.toBeDefined();
    });
  });
});

async function providerConstraint(pool: Pool): Promise<string> {
  const result = await pool.query<{ definition: string }>(
    `SELECT pg_get_constraintdef(oid) AS definition
       FROM pg_constraint
      WHERE conname='email_channel_provider_check'`,
  );
  return result.rows[0]?.definition ?? '';
}
