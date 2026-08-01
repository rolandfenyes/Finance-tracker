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

describe('currency HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-currency-password';
  const users = {
    free: { id: randomUUID(), email: `fx-free-${randomUUID()}@example.test` },
    premium: { id: randomUUID(), email: `fx-premium-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `fx-admin-${randomUUID()}@example.test` },
  };

  beforeAll(async () => {
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
    await Promise.all(
      (Object.entries(users) as Array<[UserRole, (typeof users)[UserRole]]>).map(([role, user]) =>
        insertUser(user.id, user.email, role),
      ),
    );
  });

  afterAll(async () => {
    await app.close();
  });

  it('returns the catalogue and the default main membership without mutating on reads', async () => {
    const agent = await loggedIn('premium');
    const before = await pool.query<{ count: string }>(
      'SELECT count(*)::text AS count FROM mymoneymap.user_currencies WHERE user_id = $1',
      [users.premium.id],
    );
    await agent
      .get('/api/v1/currencies')
      .expect(200)
      .expect((response) => {
        expect(response.body.items).toEqual(
          expect.arrayContaining([
            expect.objectContaining({
              code: 'HUF',
              name: 'Hungarian Forint',
              minorUnit: 2,
              roundingMode: 'HALF_EVEN',
            }),
            expect.objectContaining({ code: 'JPY', minorUnit: 0 }),
          ]),
        );
      });
    await agent
      .get('/api/v1/users/me/currencies')
      .expect(200)
      .expect((response) => {
        expect(response.body).toMatchObject({
          mainCurrency: 'HUF',
          items: [expect.objectContaining({ code: 'HUF', isMain: true })],
        });
      });
    const after = await pool.query<{ count: string }>(
      'SELECT count(*)::text AS count FROM mymoneymap.user_currencies WHERE user_id = $1',
      [users.premium.id],
    );
    expect(after.rows[0]).toEqual(before.rows[0]);
  });

  it('adds, changes main atomically, removes only non-main, and advances currency onboarding', async () => {
    await pool.query('UPDATE mymoneymap.users SET onboard_step = 3 WHERE id = $1', [
      users.premium.id,
    ]);
    const agent = await loggedIn('premium');
    await agent
      .post('/api/v1/users/me/currencies')
      .send({ code: 'EUR' })
      .expect(201)
      .expect((response) => {
        expect(response.body.items).toEqual(
          expect.arrayContaining([expect.objectContaining({ code: 'EUR', isMain: false })]),
        );
      });
    await agent
      .put('/api/v1/users/me/main-currency')
      .send({ code: 'EUR' })
      .expect(200)
      .expect((response) => expect(response.body.mainCurrency).toBe('EUR'));
    await agent.delete('/api/v1/users/me/currencies/EUR').expect(409);
    await agent.delete('/api/v1/users/me/currencies/HUF').expect(204);
    const state = await pool.query<{ onboard_step: number; main_count: string }>(
      `SELECT u.onboard_step,
              count(*) FILTER (WHERE uc.is_main)::text AS main_count
         FROM mymoneymap.users u
         JOIN mymoneymap.user_currencies uc ON uc.user_id = u.id
        WHERE u.id = $1
        GROUP BY u.onboard_step`,
      [users.premium.id],
    );
    expect(state.rows[0]).toEqual({ onboard_step: 4, main_count: '1' });
  });

  it('lets free onboarding replace the provisional main currency without raising its quota', async () => {
    await pool.query('UPDATE mymoneymap.users SET onboard_step = 3 WHERE id = $1', [users.free.id]);
    const free = await loggedIn('free');

    await free
      .post('/api/v1/users/me/currencies')
      .send({ code: 'AUD' })
      .expect(201)
      .expect((response) => {
        expect(response.body).toMatchObject({
          mainCurrency: 'AUD',
          items: [expect.objectContaining({ code: 'AUD', isMain: true })],
        });
      });

    const state = await pool.query<{ onboard_step: number; currency_count: string }>(
      `SELECT u.onboard_step, count(uc.code)::text AS currency_count
         FROM mymoneymap.users u
         JOIN mymoneymap.user_currencies uc ON uc.user_id = u.id
        WHERE u.id = $1
        GROUP BY u.onboard_step`,
      [users.free.id],
    );
    expect(state.rows[0]).toEqual({ onboard_step: 4, currency_count: '1' });
    await free.post('/api/v1/users/me/currencies').send({ code: 'EUR' }).expect(403);
  });

  it('enforces the free quota, validation, membership ownership, verification, and admin boundary', async () => {
    const free = await loggedIn('free');
    await free.post('/api/v1/users/me/currencies').send({ code: 'EUR' }).expect(403);
    await free.post('/api/v1/users/me/currencies').send({ code: 'eur' }).expect(400);
    await free.post('/api/v1/users/me/currencies').send({ code: 'AAA' }).expect(422);
    await free.put('/api/v1/users/me/main-currency').send({ code: 'USD' }).expect(404);

    const admin = await loggedIn('admin');
    await admin.get('/api/v1/currencies').expect(403);
    await admin.get('/api/v1/users/me/currencies').expect(403);

    await pool.query('UPDATE mymoneymap.users SET email_verified_at = NULL WHERE id = $1', [
      users.free.id,
    ]);
    const unverified = await loggedIn('free');
    await unverified.get('/api/v1/currencies').expect(403);
  });

  it('requires authentication on every public currency route', async () => {
    await request(app.getHttpServer()).get('/api/v1/currencies').expect(401);
    await request(app.getHttpServer()).get('/api/v1/users/me/currencies').expect(401);
    await request(app.getHttpServer())
      .post('/api/v1/users/me/currencies')
      .send({ code: 'EUR' })
      .expect(401);
    await request(app.getHttpServer())
      .put('/api/v1/users/me/main-currency')
      .send({ code: 'EUR' })
      .expect(401);
    await request(app.getHttpServer()).delete('/api/v1/users/me/currencies/EUR').expect(401);
  });

  async function loggedIn(role: UserRole): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent
      .post('/api/v1/auth/sessions')
      .send({ email: users[role].email, password })
      .expect(204);
    return agent;
  }

  async function insertUser(id: string, email: string, role: UserRole): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
        (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Currency User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }
});
