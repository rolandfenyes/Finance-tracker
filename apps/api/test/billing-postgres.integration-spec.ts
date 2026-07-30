import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);

describe('billing PostgreSQL contract', () => {
  it('enforces exact values, owner integrity, promotion boundaries, immutable audit, and rollback', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'billing_plans')).toBe('mymoneymap.billing_plans');
      expect(await relation(pool, 'billing_settings')).toBeNull();

      const adminId = await insertUser(pool, 'admin');
      const ownerId = await insertUser(pool, 'free');
      const otherId = await insertUser(pool, 'free');
      const planId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.billing_plans
           (id,code,name,price,currency,billing_interval,interval_count,role_slug,trial_days,
            is_active,metadata,created_at,updated_at)
         VALUES ($1,'synthetic-premium','Synthetic Premium','0.123456789012','USD','monthly',
                 1,'premium',14,true,'{}',now(),now())`,
        [planId],
      );
      expect(
        (
          await pool.query<{ price: string }>(
            'SELECT price::text FROM mymoneymap.billing_plans WHERE id=$1',
            [planId],
          )
        ).rows[0]!.price,
      ).toBe('0.123456789012');

      await expect(
        pool.query(
          `INSERT INTO mymoneymap.billing_promotions
             (id,code,name,discount_percent,metadata,created_at,updated_at)
           VALUES ($1,'TOO-MUCH','Too much','100.01','{}',now(),now())`,
          [randomUUID()],
        ),
      ).rejects.toMatchObject({ code: '23514' });

      const subscriptionId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.user_subscriptions
           (id,user_id,plan_code,plan_name,status,billing_interval,interval_count,amount,currency,
            started_at,created_at,updated_at)
         VALUES ($1,$2,'synthetic-premium','Synthetic Premium','active','monthly',1,
                 '0.123456789012','USD',now(),now(),now())`,
        [subscriptionId, ownerId],
      );
      const invoiceId = randomUUID();
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.user_invoices
             (id,user_id,subscription_id,invoice_number,status,total_amount,currency,
              issued_at,created_at,updated_at)
           VALUES ($1,$2,$3,$4,'open','1','USD',now(),now(),now())`,
          [invoiceId, otherId, subscriptionId, `INV-${randomUUID()}`],
        ),
      ).rejects.toMatchObject({ code: '23503' });

      const auditId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.privileged_audit_events
           (id,actor_user_id,action,target_type,target_id,details,created_at)
         VALUES ($1,$2,'billing.plan_created','billing_plan',$3,'{}',now())`,
        [auditId, adminId, planId],
      );
      await expect(
        pool.query('DELETE FROM mymoneymap.privileged_audit_events WHERE id=$1', [auditId]),
      ).rejects.toThrow('privileged audit events are immutable');

      await migrateOneDown(database);
      expect(await relation(pool, 'billing_plans')).toBeNull();
      expect(await relation(pool, 'privileged_audit_events')).toBe(
        'mymoneymap.privileged_audit_events',
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
