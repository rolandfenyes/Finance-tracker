import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';
import { PasswordService } from '../src/identity/password.service';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { POSTGRES_POOL } from '../src/platform/database/database.constants';

jest.setTimeout(30_000);

describe('administrative billing HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-step17-password';
  const admin = { id: randomUUID(), email: `admin-${randomUUID()}@example.test` };
  const owner = { id: randomUUID(), email: `owner-${randomUUID()}@example.test` };

  beforeAll(async () => {
    process.env.SETTINGS_ENCRYPTION_KEY = Buffer.from('synthetic-test-key-is-32-bytes!!').toString(
      'base64',
    );
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    const redis = await app.get(RedisSecurityService).ready();
    const keys: string[] = [];
    for await (const batch of redis.scanIterator({ MATCH: 'mymoneymap:*' })) keys.push(...batch);
    if (keys.length) await redis.del(keys);
    pool = app.get(POSTGRES_POOL);
    passwordHash = await app.get(PasswordService).hash(password);
    await insertUser(admin, 'admin');
    await insertUser(owner, 'free');
  });

  afterAll(async () => app.close());

  it('denies non-admins and exposes no provider or secret endpoint', async () => {
    const user = await login(owner.email);
    await user.get('/api/v1/admin/billing/summary').expect(403);
    await user.get('/api/v1/admin/billing/plans').expect(403);
    await user.get('/api/v1/admin/billing/promotions').expect(403);
    const staff = await login(admin.email);
    await staff
      .post('/api/v1/admin/billing/settings')
      .send({ stripeSecretKey: 'nope' })
      .expect(404);
    await staff.post('/api/v1/admin/billing/checkout').send({}).expect(404);
    await staff.post('/api/v1/admin/billing/webhook').send({}).expect(404);
  });

  it('preserves exact catalog values, promotion boundaries, and audited entitlement transition', async () => {
    const staff = await login(admin.email);
    const created = await staff
      .post('/api/v1/admin/billing/plans')
      .send({
        code: `premium-${randomUUID().slice(0, 8)}`,
        name: 'Synthetic Premium',
        price: '12.123456789012',
        currency: 'USD',
        billingInterval: 'monthly',
        intervalCount: 1,
        roleSlug: 'premium',
        trialDays: 14,
        isActive: true,
        metadata: { source: 'synthetic' },
      })
      .expect(201);
    expect(created.body.price).toBe('12.123456789012');
    const planId = created.body.id as string;

    await staff
      .post('/api/v1/admin/billing/promotions')
      .send({
        code: `PROMO-${randomUUID().slice(0, 8).toUpperCase()}`,
        name: 'Invalid percent',
        discountPercent: '100.01',
        metadata: {},
      })
      .expect(400);
    const promotion = await staff
      .post('/api/v1/admin/billing/promotions')
      .send({
        code: `PROMO-${randomUUID().slice(0, 8).toUpperCase()}`,
        name: 'Synthetic promotion',
        discountPercent: '12.50',
        planCode: created.body.code,
        metadata: {},
      })
      .expect(201);
    expect(promotion.body.discountPercent).toBe('12.50');

    const assignment = await staff
      .put(`/api/v1/admin/users/${owner.id}/subscription`)
      .send({ planId, status: 'trialing', notes: 'Synthetic assignment' })
      .expect(200);
    expect(assignment.body).toMatchObject({
      userId: owner.id,
      planCode: created.body.code,
      amount: '12.123456789012',
      currency: 'USD',
      role: 'premium',
    });
    const persisted = await pool.query<{
      role: string;
      amount: string;
      trial_ends_at: Date | null;
    }>(
      `SELECT u.role,s.amount::text,s.trial_ends_at
         FROM mymoneymap.users u JOIN mymoneymap.user_subscriptions s ON s.user_id=u.id
        WHERE u.id=$1 ORDER BY s.created_at DESC LIMIT 1`,
      [owner.id],
    );
    expect(persisted.rows[0]).toMatchObject({ role: 'premium', amount: '12.123456789012' });
    expect(persisted.rows[0]!.trial_ends_at).not.toBeNull();
    const audit = await pool.query<{ details: Record<string, string> }>(
      `SELECT details FROM mymoneymap.privileged_audit_events
        WHERE action='billing.subscription_assigned' AND target_id=$1`,
      [assignment.body.id],
    );
    expect(audit.rows[0]!.details).toMatchObject({ fromRole: 'free', toRole: 'premium' });

    const summary = await staff.get('/api/v1/admin/billing/summary').expect(200);
    expect(summary.body.mode).toBe('administrative_records_only');
    expect(summary.body.providerCapabilities).toEqual({
      checkout: false,
      portal: false,
      webhooks: false,
      customerCancellation: false,
    });
    expect(JSON.stringify(summary.body)).not.toMatch(/secretKey|webhookSecret/i);
  });

  it('enforces invoice ownership/currency and records exact payments with audit history', async () => {
    const staff = await login(admin.email);
    const invoiceId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.user_invoices
         (id,user_id,invoice_number,status,total_amount,currency,issued_at,created_at,updated_at)
       VALUES ($1,$2,$3,'open','20.000000000001','USD',now(),now(),now())`,
      [invoiceId, owner.id, `INV-${randomUUID()}`],
    );
    const payment = await staff
      .post('/api/v1/admin/payments')
      .send({
        userId: owner.id,
        invoiceId,
        type: 'charge',
        status: 'succeeded',
        amount: '20.000000000001',
        currency: 'USD',
        gateway: 'manual-record',
        transactionReference: 'synthetic-reference',
        processedAt: new Date().toISOString(),
      })
      .expect(201);
    expect(payment.body.amount).toBe('20.000000000001');
    await staff
      .patch(`/api/v1/admin/invoices/${invoiceId}`)
      .send({ status: 'paid', paidAt: new Date().toISOString() })
      .expect(200);
    const actions = await pool.query<{ action: string }>(
      `SELECT action FROM mymoneymap.privileged_audit_events
        WHERE target_id IN ($1,$2) ORDER BY created_at`,
      [payment.body.id, invoiceId],
    );
    expect(actions.rows.map((row) => row.action).sort()).toEqual([
      'billing.invoice_updated',
      'billing.payment_created',
    ]);
  });

  async function login(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  async function insertUser(
    user: { id: string; email: string },
    role: 'free' | 'admin',
  ): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
         (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Billing User','1990-01-01',$4,now(),now(),now())`,
      [user.id, user.email, passwordHash, role],
    );
  }
});
