import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';
import { PasswordService } from '../src/identity/password.service';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { hash } from '../src/identity/identity.service';
import { POSTGRES_POOL } from '../src/platform/database/database.constants';

describe('identity/access HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const verifiedEmail = `verified-${randomUUID()}@example.test`;
  const unverifiedEmail = `unverified-${randomUUID()}@example.test`;
  const password = 'synthetic-current-password';

  beforeAll(async () => {
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    const redis = await app.get(RedisSecurityService).ready();
    const testKeys: string[] = [];
    for await (const keys of redis.scanIterator({ MATCH: 'mymoneymap:*' })) {
      testKeys.push(...keys);
    }
    if (testKeys.length > 0) await redis.del(testKeys);
    pool = app.get(POSTGRES_POOL);
    passwordHash = await app.get(PasswordService).hash(password);
    await insertUser(verifiedEmail, true);
    await insertUser(unverifiedEmail, false);
  });

  afterAll(async () => {
    await app.close();
  });

  it('rotates a pre-authentication session, returns current user, and revokes logout', async () => {
    const agent = request.agent(app.getHttpServer());
    const preAuth = await agent.post('/api/v1/auth/passkey-sessions/options').send({}).expect(201);
    const preAuthCookie = cookieValue(preAuth.headers['set-cookie']);

    const login = await agent
      .post('/api/v1/auth/sessions')
      .send({ email: verifiedEmail, password, remember: false })
      .expect(204);
    const authenticatedCookie = cookieValue(login.headers['set-cookie']);
    expect(authenticatedCookie).not.toBe(preAuthCookie);

    await agent
      .get('/api/v1/users/me')
      .expect(200)
      .expect((response) => {
        expect(response.body).toMatchObject({
          email: verifiedEmail,
          role: 'free',
          emailVerified: true,
        });
      });
    await agent.delete('/api/v1/auth/session').expect(204);
    await agent.get('/api/v1/users/me').expect(401);
  });

  it('creates only a limited session for an unverified user', async () => {
    const agent = request.agent(app.getHttpServer());
    await agent
      .post('/api/v1/auth/sessions')
      .send({ email: unverifiedEmail, password })
      .expect(204);
    await agent.get('/api/v1/users/me').expect(200);
    await agent.post('/api/v1/auth/passkeys/registration-options').send({}).expect(403);
  });

  it('uses the same generic failure for unknown accounts and wrong passwords', async () => {
    const unknown = await request(app.getHttpServer())
      .post('/api/v1/auth/sessions')
      .send({ email: `unknown-${randomUUID()}@example.test`, password: 'wrong' })
      .expect(401);
    const known = await request(app.getHttpServer())
      .post('/api/v1/auth/sessions')
      .send({ email: verifiedEmail, password: 'wrong' })
      .expect(401);
    expect(known.body.error.code).toBe('UNAUTHORIZED');
    expect(known.body.error.message).toBe(unknown.body.error.message);
  });

  it('returns generic registration and verification responses without account enumeration', async () => {
    const newEmail = `registration-${randomUUID()}@example.test`;
    const registration = {
      email: newEmail,
      password: 'synthetic-registration-password',
      fullName: 'Registration User',
      dateOfBirth: '1990-01-01',
    };
    await request(app.getHttpServer())
      .post('/api/v1/auth/registrations')
      .send(registration)
      .expect(202)
      .expect({ status: 'accepted' });
    await request(app.getHttpServer())
      .post('/api/v1/auth/registrations')
      .send({ ...registration, email: verifiedEmail })
      .expect(202)
      .expect({ status: 'accepted' });

    const expiredToken = `expired-${randomUUID()}`;
    const registered = await pool.query<{ id: string }>(
      'SELECT id FROM mymoneymap.users WHERE email = $1',
      [newEmail],
    );
    await pool.query(
      `UPDATE mymoneymap.email_verification_tokens
          SET consumed_at = now()
        WHERE user_id = $1 AND consumed_at IS NULL`,
      [registered.rows[0]!.id],
    );
    await pool.query(
      `INSERT INTO mymoneymap.email_verification_tokens
         (id,user_id,token_hash,expires_at,created_at)
       VALUES ($1,$2,$3,now() - interval '1 second',now() - interval '1 hour')`,
      [randomUUID(), registered.rows[0]!.id, hash(expiredToken)],
    );
    const expired = await request(app.getHttpServer())
      .post('/api/v1/auth/email-verifications')
      .send({ token: expiredToken })
      .expect(400);
    const unknown = await request(app.getHttpServer())
      .post('/api/v1/auth/email-verifications')
      .send({ token: `unknown-${randomUUID()}` })
      .expect(400);
    expect(expired.body.error.message).toBe(unknown.body.error.message);
  });

  it('throttles repeated authentication attempts by account and records safe audit data', async () => {
    const email = `throttle-${randomUUID()}@example.test`;
    for (let attempt = 0; attempt < 5; attempt += 1) {
      await request(app.getHttpServer())
        .post('/api/v1/auth/sessions')
        .send({ email, password: 'wrong' })
        .expect(401);
    }
    await request(app.getHttpServer())
      .post('/api/v1/auth/sessions')
      .send({ email, password: 'wrong' })
      .expect(429);

    const audit = await pool.query<{ email_hash: string; ip_hash: string }>(
      `SELECT email_hash, ip_hash
         FROM mymoneymap.login_audit_events
        WHERE outcome = 'throttled'
        ORDER BY created_at DESC LIMIT 1`,
    );
    expect(audit.rows[0]?.email_hash).toMatch(/^[0-9a-f]{64}$/);
    expect(JSON.stringify(audit.rows)).not.toContain(email);
  });

  it('revokes every user session after current-password verified password change', async () => {
    const first = request.agent(app.getHttpServer());
    const second = request.agent(app.getHttpServer());
    await login(first, verifiedEmail, password);
    await login(second, verifiedEmail, password);

    await first
      .put('/api/v1/users/me/password')
      .send({ currentPassword: password, newPassword: 'synthetic-new-password' })
      .expect(204);
    await first.get('/api/v1/users/me').expect(401);
    await second.get('/api/v1/users/me').expect(401);

    await request(app.getHttpServer())
      .post('/api/v1/auth/sessions')
      .send({ email: verifiedEmail, password })
      .expect(401);
  });

  it('does not allow one user to delete another user passkey', async () => {
    const ownerId = randomUUID();
    const otherId = randomUUID();
    const ownerEmail = `owner-${randomUUID()}@example.test`;
    const otherEmail = `other-${randomUUID()}@example.test`;
    await insertUser(ownerEmail, true, ownerId);
    await insertUser(otherEmail, true, otherId);
    const passkeyId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.passkeys
         (id,user_id,credential_id,public_key,counter,device_type,backed_up,label,created_at)
       VALUES ($1,$2,$3,decode('0102','hex'),0,'singleDevice',false,'Owner key',now())`,
      [passkeyId, ownerId, `credential-${randomUUID()}`],
    );

    const other = request.agent(app.getHttpServer());
    await login(other, otherEmail, password);
    await other.delete(`/api/v1/auth/passkeys/${passkeyId}`).expect(404);
    expect(
      (
        await pool.query<{ count: string }>(
          'SELECT count(*)::text AS count FROM mymoneymap.passkeys WHERE id = $1',
          [passkeyId],
        )
      ).rows[0]?.count,
    ).toBe('1');
  });

  async function insertUser(email: string, verified: boolean, id = randomUUID()): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
         (id,email,password_hash,full_name,date_of_birth,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic User','1990-01-01',$4,now(),now())`,
      [id, email, passwordHash, verified ? new Date() : null],
    );
  }
});

async function login(
  agent: ReturnType<typeof request.agent>,
  email: string,
  password: string,
): Promise<void> {
  await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
}

function cookieValue(value: string[] | string | undefined): string {
  const first = Array.isArray(value) ? value[0] : value;
  if (!first) throw new Error('Expected session cookie');
  return first.split(';')[0]!;
}
