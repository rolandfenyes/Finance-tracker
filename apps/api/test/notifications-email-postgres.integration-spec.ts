import { randomUUID } from 'node:crypto';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);

describe('notifications/email PostgreSQL contract', () => {
  it('enforces locale, contracts, event idempotency, ownership cleanup, and Step 18 rollback', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.users
         (id,email,password_hash,full_name,date_of_birth,role,status,created_at,updated_at,
          theme,desired_language,onboard_step,needs_tutorial,tutorial_seen)
         VALUES($1,$2,'hash','Synthetic User','1990-01-01','free','active',now(),now(),
                'verdant-horizon','hu',1,false,true)`,
        [userId, `synthetic-${userId}@example.test`],
      );
      await pool.query(
        `INSERT INTO mymoneymap.user_email_preferences(user_id,educational_enabled,updated_at)
         VALUES($1,false,now())`,
        [userId],
      );
      const delivery = [
        randomUUID(),
        'synthetic-event-key',
        randomUUID(),
        userId,
        `synthetic-${userId}@example.test`,
      ];
      await pool.query(
        `INSERT INTO mymoneymap.email_deliveries
         (id,event_key,correlation_id,user_id,recipient_email,template_code,template_version,
          locale,classification,template_data,status,attempt_count,max_attempts,created_at)
         VALUES($1,$2,$3,$4,$5,'welcome',1,'hu','transactional',
                '{"user_first_name":"Synthetic"}','queued',0,3,now())`,
        delivery,
      );
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.email_deliveries
           (id,event_key,correlation_id,recipient_email,template_code,template_version,locale,
            classification,template_data,status,attempt_count,max_attempts,created_at)
           VALUES($1,$2,$3,'duplicate@example.test','welcome',1,'en','transactional',
                  '{}','queued',0,3,now())`,
          [randomUUID(), delivery[1], randomUUID()],
        ),
      ).rejects.toMatchObject({ code: '23505' });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.email_templates
           (id,code,version,locale,name,subject,body,data_contract,active,created_at,updated_at)
           VALUES($1,'bad',1,'fr','Bad','Bad','Bad','[]',true,now(),now())`,
          [randomUUID()],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await pool.query('DELETE FROM mymoneymap.users WHERE id=$1', [userId]);
      expect(
        (
          await pool.query<{ user_id: string | null }>(
            'SELECT user_id FROM mymoneymap.email_deliveries WHERE id=$1',
            [delivery[0]],
          )
        ).rows[0]?.user_id,
      ).toBeNull();
      await migrateOneDown(database);
      expect(
        (
          await pool.query<{ relation: string | null }>(
            `SELECT to_regclass('mymoneymap.email_deliveries')::text AS relation`,
          )
        ).rows[0]?.relation,
      ).toBeNull();
    });
  });
});
