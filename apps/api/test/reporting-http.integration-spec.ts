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

jest.setTimeout(45_000);

interface ReportResponse {
  period: { first: string; last: string; year: number; month?: number; timeZone: string };
  posted: Summary;
  forecast: { summary: Summary; sources: Array<{ sourceEntryId: string }> };
  combinedProjection: Summary;
  budget: {
    items: Array<{
      label: string;
      plan: {
        plannedAmount: string;
        assignedCategorySpending: string;
        signedVariance: string;
      } | null;
    }>;
  };
  activity: {
    items: Array<{
      sourceEntryId: string;
      source: { module: string; referenceId: string | null };
    }>;
    nextCursor: string | null;
  };
}

interface Summary {
  income: string;
  expense: string;
  transfer: string;
  adjustmentNet: string;
  tradeCashNet: string;
  netCashFlow: string;
  conversion: {
    status: string;
    complete: boolean;
    includedSourceCount: number;
    unavailableSourceCount: number;
  };
}

describe('reporting HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-reporting-password';
  const users = {
    owner: { id: randomUUID(), email: `report-owner-${randomUUID()}@example.test` },
    other: { id: randomUUID(), email: `report-other-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `report-admin-${randomUUID()}@example.test` },
  };
  let ownerAgent: ReturnType<typeof request.agent>;

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
      insertUser(users.owner.id, users.owner.email, 'premium'),
      insertUser(users.other.id, users.other.email, 'premium'),
      insertUser(users.admin.id, users.admin.email, 'admin'),
    ]);
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'USD',false,now())`,
      [users.owner.id],
    );
    ownerAgent = await loggedIn('owner');
    await seedOwnerSources();
    const other = await loggedIn('other');
    await post(other, 'external_income', '999', '2026-07-15');
  });

  afterAll(async () => {
    await app.close();
  });

  it('reconciles posted, forecast, combined, transfer, budget, and unavailable-FX facts', async () => {
    await ownerAgent
      .get('/api/v1/reports/months/2026/7?limit=2')
      .expect(200)
      .expect((response) => {
        const report = response.body as ReportResponse;
        expect(report.period).toEqual({
          first: '2026-07-01',
          last: '2026-07-31',
          year: 2026,
          month: 7,
          timeZone: 'Europe/Budapest',
        });
        expect(report.posted).toMatchObject({
          income: '100',
          expense: '40',
          transfer: '25',
          adjustmentNet: '0',
          tradeCashNet: '0',
          netCashFlow: '60',
          conversion: {
            status: 'unavailable',
            complete: false,
            includedSourceCount: 5,
            unavailableSourceCount: 1,
          },
        });
        expect(report.forecast.summary).toMatchObject({
          income: '50',
          expense: '10',
          transfer: '5',
          netCashFlow: '40',
        });
        expect(report.combinedProjection).toMatchObject({
          income: '150',
          expense: '50',
          transfer: '30',
          netCashFlow: '100',
        });
        expect(report.forecast.sources).toHaveLength(3);
        expect(
          report.forecast.sources.every(({ sourceEntryId }) => sourceEntryId.includes(':')),
        ).toBe(true);
        expect(report.activity.items).toHaveLength(2);
        expect(report.activity.items).toEqual(
          expect.arrayContaining([
            expect.objectContaining({
              source: { module: 'manual', referenceId: null },
            }),
          ]),
        );
        expect(report.activity.nextCursor).not.toBeNull();
        const budget = report.budget.items.find(({ label }) => label === 'Needs');
        expect(budget?.plan).toMatchObject({
          plannedAmount: '25',
          assignedCategorySpending: '40',
          signedVariance: '-15',
        });
      });
  });

  it('keeps filtered totals invariant across cursor pages and isolates every source by owner', async () => {
    const first = await ownerAgent
      .get('/api/v1/reports/months/2026/7?kind=income&limit=1')
      .expect(200);
    const firstBody = first.body as ReportResponse;
    expect(firstBody.posted.income).toBe('100');
    expect(firstBody.posted.expense).toBe('0');
    expect(firstBody.forecast.summary.income).toBe('50');
    expect(firstBody.activity.items).toHaveLength(1);
    expect(firstBody.activity.nextCursor).not.toBeNull();

    const second = await ownerAgent
      .get(
        `/api/v1/reports/months/2026/7?kind=income&limit=1&cursor=${encodeURIComponent(
          firstBody.activity.nextCursor!,
        )}`,
      )
      .expect(200);
    const secondBody = second.body as ReportResponse;
    expect(secondBody.posted).toEqual(firstBody.posted);
    expect(secondBody.forecast.summary).toEqual(firstBody.forecast.summary);
    expect(JSON.stringify(second.body)).not.toContain('999.000000000000');
  });

  it('returns month/year aggregates, applies the Budapest current-date contract, and performs no writes', async () => {
    const before = await sourceCounts();
    const [year, current, retryLeft, retryRight] = await Promise.all([
      ownerAgent.get('/api/v1/reports/years/2026').expect(200),
      ownerAgent.get('/api/v1/reports/months/current').expect(200),
      ownerAgent.get('/api/v1/reports/months/2026/7').expect(200),
      ownerAgent.get('/api/v1/reports/months/2026/7').expect(200),
    ]);
    expect(year.body.months).toHaveLength(12);
    expect(year.body.posted).toMatchObject({ income: '100', expense: '40', netCashFlow: '60' });
    expect(year.body.forecast).toMatchObject({ income: '50', expense: '10', netCashFlow: '40' });
    expect(current.body.period.timeZone).toBe('Europe/Budapest');
    expect(retryLeft.body).toEqual(retryRight.body);
    expect(await sourceCounts()).toEqual(before);

    await ownerAgent
      .get('/api/v1/reports/years')
      .expect(200)
      .expect((response) => {
        expect(response.body.items).toEqual(expect.arrayContaining([{ year: 2026 }]));
      });
  });

  it('validates periods, filters, exact ranges, and cursors', async () => {
    await ownerAgent.get('/api/v1/reports/months/2026/13').expect(400);
    await ownerAgent.get('/api/v1/reports/years/0').expect(400);
    await ownerAgent.get('/api/v1/reports/months/2026/7?kind=wealth').expect(400);
    await ownerAgent.get('/api/v1/reports/months/2026/7?currency=usd').expect(400);
    await ownerAgent.get('/api/v1/reports/months/2026/7?minAmount=20&maxAmount=10').expect(422);
    await ownerAgent.get('/api/v1/reports/months/2026/7?cursor=not-a-cursor').expect(400);
  });

  it('protects all reporting routes by authentication and personal-finance access', async () => {
    await request(app.getHttpServer()).get('/api/v1/reports/months/current').expect(401);
    await request(app.getHttpServer()).get('/api/v1/reports/years').expect(401);
    const admin = await loggedIn('admin');
    await admin.get('/api/v1/reports/months/current').expect(403);
    await admin.get('/api/v1/reports/years/2026').expect(403);
  });

  async function seedOwnerSources(): Promise<void> {
    const incomeCategory = await createCategory('Salary', 'income');
    const spendingCategory = await createCategory('Groceries', 'spending');
    const rule = await ownerAgent
      .post('/api/v1/budget-rules')
      .send({ label: 'Needs', percent: '50' })
      .expect(201)
      .then((response) =>
        (response.body.items as Array<{ id: string; label: string }>).find(
          ({ label }) => label === 'Needs',
        ),
      );
    if (!rule) throw new Error('Synthetic budget rule was not returned');
    await ownerAgent
      .put(`/api/v1/categories/${spendingCategory}/budget-rule`)
      .send({ budgetRuleId: rule.id })
      .expect(200);
    await ownerAgent
      .post('/api/v1/basic-incomes')
      .send({
        label: 'Synthetic salary forecast',
        amount: '50',
        currency: 'HUF',
        validFrom: '2026-07-01',
        validTo: '2026-07-31',
        categoryId: incomeCategory,
      })
      .expect(201);
    await ownerAgent
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Synthetic recurring expense',
        amount: '10',
        currency: 'HUF',
        economicType: 'expense',
        startsOn: '2026-07-10',
        rrule: '',
        categoryId: spendingCategory,
      })
      .expect(201);
    await ownerAgent
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Synthetic recurring transfer',
        amount: '5',
        currency: 'HUF',
        economicType: 'transfer',
        startsOn: '2026-07-20',
        rrule: '',
      })
      .expect(201);
    await post(ownerAgent, 'external_income', '100', '2026-07-05', {
      categoryId: incomeCategory,
    });
    const reversed = await post(ownerAgent, 'external_income', '7', '2026-07-05', {
      categoryId: incomeCategory,
    });
    await ownerAgent
      .post(`/api/v1/journal/entries/${reversed.id}/reversals`)
      .set('Idempotency-Key', randomUUID())
      .send({ postedOn: '2026-07-05' })
      .expect(201);
    await post(ownerAgent, 'external_expense', '40', '2026-07-06', {
      categoryId: spendingCategory,
    });
    const source = await createAccount('investment');
    const destination = await createAccount('goal');
    await post(ownerAgent, 'internal_transfer', '25', '2026-07-07', {
      sourceAccountId: source,
      destinationAccountId: destination,
    });
    await post(ownerAgent, 'external_income', '11', '2026-07-08', { currency: 'USD' });
  }

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
       VALUES ($1,$2,$3,'Synthetic Reporting User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function createCategory(label: string, kind: 'income' | 'spending'): Promise<string> {
    return ownerAgent
      .post('/api/v1/categories')
      .send({ label, kind, color: '#AABBCC' })
      .expect(201)
      .then((response) => {
        const item = (response.body.items as Array<{ id: string; label: string }>).find(
          (candidate) => candidate.label === label,
        );
        if (!item) throw new Error(`Synthetic category ${label} was not returned`);
        return item.id;
      });
  }

  async function createAccount(kind: 'investment' | 'goal'): Promise<string> {
    const id = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.ledger_accounts
        (id,user_id,kind,module_reference_id,created_at)
       VALUES ($1,$2,$3,$4,now())`,
      [id, users.owner.id, kind, randomUUID()],
    );
    return id;
  }

  async function post(
    agent: ReturnType<typeof request.agent>,
    economicType: string,
    amount: string,
    postedOn: string,
    extra: Record<string, unknown> = {},
  ): Promise<{ id: string }> {
    return agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', randomUUID())
      .send({ economicType, amount, currency: 'HUF', postedOn, ...extra })
      .expect(201)
      .then((response) => response.body as { id: string });
  }

  async function sourceCounts(): Promise<Record<string, string>> {
    const result = await pool.query<Record<string, string>>(
      `SELECT
         (SELECT count(*)::text FROM mymoneymap.journal_entries WHERE user_id = $1) AS entries,
         (SELECT count(*)::text FROM mymoneymap.fx_conversion_snapshots WHERE user_id = $1) AS snapshots,
         (SELECT count(*)::text FROM mymoneymap.recurring_occurrences WHERE user_id = $1) AS occurrences,
         (SELECT count(*)::text FROM mymoneymap.fx_quotes) AS quotes`,
      [users.owner.id],
    );
    return result.rows[0]!;
  }
});
