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

interface GoalResponse {
  id: string;
  title: string;
  targetAmount: string;
  currentAmount: string;
  remainingAmount: string;
  progressPercent: string;
  currency: string;
  status: string;
  archivedAt: string | null;
  categoryId: string | null;
  recurringRule: { id: string; goalId: string; rrule: string; amount: string } | null;
  contributions: Array<{
    id: string;
    amount: string;
    goalAmount: string;
    reversedByJournalEntryId: string | null;
    correctsContributionId: string | null;
  }>;
}

interface GoalsResponse {
  items: GoalResponse[];
}

describe('goals HTTP, ledger, and PostgreSQL contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-goals-password';
  const users = {
    premium: { id: randomUUID(), email: `goals-premium-${randomUUID()}@example.test` },
    other: { id: randomUUID(), email: `goals-other-${randomUUID()}@example.test` },
    free: { id: randomUUID(), email: `goals-free-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `goals-admin-${randomUUID()}@example.test` },
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
      insertUser(users.premium.id, users.premium.email, 'premium'),
      insertUser(users.other.id, users.other.email, 'premium'),
      insertUser(users.free.id, users.free.email, 'free'),
      insertUser(users.admin.id, users.admin.email, 'admin'),
    ]);
  });

  afterAll(async () => {
    await app.close();
  });

  it('completes exactly at target, locks, and replays a contribution without duplicates', async () => {
    const agent = await loggedIn('premium');
    const goal = await createGoal(agent, 'Golden target', '1000');

    await contribute(agent, goal.id, '400', 'golden-400')
      .expect(201)
      .expect('Idempotency-Replayed', 'false')
      .expect((response) => {
        expect(goalBody(response)).toMatchObject({
          currentAmount: '400',
          remainingAmount: '600',
          status: 'active',
        });
      });

    await contribute(agent, goal.id, '600', 'golden-600')
      .expect(201)
      .expect((response) => {
        expect(goalBody(response)).toMatchObject({
          currentAmount: '1000',
          remainingAmount: '0',
          progressPercent: '100',
          status: 'completed',
        });
      });
    await contribute(agent, goal.id, '600', 'golden-600')
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    await contribute(agent, goal.id, '0.000000000001', 'after-completion').expect(409);
    expect(await goalJournalCount(users.premium.id, goal.id)).toBe('2');
  });

  it('serializes concurrent contributions, rejects overfunding, and rolls back rejected journals', async () => {
    const agent = await loggedIn('other');
    const goal = await createGoal(agent, 'Concurrent target', '100');
    const responses = await Promise.all([
      contribute(agent, goal.id, '60', `concurrent-a-${randomUUID()}`),
      contribute(agent, goal.id, '60', `concurrent-b-${randomUUID()}`),
    ]);
    expect(responses.map(({ status }) => status).sort()).toEqual([201, 422]);
    const persisted = await getGoal(agent, goal.id);
    expect(persisted.currentAmount).toBe('60');
    expect(persisted.contributions).toHaveLength(1);
    expect(await goalJournalCount(users.other.id, goal.id)).toBe('1');

    await agent.patch(`/api/v1/goals/${goal.id}`).send({ targetAmount: '59.999' }).expect(422);
    expect((await getGoal(agent, goal.id)).targetAmount).toBe('100');
  });

  it('uses observed exact FX for progress and links only owned goal/category/currency state', async () => {
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'EUR',false,now())`,
      [users.other.id],
    );
    await pool.query(
      `INSERT INTO mymoneymap.fx_quotes
        (id,provider,base_code,quote_code,rate,observed_on,observed_at,fetched_at,quality,status)
       VALUES ($1,'frankfurter','EUR','HUF','400','2026-07-29',
               '2026-07-29T12:00:00Z','2026-07-29T12:01:00Z','provider_observed','available')
       ON CONFLICT DO NOTHING`,
      [randomUUID()],
    );
    const agent = await loggedIn('other');
    const category = await createCategory(agent, 'Goal relationship');
    const goal = await createGoal(agent, 'FX target', '400', {
      categoryId: category.id,
    });
    await contribute(agent, goal.id, '1', `fx-${randomUUID()}`, 'EUR')
      .expect(201)
      .expect((response) => {
        expect(goalBody(response)).toMatchObject({
          currentAmount: '400',
          status: 'completed',
          categoryId: category.id,
        });
        expect(goalBody(response).contributions[0]).toMatchObject({
          amount: '1',
          goalAmount: '400',
        });
      });
  });

  it('corrects by reversal/replacement, reopens below target, and safely retries reversal', async () => {
    const agent = await loggedIn('premium');
    const goal = await createGoal(agent, 'Correction target', '400');
    const completed = await contribute(agent, goal.id, '400', `original-${randomUUID()}`)
      .expect(201)
      .then(goalBody);
    const original = completed.contributions[0]!;

    const corrected = await agent
      .post(`/api/v1/goals/${goal.id}/contributions/${original.id}/corrections`)
      .set('Idempotency-Key', `correction-${randomUUID()}`)
      .send({ amount: '300', currency: 'HUF', occurredOn: '2026-07-29' })
      .expect(201)
      .then(goalBody);
    expect(corrected).toMatchObject({ currentAmount: '300', status: 'active' });
    expect(corrected.contributions).toHaveLength(2);
    expect(corrected.contributions[0]!.reversedByJournalEntryId).not.toBeNull();
    expect(corrected.contributions[1]!.correctsContributionId).toBe(original.id);

    const replacement = corrected.contributions[1]!;
    const reversalKey = `reversal-${randomUUID()}`;
    const reversed = await agent
      .post(`/api/v1/goals/${goal.id}/contributions/${replacement.id}/reversals`)
      .set('Idempotency-Key', reversalKey)
      .send({ postedOn: '2026-07-29', note: 'Synthetic correction reversal' })
      .expect(201)
      .expect('Idempotency-Replayed', 'false')
      .then(goalBody);
    expect(reversed).toMatchObject({ currentAmount: '0', status: 'active' });
    await agent
      .post(`/api/v1/goals/${goal.id}/contributions/${replacement.id}/reversals`)
      .set('Idempotency-Key', reversalKey)
      .send({ postedOn: '2026-07-29', note: 'Synthetic correction reversal' })
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    expect(await goalJournalCount(users.premium.id, goal.id)).toBe('4');
  });

  it('archives and unarchives without income, expense, or hidden balance mutations', async () => {
    const agent = await loggedIn('premium');
    const goal = await createGoal(agent, 'Archive target', '100');
    await contribute(agent, goal.id, '25', `archive-${randomUUID()}`).expect(201);
    const before = await journalSummary(users.premium.id, goal.id);

    await agent
      .post(`/api/v1/goals/${goal.id}/archive`)
      .expect(201)
      .expect((response) => {
        expect(findGoal(response, goal.id).archivedAt).not.toBeNull();
      });
    await contribute(agent, goal.id, '1', `archived-${randomUUID()}`).expect(409);
    await agent
      .post(`/api/v1/goals/${goal.id}/unarchive`)
      .expect(201)
      .expect((response) => {
        expect(findGoal(response, goal.id).archivedAt).toBeNull();
      });
    expect(await journalSummary(users.premium.id, goal.id)).toEqual(before);
  });

  it('creates, replaces, forecasts, and deletes one goal-linked transfer schedule', async () => {
    const agent = await loggedIn('other');
    const goal = await createGoal(agent, 'Scheduled target', '500');
    await agent
      .post(`/api/v1/goals/${goal.id}/recurring-rule`)
      .send({
        title: 'Monthly goal transfer',
        amount: '50.000000000001',
        startsOn: '2026-07-01',
        rrule: 'BYMONTHDAY=1;FREQ=MONTHLY',
      })
      .expect(201)
      .expect((response) => {
        expect(findGoal(response, goal.id).recurringRule).toMatchObject({
          goalId: goal.id,
          amount: '50.000000000001',
          rrule: 'FREQ=MONTHLY;BYMONTHDAY=1',
        });
      });
    await agent
      .post(`/api/v1/goals/${goal.id}/recurring-rule`)
      .send({
        title: 'Duplicate',
        amount: '1',
        startsOn: '2026-07-01',
        rrule: '',
      })
      .expect(409);
    await agent
      .put(`/api/v1/goals/${goal.id}/recurring-rule`)
      .send({
        title: 'Updated goal transfer',
        amount: '75',
        startsOn: '2026-08-01',
        rrule: 'FREQ=MONTHLY;BYMONTHDAY=1',
      })
      .expect(200)
      .expect((response) => {
        expect(findGoal(response, goal.id).recurringRule?.amount).toBe('75');
      });

    const rule = (await getGoal(agent, goal.id)).recurringRule!;
    await agent.patch(`/api/v1/recurring-rules/${rule.id}`).send({ amount: '1' }).expect(404);
    await agent.delete(`/api/v1/goals/${goal.id}/recurring-rule`).expect(204);
    expect((await getGoal(agent, goal.id)).recurringRule).toBeNull();
  });

  it('enforces cross-user isolation, role/access controls, validation, and history deletion policy', async () => {
    const owner = await loggedIn('premium');
    const foreign = await loggedIn('other');
    const goal = await createGoal(owner, 'Owned target', '10');
    await foreign.patch(`/api/v1/goals/${goal.id}`).send({ title: 'Foreign' }).expect(404);
    await contribute(foreign, goal.id, '1', `foreign-${randomUUID()}`).expect(404);
    await foreign.post(`/api/v1/goals/${goal.id}/archive`).expect(404);

    await owner
      .post('/api/v1/goals')
      .send({ title: 'Bad date', targetAmount: '1', currency: 'HUF', deadline: '2026-02-30' })
      .expect(400);
    await owner
      .post('/api/v1/goals')
      .send({ title: 'Zero', targetAmount: '0', currency: 'HUF' })
      .expect(422);
    await contribute(owner, goal.id, '1', `bad-date-${randomUUID()}`, 'HUF', '2026-02-30').expect(
      400,
    );

    const empty = await createGoal(owner, 'Deletable target', '10');
    await owner.delete(`/api/v1/goals/${empty.id}`).expect(204);
    await contribute(owner, goal.id, '1', `history-${randomUUID()}`).expect(201);
    await owner.delete(`/api/v1/goals/${goal.id}`).expect(409);

    await request(app.getHttpServer()).get('/api/v1/goals').expect(401);
    const admin = await loggedIn('admin');
    await admin.get('/api/v1/goals').expect(403);
    await admin
      .post('/api/v1/goals')
      .send({ title: 'Denied', targetAmount: '1', currency: 'HUF' })
      .expect(403);
  });

  it('serializes the free unarchived-goal quota', async () => {
    const agent = await loggedIn('free');
    await createGoal(agent, 'Free existing', '1');
    const payload = { targetAmount: '1', currency: 'HUF' };
    const responses = await Promise.all([
      agent.post('/api/v1/goals').send({ ...payload, title: 'Free concurrent A' }),
      agent.post('/api/v1/goals').send({ ...payload, title: 'Free concurrent B' }),
    ]);
    expect(responses.map(({ status }) => status).sort()).toEqual([201, 403]);
    expect(await goalCount(users.free.id)).toBe('2');
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
       VALUES ($1,$2,$3,'Synthetic Goals User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function createGoal(
    agent: ReturnType<typeof request.agent>,
    title: string,
    targetAmount: string,
    extra: Record<string, unknown> = {},
  ): Promise<GoalResponse> {
    return agent
      .post('/api/v1/goals')
      .send({ title, targetAmount, currency: 'HUF', ...extra })
      .expect(201)
      .then((response) => {
        const goal = responseBody(response).items.find((item) => item.title === title);
        if (!goal) throw new Error(`Goal ${title} was not returned`);
        return goal;
      });
  }

  function contribute(
    agent: ReturnType<typeof request.agent>,
    goalId: string,
    amount: string,
    key: string,
    currency = 'HUF',
    occurredOn = '2026-07-29',
  ): request.Test {
    return agent
      .post(`/api/v1/goals/${goalId}/contributions`)
      .set('Idempotency-Key', key)
      .send({ amount, currency, occurredOn });
  }

  async function createCategory(
    agent: ReturnType<typeof request.agent>,
    label: string,
  ): Promise<{ id: string }> {
    return agent
      .post('/api/v1/categories')
      .send({ label, kind: 'spending', color: '#AABBCC' })
      .expect(201)
      .then((response) => {
        const category = (
          response.body as { items: Array<{ id: string; label: string }> }
        ).items.find((item) => item.label === label);
        if (!category) throw new Error('Synthetic category was not returned');
        return category;
      });
  }

  async function getGoal(
    agent: ReturnType<typeof request.agent>,
    goalId: string,
  ): Promise<GoalResponse> {
    return agent
      .get('/api/v1/goals')
      .expect(200)
      .then((response) => findGoal(response, goalId));
  }

  async function goalJournalCount(userId: string, goalId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        `SELECT count(*)::text AS count
           FROM mymoneymap.journal_entries
          WHERE user_id = $1
            AND source_module = 'goals'
            AND source_reference_id IN (
              SELECT id FROM mymoneymap.goal_contributions WHERE goal_id = $2
            )`,
        [userId, goalId],
      )
    ).rows[0]!.count;
  }

  async function journalSummary(
    userId: string,
    goalId: string,
  ): Promise<{ count: string; income: string; expense: string }> {
    return (
      await pool.query<{ count: string; income: string; expense: string }>(
        `SELECT count(*)::text AS count,
                count(*) FILTER (WHERE economic_type = 'external_income')::text AS income,
                count(*) FILTER (WHERE economic_type IN ('external_expense','fee'))::text AS expense
           FROM mymoneymap.journal_entries
          WHERE user_id = $1
            AND (
              source_reference_id IN (
                SELECT id FROM mymoneymap.goal_contributions WHERE goal_id = $2
              )
              OR source_reference_id = $2
            )`,
        [userId, goalId],
      )
    ).rows[0]!;
  }

  async function goalCount(userId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap.goals WHERE user_id = $1',
        [userId],
      )
    ).rows[0]!.count;
  }
});

function responseBody(response: request.Response): GoalsResponse {
  return response.body as GoalsResponse;
}

function goalBody(response: request.Response): GoalResponse {
  return response.body as GoalResponse;
}

function findGoal(response: request.Response, id: string): GoalResponse {
  const goal = responseBody(response).items.find((item) => item.id === id);
  if (!goal) throw new Error(`Goal ${id} was not returned`);
  return goal;
}
