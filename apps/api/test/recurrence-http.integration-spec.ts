import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';
import type { UserRole } from '../src/identity/identity.types';
import { PasswordService } from '../src/identity/password.service';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { POSTGRES_POOL } from '../src/platform/database/database.constants';

jest.setTimeout(30_000);

interface RecurringRuleResponse {
  id: string;
  title: string;
  amount: string;
  economicType: string;
  rrule: string;
  forecast: {
    occurrences: string[];
    truncated: boolean;
    iterationLimit: number;
  } | null;
}

interface RecurringRulesResponse {
  items: RecurringRuleResponse[];
}

describe('recurrence scheduling HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-recurrence-password';
  const users = {
    free: { id: randomUUID(), email: `recurrence-free-${randomUUID()}@example.test` },
    premium: { id: randomUUID(), email: `recurrence-premium-${randomUUID()}@example.test` },
    other: { id: randomUUID(), email: `recurrence-other-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `recurrence-admin-${randomUUID()}@example.test` },
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
    await Promise.all([
      insertUser(users.free.id, users.free.email, 'free'),
      insertUser(users.premium.id, users.premium.email, 'premium'),
      insertUser(users.other.id, users.other.email, 'premium'),
      insertUser(users.admin.id, users.admin.email, 'admin'),
    ]);
  });

  afterAll(async () => {
    await app.close();
  });

  it('creates an exact typed rule and expands a side-effect-free month-end forecast', async () => {
    const agent = await loggedIn('premium');
    const category = await createCategory(agent, 'Recurring spending', 'spending');
    await agent
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Month end',
        amount: '125.500000000001',
        currency: 'HUF',
        economicType: 'expense',
        startsOn: '2024-01-31',
        rrule: 'BYMONTHDAY=31;FREQ=MONTHLY',
        categoryId: category.id,
      })
      .expect(201)
      .expect((response) => {
        const rule = responseBody(response).items.find(({ title }) => title === 'Month end');
        expect(rule).toMatchObject({
          amount: '125.500000000001',
          economicType: 'expense',
          rrule: 'FREQ=MONTHLY;BYMONTHDAY=31',
          forecast: null,
        });
      });

    const before = await persistedCounts(users.premium.id);
    await agent
      .get('/api/v1/recurring-rules?from=2024-01-01&to=2024-04-30')
      .expect(200)
      .expect((response) => {
        const rule = responseBody(response).items.find(({ title }) => title === 'Month end');
        expect(rule?.forecast).toEqual({
          from: '2024-01-01',
          to: '2024-04-30',
          occurrences: ['2024-01-31', '2024-02-29', '2024-03-31', '2024-04-30'],
          truncated: false,
          iterationLimit: 2000,
        });
      });
    expect(await persistedCounts(users.premium.id)).toEqual(before);
  });

  it('rejects unsupported rules and invalid financial, reference, range, and date semantics', async () => {
    const agent = await loggedIn('other');
    const income = await createCategory(agent, 'Recurring income', 'income');
    const spending = await createCategory(agent, 'Recurring expense', 'spending');
    const valid = {
      title: 'Invalid probe',
      amount: '10',
      currency: 'HUF',
      economicType: 'expense',
      startsOn: '2026-07-01',
      rrule: 'FREQ=DAILY',
    };

    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, rrule: 'FREQ=DAILY;BYSETPOS=1' })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, rrule: 'FREQ=HOURLY' })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, amount: '0' })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, currency: 'EUR' })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, categoryId: income.id })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, economicType: 'transfer', categoryId: spending.id })
      .expect(422);
    await agent
      .post('/api/v1/recurring-rules')
      .send({ ...valid, startsOn: '2026-02-30' })
      .expect(400);
    await agent.get('/api/v1/recurring-rules?from=2026-07-01').expect(400);
    await agent.get('/api/v1/recurring-rules?from=2026-08-01&to=2026-07-01').expect(400);
  });

  it('serializes the free active-schedule quota under concurrent creates', async () => {
    await insertRule(users.free.id, 'Existing free schedule');
    const agent = await loggedIn('free');
    const payload = {
      amount: '1',
      currency: 'HUF',
      economicType: 'expense',
      startsOn: '2026-07-01',
      rrule: '',
    };
    const responses = await Promise.all([
      agent.post('/api/v1/recurring-rules').send({ ...payload, title: 'Concurrent A' }),
      agent.post('/api/v1/recurring-rules').send({ ...payload, title: 'Concurrent B' }),
    ]);
    expect(responses.map(({ status }) => status).sort()).toEqual([201, 403]);
    expect(await ruleCount(users.free.id)).toBe('2');
  });

  it('enforces cross-user isolation and invalidates stored forecasts on an owned update', async () => {
    const owner = await loggedIn('other');
    const foreign = await loggedIn('premium');
    const created = await owner
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Owned schedule',
        amount: '20',
        currency: 'HUF',
        economicType: 'income',
        startsOn: '2026-07-01',
        rrule: 'FREQ=WEEKLY;BYDAY=WE',
      })
      .expect(201)
      .then((response) =>
        responseBody(response).items.find(({ title }) => title === 'Owned schedule'),
      );
    expect(created).toBeDefined();

    await foreign
      .patch(`/api/v1/recurring-rules/${created!.id}`)
      .send({ amount: '30' })
      .expect(404);
    await foreign.delete(`/api/v1/recurring-rules/${created!.id}`).expect(404);
    await insertOccurrence(created!.id, users.other.id);
    await owner
      .patch(`/api/v1/recurring-rules/${created!.id}`)
      .send({ amount: '30.000000000001' })
      .expect(200);
    expect(await occurrenceCount(created!.id)).toBe('0');
    await owner.delete(`/api/v1/recurring-rules/${created!.id}`).expect(204);
  });

  it('protects every recurrence route by authentication and personal-finance role', async () => {
    await request(app.getHttpServer()).get('/api/v1/recurring-rules').expect(401);
    await request(app.getHttpServer()).post('/api/v1/recurring-rules').send({}).expect(401);
    const admin = await loggedIn('admin');
    await admin.get('/api/v1/recurring-rules').expect(403);
    await admin
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Denied',
        amount: '1',
        currency: 'HUF',
        economicType: 'expense',
        startsOn: '2026-07-01',
        rrule: '',
      })
      .expect(403);
  });

  async function loggedIn(role: keyof typeof users): Promise<ReturnType<typeof request.agent>> {
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
       VALUES ($1,$2,$3,'Synthetic Recurrence User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function createCategory(
    agent: ReturnType<typeof request.agent>,
    label: string,
    kind: 'income' | 'spending',
  ): Promise<{ id: string }> {
    return agent
      .post('/api/v1/categories')
      .send({ label, kind, color: '#AABBCC' })
      .expect(201)
      .then((response) => {
        const category = (response.body as { items: { id: string; label: string }[] }).items.find(
          (item) => item.label === label,
        );
        if (!category) throw new Error(`Category ${label} was not returned`);
        return category;
      });
  }

  async function insertRule(userId: string, title: string): Promise<string> {
    const id = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.recurring_rules
        (id,user_id,title,amount,currency,economic_type,starts_on,rrule,created_at,updated_at)
       VALUES ($1,$2,$3,'1','HUF','expense','2026-07-01','',now(),now())`,
      [id, userId, title],
    );
    return id;
  }

  async function insertOccurrence(ruleId: string, userId: string): Promise<void> {
    const executionId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.recurrence_job_executions
        (id,job_key,queue_job_id,due_through,status,attempt_count,max_attempts,
         finished_at,created_at,updated_at)
       VALUES ($1,$2,$2,'2026-07-01','completed',1,3,now(),now(),now())`,
      [executionId, `http-test-${executionId}`],
    );
    await pool.query(
      `INSERT INTO mymoneymap.recurring_occurrences
        (id,rule_id,user_id,due_on,economic_type,amount,currency,state,
         job_execution_id,created_at)
       VALUES ($1,$2,$3,'2026-07-01','income','20','HUF','forecast',$4,now())`,
      [randomUUID(), ruleId, userId, executionId],
    );
  }

  async function persistedCounts(
    userId: string,
  ): Promise<{ rules: string; occurrences: string; jobs: string; journal: string }> {
    const result = await pool.query<{
      rules: string;
      occurrences: string;
      jobs: string;
      journal: string;
    }>(
      `SELECT
         (SELECT count(*)::text FROM mymoneymap.recurring_rules WHERE user_id = $1) AS rules,
         (SELECT count(*)::text FROM mymoneymap.recurring_occurrences WHERE user_id = $1)
           AS occurrences,
         (SELECT count(*)::text FROM mymoneymap.recurrence_job_executions) AS jobs,
         (SELECT count(*)::text FROM mymoneymap.journal_entries WHERE user_id = $1) AS journal`,
      [userId],
    );
    return result.rows[0]!;
  }

  async function ruleCount(userId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap.recurring_rules WHERE user_id = $1',
        [userId],
      )
    ).rows[0]!.count;
  }

  async function occurrenceCount(ruleId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap.recurring_occurrences WHERE rule_id = $1',
        [ruleId],
      )
    ).rows[0]!.count;
  }
});

function responseBody(response: { body: unknown }): RecurringRulesResponse {
  return response.body as RecurringRulesResponse;
}
