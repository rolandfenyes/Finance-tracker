import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);

describe('admin/feedback/system PostgreSQL contract', () => {
  it('applies constrained owned records, immutable privileged audit, and rolls Step 16 back', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'feedback')).toBe('mymoneymap.feedback');
      expect(await relation(pool, 'privileged_audit_events')).toBe(
        'mymoneymap.privileged_audit_events',
      );

      const adminId = await insertUser(pool, 'admin');
      const ownerId = await insertUser(pool, 'free');
      const feedbackId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.feedback
           (id,user_id,kind,title,message,severity,status,created_at,updated_at)
         VALUES ($1,$2,'bug','Synthetic bug','Synthetic details','high','open',now(),now())`,
        [feedbackId, ownerId],
      );

      await expect(
        pool.query(
          `INSERT INTO mymoneymap.feedback
             (id,user_id,kind,title,message,status,created_at,updated_at)
           VALUES ($1,$2,'unsupported','x','x','open',now(),now())`,
          [randomUUID(), ownerId],
        ),
      ).rejects.toMatchObject({ code: '23514' });

      const auditId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.privileged_audit_events
           (id,actor_user_id,action,target_type,target_id,details,created_at)
         VALUES ($1,$2,'feedback.updated','feedback',$3,'{}',now())`,
        [auditId, adminId, feedbackId],
      );
      await expect(
        pool.query('UPDATE mymoneymap.privileged_audit_events SET target_id=NULL WHERE id=$1', [
          auditId,
        ]),
      ).rejects.toThrow('privileged audit events are immutable');
      await expect(
        pool.query('DELETE FROM mymoneymap.privileged_audit_events WHERE id=$1', [auditId]),
      ).rejects.toThrow('privileged audit events are immutable');

      await migrateOneDown(database);
      expect(await relation(pool, 'feedback')).toBeNull();
      expect(await relation(pool, 'privileged_audit_events')).toBeNull();
      expect(await relation(pool, 'securities_trades')).toBe('mymoneymap.securities_trades');

      await migrateToLatest(database);
      expect(await relation(pool, 'account_recovery_requests')).toBe(
        'mymoneymap.account_recovery_requests',
      );
    });
  });
});

async function insertUser(pool: Pool, role: 'free' | 'admin'): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
       (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic User','1990-01-01',$3,now(),now(),now())`,
    [id, `${role}-${randomUUID()}@example.test`, role],
  );
  return id;
}

async function relation(pool: Pool, name: string): Promise<string | null> {
  return (
    await pool.query<{ relation: string | null }>('SELECT to_regclass($1)::text relation', [
      `mymoneymap.${name}`,
    ])
  ).rows[0]!.relation;
}
