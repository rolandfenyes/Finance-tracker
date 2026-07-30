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

interface PlanningItem {
  id: string;
  label: string;
  color?: string;
  budgetRuleId?: string | null;
  assignedCategoryIds?: string[];
  plan?: {
    status: string;
    currency: string;
    plannedAmount?: string;
    assignedCategorySpending?: string;
    signedVariance?: string;
  };
}

interface PlanningCollection {
  items: PlanningItem[];
  allocation?: {
    totalPercent: string;
    status: string;
    overAllocatedBy: string;
  };
}

describe('budgeting, categories, and basic income HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-planning-password';
  const users = {
    free: { id: randomUUID(), email: `planning-free-${randomUUID()}@example.test` },
    premium: { id: randomUUID(), email: `planning-premium-${randomUUID()}@example.test` },
    other: { id: randomUUID(), email: `planning-other-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `planning-admin-${randomUUID()}@example.test` },
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

  it('shows over-allocation and exact negative rule variance without equal category caps', async () => {
    const agent = await loggedIn('premium');
    await agent.post('/api/v1/budget-rules').send({ label: 'Needs', percent: '50' }).expect(201);
    await agent
      .post('/api/v1/budget-rules')
      .send({ label: 'Goals', percent: '70' })
      .expect(201)
      .expect((response) => {
        expect(responseBody<PlanningCollection>(response).allocation).toEqual({
          totalPercent: '120',
          status: 'over_allocated',
          overAllocatedBy: '20',
        });
      });
    const rules = await agent.get('/api/v1/budget-rules').expect(200);
    const needs = findByLabel(rules, 'Needs');

    const category = await agent
      .post('/api/v1/categories')
      .send({ label: 'Groceries', kind: 'spending', color: '#facC15' })
      .expect(201)
      .then((response) => findByLabel(response, 'Groceries'));
    expect(category.color).toBe('#FACC15');
    await agent
      .put(`/api/v1/categories/${category.id}/budget-rule`)
      .send({ budgetRuleId: needs.id })
      .expect(200);

    const incomeCategory = await agent
      .post('/api/v1/categories')
      .send({ label: 'Salary', kind: 'income', color: '#0A0' })
      .expect(201)
      .then((response) => findByLabel(response, 'Salary'));
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Salary',
        amount: '1000.000000000001',
        currency: 'HUF',
        validFrom: '2026-07-01',
        categoryId: incomeCategory.id,
      })
      .expect(201);

    await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', `budget-spend-${randomUUID()}`)
      .send({
        economicType: 'external_expense',
        amount: '575',
        currency: 'HUF',
        postedOn: '2026-07-15',
        categoryId: category.id,
      })
      .expect(201);

    await agent
      .get('/api/v1/budget-rules?month=2026-07')
      .expect(200)
      .expect((response) => {
        const plannedNeeds = findByLabel(response, 'Needs');
        expect(plannedNeeds.assignedCategoryIds).toEqual([category.id]);
        expect(plannedNeeds.plan).toEqual({
          status: 'available',
          currency: 'HUF',
          plannedAmount: '500',
          assignedCategorySpending: '575',
          signedVariance: '-75',
        });
        const emptyGoals = findByLabel(response, 'Goals');
        expect(emptyGoals.plan).toMatchObject({
          assignedCategorySpending: '0',
          signedVariance: '700',
        });
        expect(emptyGoals.plan).not.toHaveProperty('categoryCap');
      });
  });

  it('preserves free onboarding initialization but denies later cash-flow editing', async () => {
    const agent = await loggedIn('free');
    await agent
      .put('/api/v1/budget-rules')
      .send({ rules: [{ label: 'Initial needs', percent: '60' }] })
      .expect(200);
    await agent.post('/api/v1/budget-rules').send({ label: 'Denied', percent: '10' }).expect(403);
    await agent
      .put('/api/v1/budget-rules')
      .send({ rules: [{ label: 'Replacement', percent: '50' }] })
      .expect(409);
    const state = await pool.query<{ onboard_step: number }>(
      'SELECT onboard_step FROM mymoneymap.users WHERE id = $1',
      [users.free.id],
    );
    expect(state.rows[0]?.onboard_step).toBe(3);
  });

  it('serializes the free category quota and advances onboarding without partial writes', async () => {
    await pool.query(
      `INSERT INTO mymoneymap.categories
        (id,user_id,label,kind,color,created_at,updated_at)
       SELECT gen_random_uuid(), $1, 'Existing ' || n, 'spending', '#AABBCC', now(), now()
         FROM generate_series(1,9) n`,
      [users.free.id],
    );
    const agent = await loggedIn('free');
    const responses = await Promise.all([
      agent.post('/api/v1/categories').send({
        label: 'Concurrent A',
        kind: 'spending',
        color: '#ABC',
      }),
      agent.post('/api/v1/categories').send({
        label: 'Concurrent B',
        kind: 'spending',
        color: '#DEF',
      }),
    ]);
    expect(responses.map(({ status }) => status).sort()).toEqual([201, 403]);
    const count = await pool.query<{ count: string }>(
      'SELECT count(*)::text AS count FROM mymoneymap.categories WHERE user_id = $1',
      [users.free.id],
    );
    expect(count.rows[0]?.count).toBe('10');
  });

  it('validates category, currency, amount, and date semantics and keeps basic income forecast-only', async () => {
    const agent = await loggedIn('other');
    const income = await createCategory(agent, 'Other salary', 'income');
    const spending = await createCategory(agent, 'Other spending', 'spending');
    await agent
      .post('/api/v1/categories')
      .send({ label: 'Bad color', kind: 'spending', color: 'blue' })
      .expect(400);
    await agent
      .post('/api/v1/budget-rules')
      .send({ label: 'Too much', percent: '100.0001' })
      .expect(400);
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Zero',
        amount: '0',
        currency: 'HUF',
        validFrom: '2026-07-01',
      })
      .expect(422);
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Wrong currency',
        amount: '10',
        currency: 'EUR',
        validFrom: '2026-07-01',
      })
      .expect(422);
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Wrong category',
        amount: '10',
        currency: 'HUF',
        validFrom: '2026-07-01',
        categoryId: spending.id,
      })
      .expect(422);
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Wrong range',
        amount: '10',
        currency: 'HUF',
        validFrom: '2026-07-02',
        validTo: '2026-07-01',
        categoryId: income.id,
      })
      .expect(422);

    const beforeJournal = await journalCount(users.other.id);
    const first = await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Salary history',
        amount: '100',
        currency: 'HUF',
        validFrom: '2026-07-01',
        categoryId: income.id,
      })
      .expect(201);
    expect(await journalCount(users.other.id)).toBe(beforeJournal);
    await agent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Salary history',
        amount: '125',
        currency: 'HUF',
        validFrom: '2026-08-01',
        categoryId: income.id,
      })
      .expect(201)
      .expect((response) => {
        const history = responseBody<PlanningCollection>(response).items.filter(
          (item) => item.label === 'Salary history',
        );
        expect(history).toEqual(
          expect.arrayContaining([
            expect.objectContaining({ amount: '100.000000000000', validTo: '2026-07-31' }),
            expect.objectContaining({ amount: '125.000000000000', validTo: null }),
          ]),
        );
      });
    const firstId = findByLabel(first, 'Salary history').id;
    await agent.patch(`/api/v1/basic-incomes/${firstId}`).send({ amount: '110' }).expect(200);
  });

  it('enforces ownership, reference deletion, rule assignment, and ledger category kind', async () => {
    const owner = await loggedIn('other');
    const foreign = await loggedIn('premium');
    const income = await createCategory(owner, 'Referenced income', 'income');
    const spending = await createCategory(owner, 'Owned expense', 'spending');
    const rule = await owner
      .post('/api/v1/budget-rules')
      .send({ label: 'Owner rule', percent: '25' })
      .expect(201)
      .then((response) => findByLabel(response, 'Owner rule'));
    await foreign.patch(`/api/v1/categories/${spending.id}`).send({ label: 'Nope' }).expect(404);
    await foreign
      .put(`/api/v1/categories/${spending.id}/budget-rule`)
      .send({ budgetRuleId: rule.id })
      .expect(404);
    await owner
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Referenced',
        amount: '10',
        currency: 'HUF',
        validFrom: '2026-09-01',
        categoryId: income.id,
      })
      .expect(201);
    await owner.delete(`/api/v1/categories/${income.id}`).expect(409);
    await owner
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', `wrong-kind-${randomUUID()}`)
      .send({
        economicType: 'external_expense',
        amount: '1',
        currency: 'HUF',
        postedOn: '2026-07-20',
        categoryId: income.id,
      })
      .expect(422);
    await owner
      .put(`/api/v1/categories/${spending.id}/budget-rule`)
      .send({ budgetRuleId: rule.id })
      .expect(200);
    await owner.delete(`/api/v1/budget-rules/${rule.id}`).expect(204);
    await owner
      .get('/api/v1/categories')
      .expect(200)
      .expect((response) => {
        expect(
          responseBody<PlanningCollection>(response).items.find((item) => item.id === spending.id),
        ).toMatchObject({ budgetRuleId: null });
      });
  });

  it('keeps reads side-effect free and protects every route by authentication and role', async () => {
    const before = await planningCounts(users.premium.id);
    const premium = await loggedIn('premium');
    await premium.get('/api/v1/categories').expect(200);
    await premium.get('/api/v1/basic-incomes').expect(200);
    await premium.get('/api/v1/budget-rules?month=2026-07').expect(200);
    expect(await planningCounts(users.premium.id)).toEqual(before);

    await request(app.getHttpServer()).get('/api/v1/categories').expect(401);
    await request(app.getHttpServer()).get('/api/v1/basic-incomes').expect(401);
    await request(app.getHttpServer()).get('/api/v1/budget-rules').expect(401);
    const admin = await loggedIn('admin');
    await admin.get('/api/v1/categories').expect(403);
    await admin.get('/api/v1/basic-incomes').expect(403);
    await admin.get('/api/v1/budget-rules').expect(403);
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
       VALUES ($1,$2,$3,'Synthetic Planning User','1990-01-01',$4,now(),now(),now())`,
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
      .then((response) => findByLabel(response, label));
  }

  async function journalCount(userId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        'SELECT count(*)::text AS count FROM mymoneymap.journal_entries WHERE user_id = $1',
        [userId],
      )
    ).rows[0]!.count;
  }

  async function planningCounts(userId: string): Promise<Record<string, string>> {
    const result = await pool.query<Record<string, string>>(
      `SELECT
         (SELECT count(*)::text FROM mymoneymap.budget_rules WHERE user_id = $1) AS rules,
         (SELECT count(*)::text FROM mymoneymap.categories WHERE user_id = $1) AS categories,
         (SELECT count(*)::text FROM mymoneymap.basic_incomes WHERE user_id = $1) AS incomes,
         (SELECT count(*)::text FROM mymoneymap.journal_entries WHERE user_id = $1) AS journal`,
      [userId],
    );
    return result.rows[0]!;
  }
});

function responseBody<T>(response: { body: unknown }): T {
  return response.body as T;
}

function findByLabel(response: { body: unknown }, label: string): PlanningItem {
  const item = responseBody<PlanningCollection>(response).items.find(
    (candidate) => candidate.label === label,
  );
  if (!item) throw new Error(`Synthetic response item was not found: ${label}`);
  return item;
}
