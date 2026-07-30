/* eslint-disable @typescript-eslint/explicit-function-return-type */
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
import { PrivacyDeletionProcessor } from '../src/privacy/privacy-deletion.service';
import { PRIVACY_MANIFEST_VERSION } from '../src/privacy/privacy-manifest';

jest.setTimeout(30_000);

describe('privacy HTTP contract and isolation', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-privacy-current-password';
  const owner = { id: randomUUID(), email: `privacy-http-owner-${randomUUID()}@example.test` };
  const other = { id: randomUUID(), email: `privacy-http-other-${randomUUID()}@example.test` };

  beforeAll(async () => {
    const module = await Test.createTestingModule({ imports: [AppModule] })
      .overrideProvider(PrivacyDeletionProcessor)
      .useValue({ process: jest.fn().mockResolvedValue(undefined) })
      .compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    pool = app.get(POSTGRES_POOL);
    const redis = await app.get(RedisSecurityService).ready();
    const keys: string[] = [];
    for await (const batch of redis.scanIterator({ MATCH: 'mymoneymap:*privacy*' })) {
      keys.push(...batch);
    }
    if (keys.length) await redis.del(keys);
    passwordHash = await app.get(PasswordService).hash(password);
    await Promise.all([insertUser(owner.id, owner.email), insertUser(other.id, other.email)]);
  });

  afterAll(async () => app.close());

  it('requires authentication and keeps production export storage configuration-gated', async () => {
    await request(app.getHttpServer())
      .post('/api/v1/privacy/exports')
      .set('Idempotency-Key', 'privacy-export-anonymous')
      .expect(401);
    const agent = await loggedIn(owner.email);
    await agent
      .post('/api/v1/privacy/exports')
      .set('Idempotency-Key', 'privacy-export-disabled')
      .expect(503)
      .expect((response) =>
        expect(response.body.error).toMatchObject({ code: 'SERVICE_UNAVAILABLE' }),
      );
  });

  it('validates reauthentication and idempotently queues account deletion', async () => {
    const agent = await loggedIn(owner.email);
    await agent
      .post('/api/v1/privacy/deletion-requests')
      .set('Idempotency-Key', 'privacy-delete-wrong-password')
      .send({ confirmEmail: owner.email, password: 'wrong-password' })
      .expect(403);
    await agent
      .post('/api/v1/privacy/deletion-requests')
      .set('Idempotency-Key', 'privacy-delete-invalid-body')
      .send({ confirmEmail: 'not-an-email', password: 'short' })
      .expect(400);
    const first = await agent
      .post('/api/v1/privacy/deletion-requests')
      .set('Idempotency-Key', 'privacy-delete-stable-http-key')
      .send({ confirmEmail: owner.email.toUpperCase(), password })
      .expect(202);
    const duplicate = await agent
      .post('/api/v1/privacy/deletion-requests')
      .set('Idempotency-Key', 'privacy-delete-stable-http-key')
      .send({ confirmEmail: owner.email, password })
      .expect(202);
    expect(duplicate.body.id).toBe(first.body.id);
  });

  it('does not expose another user export request', async () => {
    const exportId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.privacy_export_requests
       (id,user_id,manifest_version,idempotency_key_hash,status,attempt_count,max_attempts,created_at)
       VALUES($1,$2,$3,$4,'queued',0,3,now())`,
      [exportId, owner.id, PRIVACY_MANIFEST_VERSION, 'a'.repeat(64)],
    );
    await (await loggedIn(other.email)).get(`/api/v1/privacy/exports/${exportId}`).expect(404);
    await (
      await loggedIn(owner.email)
    )
      .get(`/api/v1/privacy/exports/${exportId}`)
      .expect(200)
      .expect((response) =>
        expect(response.body).toMatchObject({ id: exportId, status: 'queued' }),
      );
  });

  async function loggedIn(email: string) {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  async function insertUser(id: string, email: string): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
       (id,email,password_hash,full_name,date_of_birth,role,status,email_verified_at,created_at,
        updated_at,theme,desired_language,onboard_step,needs_tutorial,tutorial_seen)
       VALUES($1,$2,$3,'Synthetic Privacy User','1990-01-01','free','active',now(),now(),now(),
              'verdant-horizon','en',1,false,true)`,
      [id, email, passwordHash],
    );
  }
});
