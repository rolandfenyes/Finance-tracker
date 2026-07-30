import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';
import { PasswordService } from '../src/identity/password.service';
import type { UserRole } from '../src/identity/identity.types';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { POSTGRES_POOL } from '../src/platform/database/database.constants';

jest.setTimeout(30_000);

describe('feedback/administration/system HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-step16-password';
  const secret = `synthetic-secret-${randomUUID()}`;
  const users = {
    admin: {
      id: randomUUID(),
      email: `admin-${randomUUID()}@example.test`,
      fullName: 'Synthetic Administrator',
    },
    owner: {
      id: randomUUID(),
      email: `owner-${randomUUID()}@example.test`,
      fullName: 'Synthetic Feedback Owner',
    },
    other: {
      id: randomUUID(),
      email: `other-${randomUUID()}@example.test`,
      fullName: 'Synthetic Other User',
    },
    unverified: {
      id: randomUUID(),
      email: `unverified-${randomUUID()}@example.test`,
      fullName: 'Synthetic Unverified User',
    },
  };

  beforeAll(async () => {
    process.env.SETTINGS_ENCRYPTION_KEY = Buffer.from('synthetic-test-key-is-32-bytes!!').toString(
      'base64',
    );
    process.env.ACCOUNT_RECOVERY_TTL_SECONDS = '3600';
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    const redis = await app.get(RedisSecurityService).ready();
    const keys: string[] = [];
    for await (const batch of redis.scanIterator({ MATCH: 'mymoneymap:*' })) keys.push(...batch);
    if (keys.length > 0) await redis.del(keys);
    pool = app.get(POSTGRES_POOL);
    passwordHash = await app.get(PasswordService).hash(password);
    await insertUser('admin', users.admin, true);
    await insertUser('free', users.owner, true);
    await insertUser('premium', users.other, true);
    await insertUser('free', users.unverified, false);
  });

  afterAll(async () => {
    await app.close();
  });

  it('denies every administrative family to authenticated non-admins', async () => {
    const agent = await login(users.owner.email);
    await agent.get('/api/v1/admin/dashboard').expect(403);
    await agent.get('/api/v1/admin/analytics').expect(403);
    await agent.get('/api/v1/admin/users').expect(403);
    await agent.get('/api/v1/admin/feedback').expect(403);
    await agent.get('/api/v1/admin/system').expect(403);
    await agent
      .put('/api/v1/admin/integrations/finnhub')
      .send({ name: 'Finnhub', secret })
      .expect(403);
  });

  it('isolates owned feedback, paginates, validates status authority, and permits owned deletion', async () => {
    const owner = await login(users.owner.email);
    const other = await login(users.other.email);
    const ids: string[] = [];
    for (const title of ['First synthetic bug', 'Second synthetic idea', 'Third synthetic bug']) {
      const created = await owner
        .post('/api/v1/feedback')
        .send({
          kind: title.includes('idea') ? 'idea' : 'bug',
          title,
          message: `${title} details`,
          severity: 'medium',
        })
        .expect(201);
      ids.push(created.body.id as string);
    }

    const firstPage = await owner.get('/api/v1/feedback?limit=2').expect(200);
    expect(firstPage.body.items).toHaveLength(2);
    expect(firstPage.body.nextCursor).toEqual(expect.any(String));
    const secondPage = await owner
      .get(`/api/v1/feedback?limit=2&cursor=${firstPage.body.nextCursor}`)
      .expect(200);
    expect(secondPage.body.items).toHaveLength(1);
    expect(secondPage.body.nextCursor).toBeNull();

    await other.patch(`/api/v1/feedback/${ids[0]}/status`).send({ status: 'closed' }).expect(404);
    await other.delete(`/api/v1/feedback/${ids[0]}`).expect(404);
    await owner.patch(`/api/v1/feedback/${ids[0]}/status`).send({ status: 'resolved' }).expect(400);
    await owner.patch(`/api/v1/feedback/${ids[0]}/status`).send({ status: 'closed' }).expect(200);
    await owner.delete(`/api/v1/feedback/${ids[1]}`).expect(204);
    const otherList = await other.get('/api/v1/feedback').expect(200);
    expect(otherList.body.items).toEqual([]);
  });

  it('redacts PII, exposes defined non-billing metrics, moderates feedback, and writes audit records', async () => {
    const admin = await login(users.admin.email);
    const userList = await admin.get('/api/v1/admin/users?limit=2').expect(200);
    expect(userList.body.items).toHaveLength(2);
    expect(userList.body.nextCursor).toEqual(expect.any(String));
    const serializedUsers = JSON.stringify(userList.body);
    expect(serializedUsers).not.toContain(users.admin.email);
    expect(serializedUsers).not.toContain(users.admin.fullName);
    expect(userList.body.items[0]).toHaveProperty('emailMasked');

    const dashboard = await admin.get('/api/v1/admin/dashboard').expect(200);
    expect(dashboard.body.metrics).toEqual(
      expect.objectContaining({
        users: expect.any(Number),
        postedJournalEntries: expect.any(Number),
        goals: expect.any(Number),
        loans: expect.any(Number),
      }),
    );
    expect(dashboard.body.definitions.postedJournalEntries).toContain('journal');
    expect(JSON.stringify(dashboard.body)).not.toContain(users.owner.email);
    expect(JSON.stringify(dashboard.body)).not.toMatch(/revenue|subscription|payment/i);

    const analytics = await admin.get('/api/v1/admin/analytics').expect(200);
    expect(analytics.body.registrationsByUtcDay).toHaveLength(30);
    expect(analytics.body.definitions.active).toContain('status');
    expect(JSON.stringify(analytics.body)).not.toMatch(/revenue|subscription|payment/i);

    const feedback = await admin.get('/api/v1/admin/feedback?status=closed').expect(200);
    const feedbackItems = feedback.body.items as Array<{ id: string; user: { id: string } }>;
    const target = feedbackItems.find(
      (item: { user: { id: string } }) => item.user.id === users.owner.id,
    );
    expect(target).toBeDefined();
    expect(JSON.stringify(target)).not.toContain(users.owner.email);
    await admin
      .patch(`/api/v1/admin/feedback/${target!.id}`)
      .send({ status: 'resolved', severity: 'high' })
      .expect(200);
    await admin
      .post(`/api/v1/admin/feedback/${target!.id}/responses`)
      .send({ message: 'Synthetic staff resolution' })
      .expect(201);

    const owner = await login(users.owner.email);
    const ownerFeedback = await owner.get('/api/v1/feedback?status=resolved').expect(200);
    expect(ownerFeedback.body.items[0].responses[0].message).toBe('Synthetic staff resolution');
    const audit = await pool.query<{ action: string; details: unknown }>(
      `SELECT action,details FROM mymoneymap.privileged_audit_events
        WHERE target_id=$1 ORDER BY created_at`,
      [target!.id],
    );
    expect(audit.rows.map((row) => row.action)).toEqual(['feedback.updated', 'feedback.responded']);
    expect(JSON.stringify(audit.rows)).not.toContain('Synthetic staff resolution');
  });

  it('encrypts write-only integration secrets, masks reads, rejects secret metadata, and audits changes', async () => {
    const admin = await login(users.admin.email);
    const written = await admin
      .put('/api/v1/admin/integrations/finnhub')
      .send({
        name: 'Finnhub market data',
        secret,
        status: 'inactive',
        metadata: { environment: 'synthetic' },
      })
      .expect(200);
    expect(written.body).toMatchObject({
      service: 'finnhub',
      configured: true,
      secret: '[REDACTED]',
    });
    expect(JSON.stringify(written.body)).not.toContain(secret);

    const stored = await pool.query<{ api_key_encrypted: string }>(
      'SELECT api_key_encrypted FROM mymoneymap.api_integrations WHERE service=$1',
      ['finnhub'],
    );
    expect(stored.rows[0]?.api_key_encrypted).toMatch(/^v1\./);
    expect(stored.rows[0]?.api_key_encrypted).not.toContain(secret);

    const system = await admin.get('/api/v1/admin/system').expect(200);
    expect(JSON.stringify(system.body)).not.toContain(secret);
    expect(system.body.integrations[0].secret).toBe('[REDACTED]');
    expect(system.body.canonicalUrl).toBe(process.env.APP_BASE_URL);

    await admin
      .put('/api/v1/admin/integrations/postmark')
      .send({
        name: 'Postmark',
        secret,
        metadata: { apiKey: 'must-not-be-hidden-here' },
      })
      .expect(400);

    await admin
      .patch('/api/v1/admin/system/settings')
      .send({ siteName: 'Synthetic Money Map', maintenanceMode: true })
      .expect(200);
    const actions = await pool.query<{ action: string }>(
      `SELECT action FROM mymoneymap.privileged_audit_events
        WHERE actor_user_id=$1 AND action IN ('integration.upserted','system.settings_updated')`,
      [users.admin.id],
    );
    expect(actions.rows.map((row) => row.action).sort()).toEqual([
      'integration.upserted',
      'system.settings_updated',
    ]);
  });

  it('uses expiring hashed recovery actions without changing email or returning tokens/passwords', async () => {
    const admin = await login(users.admin.email);
    const secondAdmin = await login(users.admin.email);
    const resetResponses = await Promise.all([
      admin.post(`/api/v1/admin/users/${users.owner.id}/password-reset-request`).expect(202),
      secondAdmin.post(`/api/v1/admin/users/${users.owner.id}/password-reset-request`).expect(202),
    ]);
    for (const response of resetResponses) {
      const serialized = JSON.stringify(response.body);
      expect(response.body.status).toBe('accepted');
      expect(serialized).not.toMatch(/token|password/i);
    }
    const resets = await pool.query<{
      token_hash: string;
      consumed_at: Date | null;
      expires_at: Date;
      created_at: Date;
    }>(
      `SELECT token_hash,consumed_at,expires_at,created_at
         FROM mymoneymap.account_recovery_requests
        WHERE user_id=$1 AND kind='password_reset' ORDER BY created_at`,
      [users.owner.id],
    );
    expect(resets.rows).toHaveLength(2);
    expect(resets.rows.filter((row) => row.consumed_at === null)).toHaveLength(1);
    expect(resets.rows.every((row) => /^[0-9a-f]{64}$/.test(row.token_hash))).toBe(true);
    expect(resets.rows.every((row) => row.expires_at > row.created_at)).toBe(true);

    const pendingEmail = `pending-${randomUUID()}@example.test`;
    await admin
      .post(`/api/v1/admin/users/${users.owner.id}/email-change-request`)
      .send({ email: pendingEmail })
      .expect(202)
      .expect((response) => expect(JSON.stringify(response.body)).not.toContain(pendingEmail));
    const owner = await pool.query<{ email: string }>(
      'SELECT email FROM mymoneymap.users WHERE id=$1',
      [users.owner.id],
    );
    expect(owner.rows[0]?.email).toBe(users.owner.email);

    await admin
      .post(`/api/v1/admin/users/${users.unverified.id}/email-verification-request`)
      .expect(202)
      .expect((response) => expect(JSON.stringify(response.body)).not.toMatch(/token/i));
    const verification = await pool.query<{ token_hash: string }>(
      `SELECT token_hash FROM mymoneymap.email_verification_tokens
        WHERE user_id=$1 AND consumed_at IS NULL`,
      [users.unverified.id],
    );
    expect(verification.rows[0]?.token_hash).toMatch(/^[0-9a-f]{64}$/);

    const audit = await pool.query<{ action: string; details: unknown }>(
      `SELECT action,details FROM mymoneymap.privileged_audit_events
        WHERE target_id IN ($1,$2)
          AND action LIKE 'user.%requested'
        ORDER BY created_at`,
      [users.owner.id, users.unverified.id],
    );
    expect(audit.rows).toHaveLength(4);
    expect(JSON.stringify(audit.rows)).not.toContain(pendingEmail);
  });

  it('limits roles, audits user changes, blocks self-deactivation, and revokes inactive sessions', async () => {
    const admin = await login(users.admin.email);
    await admin
      .put(`/api/v1/admin/users/${users.other.id}/role`)
      .send({ role: 'support' })
      .expect(400);
    await admin
      .put(`/api/v1/admin/users/${users.other.id}/role`)
      .send({ role: 'premium' })
      .expect(200);
    await admin
      .put(`/api/v1/admin/users/${users.admin.id}/status`)
      .send({ status: 'inactive' })
      .expect(409);

    const other = await login(users.other.email);
    await admin
      .put(`/api/v1/admin/users/${users.other.id}/status`)
      .send({ status: 'inactive' })
      .expect(200);
    await other.get('/api/v1/users/me').expect(401);
    const actions = await pool.query<{ action: string }>(
      `SELECT action FROM mymoneymap.privileged_audit_events
        WHERE target_id=$1 AND action IN ('user.role_updated','user.status_updated')`,
      [users.other.id],
    );
    expect(actions.rows.map((row) => row.action).sort()).toEqual([
      'user.role_updated',
      'user.status_updated',
    ]);
  });

  async function login(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  async function insertUser(
    role: UserRole,
    user: { id: string; email: string; fullName: string },
    verified: boolean,
  ): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
         (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,$4,'1990-01-01',$5,$6,now(),now())`,
      [user.id, user.email, passwordHash, user.fullName, role, verified ? new Date() : null],
    );
  }
});
