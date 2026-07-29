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
import type { UserRole } from '../src/identity/identity.types';

describe('users/settings HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-settings-password';
  const users = {
    free: { id: randomUUID(), email: `free-${randomUUID()}@example.test` },
    premium: { id: randomUUID(), email: `premium-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `admin-${randomUUID()}@example.test` },
  };

  beforeAll(async () => {
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    const redis = await app.get(RedisSecurityService).ready();
    const testKeys: string[] = [];
    for await (const keys of redis.scanIterator({ MATCH: 'mymoneymap:*' })) testKeys.push(...keys);
    if (testKeys.length > 0) await redis.del(testKeys);
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

  it.each([
    ['free', true, false, 1],
    ['premium', true, false, null],
    ['admin', false, true, null],
  ] as const)(
    'returns the approved %s entitlement contract',
    async (role, finance, admin, limit) => {
      const agent = await loggedIn(role);
      await agent
        .get('/api/v1/users/me')
        .expect(200)
        .expect((response) => {
          expect(response.body).toMatchObject({
            id: users[role].id,
            email: users[role].email,
            role,
            desiredLanguage: 'en',
            theme: 'verdant-horizon',
            entitlements: {
              personalFinanceAccess: finance,
              administration: admin,
              resources: { currencies: { allowed: finance, limit } },
            },
          });
        });
    },
  );

  it('updates only the authenticated profile and supported desired language', async () => {
    const agent = await loggedIn('free');
    await agent
      .patch('/api/v1/users/me')
      .send({ fullName: 'Updated Free User', dateOfBirth: '1991-02-03', desiredLanguage: 'hu' })
      .expect(200)
      .expect((response) => {
        expect(response.body).toMatchObject({
          id: users.free.id,
          fullName: 'Updated Free User',
          dateOfBirth: '1991-02-03',
          desiredLanguage: 'hu',
        });
      });
    const other = await pool.query<{ full_name: string; desired_language: string }>(
      'SELECT full_name, desired_language FROM mymoneymap.users WHERE id = $1',
      [users.premium.id],
    );
    expect(other.rows[0]).toEqual({ full_name: 'premium User', desired_language: 'en' });
  });

  it('rejects invalid locale, theme, date, whitespace name, and tutorial reversal', async () => {
    const agent = await loggedIn('premium');
    await agent.patch('/api/v1/users/me').send({}).expect(400);
    await agent.patch('/api/v1/users/me').send({ desiredLanguage: 'el' }).expect(400);
    await agent.patch('/api/v1/users/me').send({ dateOfBirth: '2026-02-30' }).expect(400);
    await agent.patch('/api/v1/users/me').send({ fullName: '   ' }).expect(400);
    await agent
      .patch('/api/v1/users/me/preferences/theme')
      .send({ theme: 'custom-css-payload' })
      .expect(400);
    await agent.patch('/api/v1/users/me/onboarding').send({ tutorialCompleted: false }).expect(400);
  });

  it('persists only theme IDs, advances theme onboarding, and completes tutorial', async () => {
    const agent = await loggedIn('premium');
    await agent
      .get('/api/v1/users/me/preferences/theme')
      .expect(200)
      .expect((response) => {
        expect(response.body.supportedThemes).toHaveLength(8);
      });
    await agent
      .patch('/api/v1/users/me/preferences/theme')
      .send({ theme: 'lilac-eclipse' })
      .expect(200)
      .expect((response) => expect(response.body.theme).toBe('lilac-eclipse'));
    await agent
      .get('/api/v1/users/me/onboarding')
      .expect(200)
      .expect((response) => expect(response.body).toMatchObject({ currentStep: 2, next: 'rules' }));
    await pool.query('UPDATE mymoneymap.users SET onboard_step = 6 WHERE id = $1', [
      users.premium.id,
    ]);
    await agent
      .patch('/api/v1/users/me/onboarding')
      .send({ tutorialCompleted: true })
      .expect(200)
      .expect((response) =>
        expect(response.body).toEqual({
          currentStep: 6,
          next: 'complete',
          onboardingComplete: true,
          tutorialRequired: false,
          tutorialCompleted: true,
        }),
      );
  });

  it('requires authentication for every settings route', async () => {
    await request(app.getHttpServer()).get('/api/v1/users/me').expect(401);
    await request(app.getHttpServer()).patch('/api/v1/users/me').send({}).expect(401);
    await request(app.getHttpServer()).get('/api/v1/users/me/preferences/theme').expect(401);
    await request(app.getHttpServer()).get('/api/v1/users/me/onboarding').expect(401);
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
       VALUES ($1,$2,$3,$4,'1990-01-01',$5,now(),now(),now())`,
      [id, email, passwordHash, `${role} User`, role],
    );
  }
});
