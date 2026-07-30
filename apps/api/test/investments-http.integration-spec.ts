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

interface InvestmentResponse {
  id: string;
  type: 'savings' | 'etf' | 'stock';
  currency: string;
  balance: string;
  accountId: string;
  scenario: {
    enabled: boolean;
    label: string;
    guaranteed: false;
    expectedReturn: false;
    affectsPostedBalance: false;
    milestones: Array<{ horizonYears: string; value: string }>;
  };
  recurringContributionForecast: {
    occurrences: string[];
    investmentCurrencyContributionTotal: string | null;
    conversionStatus: string;
  } | null;
  movements: Array<{
    id: string;
    journalEntryId: string;
    direction: 'deposit' | 'withdrawal';
    investmentAmount: string;
    reversedByJournalEntryId: string | null;
  }>;
}

describe('generic investments HTTP, ledger, and forecast contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-investment-password';
  const owner = { id: randomUUID(), email: `investment-owner-${randomUUID()}@example.test` };
  const other = { id: randomUUID(), email: `investment-other-${randomUUID()}@example.test` };
  const admin = { id: randomUUID(), email: `investment-admin-${randomUUID()}@example.test` };

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
      insertUser(owner.id, owner.email, 'premium'),
      insertUser(other.id, other.email, 'premium'),
      insertUser(admin.id, admin.email, 'admin'),
    ]);
  });

  afterAll(async () => app.close());

  it('preserves supported types and exposes only explicitly labeled user-authored scenarios', async () => {
    const agent = await loggedIn(owner.email);
    for (const type of ['savings', 'etf', 'stock'] as const) {
      await create(agent, type, type === 'savings' ? '0' : '5').expect(201);
    }
    const items = await list(agent);
    expect(items.map(({ type }) => type).sort()).toEqual(['etf', 'savings', 'stock']);
    for (const investment of items) {
      expect(investment.balance).toBe('0');
      expect(investment.scenario).toMatchObject({
        enabled: true,
        label: 'User-authored nominal compound return scenario',
        guaranteed: false,
        expectedReturn: false,
        affectsPostedBalance: false,
      });
      expect(JSON.stringify(investment.scenario)).not.toMatch(
        /expected return|guaranteed interest/i,
      );
    }
    await agent
      .post('/api/v1/investments')
      .send({
        type: 'savings',
        name: 'Rejected negative scenario',
        currency: 'HUF',
        scenarioAnnualRate: '-1',
      })
      .expect(400);
  });

  it('updates metadata without mutating balance and deletes only history-free investments', async () => {
    const agent = await loggedIn(owner.email);
    const disposable = await create(agent, 'savings', null).expect(201).then(first);
    await agent
      .patch(`/api/v1/investments/${disposable.id}`)
      .send({
        name: 'Updated synthetic investment',
        scenarioAnnualRate: '0',
        scenarioFrequency: 'annual',
      })
      .expect(200)
      .expect((response) => {
        const updated = (response.body as { items: InvestmentResponse[] }).items.find(
          ({ id }) => id === disposable.id,
        )!;
        expect(updated.balance).toBe('0');
        expect(updated.scenario).toMatchObject({ enabled: true, affectsPostedBalance: false });
      });
    await agent.delete(`/api/v1/investments/${disposable.id}`).expect(204);
    expect((await list(agent)).some(({ id }) => id === disposable.id)).toBe(false);

    const withHistory = (await list(agent))[0]!;
    await movement(agent, withHistory.id, 'deposit', '1', `delete-history-${randomUUID()}`).expect(
      201,
    );
    await agent.delete(`/api/v1/investments/${withHistory.id}`).expect(409);
  });

  it('posts retry-safe transfers, rejects concurrent overdrafts, and reconciles the ledger', async () => {
    const agent = await loggedIn(other.email);
    const investment = await create(agent, 'savings', null).expect(201).then(first);
    const key = `investment-deposit-${randomUUID()}`;
    await movement(agent, investment.id, 'deposit', '100.000000000001', key)
      .expect(201)
      .expect('Idempotency-Replayed', 'false');
    await movement(agent, investment.id, 'deposit', '100.000000000001', key)
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    const competing = await Promise.all([
      movement(agent, investment.id, 'withdrawal', '60', `withdraw-a-${randomUUID()}`),
      movement(agent, investment.id, 'withdrawal', '60', `withdraw-b-${randomUUID()}`),
    ]);
    expect(competing.map(({ status }) => status).sort()).toEqual([201, 422]);
    const current = await byId(agent, investment.id);
    expect(current.balance).toBe('40');

    const journals = await pool.query<{ total: string; transfers: string; income: string }>(
      `SELECT count(*)::text total,
              count(*) FILTER (WHERE economic_type='internal_transfer')::text transfers,
              count(*) FILTER (WHERE economic_type IN ('external_income','external_expense'))::text income
         FROM mymoneymap.journal_entries
        WHERE user_id=$1 AND source_module='investments'`,
      [other.id],
    );
    expect(journals.rows[0]).toEqual({ total: '2', transfers: '2', income: '0' });
    const legs = await pool.query<{ amount: string }>(
      `SELECT COALESCE(sum(CASE side WHEN 'debit' THEN amount ELSE -amount END),0)::text amount
         FROM mymoneymap.journal_legs jl
         JOIN mymoneymap.journal_entries je ON je.id=jl.entry_id
        WHERE je.user_id=$1 AND je.source_module='investments'`,
      [other.id],
    );
    expect(legs.rows[0]!.amount).toBe('0.000000000000');
  });

  it('reverses movements idempotently without deleting posted history', async () => {
    const agent = await loggedIn(other.email);
    const investment = (await list(agent)).find(({ type }) => type === 'savings')!;
    const withdrawal = investment.movements.find(({ direction }) => direction === 'withdrawal')!;
    const key = `reverse-investment-${randomUUID()}`;
    await reverse(agent, investment.id, withdrawal.id, key)
      .expect(201)
      .expect('Idempotency-Replayed', 'false');
    await reverse(agent, investment.id, withdrawal.id, key)
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    expect((await byId(agent, investment.id)).balance).toBe('100');
    expect(
      (await byId(agent, investment.id)).movements.find(({ id }) => id === withdrawal.id)!
        .reversedByJournalEntryId,
    ).not.toBeNull();
  });

  it('returns recurring contributions as forecasts and never accrues scenario value into balance', async () => {
    const agent = await loggedIn(owner.email);
    const investment = (await list(agent)).find(({ type }) => type === 'etf')!;
    await movement(agent, investment.id, 'deposit', '1000', `scenario-seed-${randomUUID()}`).expect(
      201,
    );
    await agent
      .post(`/api/v1/investments/${investment.id}/recurring-rule`)
      .send({
        title: 'Monthly synthetic contribution',
        amount: '100',
        currency: 'HUF',
        startsOn: '2026-08-01',
        rrule: 'FREQ=MONTHLY;BYMONTHDAY=1;COUNT=3',
      })
      .expect(201);
    const before = await counts(owner.id);
    const current = await byId(agent, investment.id);
    expect(current.balance).toBe('1000');
    expect(current.recurringContributionForecast).toMatchObject({
      investmentCurrencyContributionTotal: '300',
      conversionStatus: 'same_currency',
    });
    expect(current.recurringContributionForecast!.occurrences).toEqual([
      '2026-08-01',
      '2026-09-01',
      '2026-10-01',
    ]);
    expect(current.scenario.milestones[0]!.value).not.toBe(current.balance);
    expect(await counts(owner.id)).toEqual(before);
  });

  it('fails closed on unavailable FX and enforces isolation, authentication, and admin denial', async () => {
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'USD',false,now()) ON CONFLICT DO NOTHING`,
      [other.id],
    );
    const ownerAgent = await loggedIn(owner.email);
    const otherAgent = await loggedIn(other.email);
    const ownerInvestment = (await list(ownerAgent))[0]!;
    const before = await counts(other.id);
    await movement(
      otherAgent,
      (await list(otherAgent))[0]!.id,
      'deposit',
      '1',
      `missing-fx-${randomUUID()}`,
      'USD',
    ).expect(422);
    expect(await counts(other.id)).toEqual(before);
    await movement(
      otherAgent,
      ownerInvestment.id,
      'deposit',
      '1',
      `foreign-${randomUUID()}`,
    ).expect(404);
    await request(app.getHttpServer()).get('/api/v1/investments').expect(401);
    await (await loggedIn(admin.email)).get('/api/v1/investments').expect(403);
  });

  async function insertUser(id: string, email: string, role: 'premium' | 'admin'): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
        (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Investment User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function loggedIn(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  function create(
    agent: ReturnType<typeof request.agent>,
    type: 'savings' | 'etf' | 'stock',
    rate: string | null,
  ): request.Test {
    return agent.post('/api/v1/investments').send({
      type,
      name: `Synthetic ${type} ${randomUUID()}`,
      currency: 'HUF',
      scenarioAnnualRate: rate,
      scenarioFrequency: 'monthly',
    });
  }

  function movement(
    agent: ReturnType<typeof request.agent>,
    investmentId: string,
    direction: 'deposit' | 'withdrawal',
    amount: string,
    key: string,
    currency = 'HUF',
  ): request.Test {
    return agent
      .post(`/api/v1/investments/${investmentId}/movements`)
      .set('Idempotency-Key', key)
      .send({ direction, amount, currency, occurredOn: '2026-07-30' });
  }

  function reverse(
    agent: ReturnType<typeof request.agent>,
    investmentId: string,
    movementId: string,
    key: string,
  ): request.Test {
    return agent
      .post(`/api/v1/investments/${investmentId}/movements/${movementId}/reversals`)
      .set('Idempotency-Key', key)
      .send({ postedOn: '2026-07-30' });
  }

  async function list(agent: ReturnType<typeof request.agent>): Promise<InvestmentResponse[]> {
    return agent
      .get('/api/v1/investments')
      .expect(200)
      .then((response) => (response.body as { items: InvestmentResponse[] }).items);
  }

  async function byId(
    agent: ReturnType<typeof request.agent>,
    investmentId: string,
  ): Promise<InvestmentResponse> {
    return (await list(agent)).find(({ id }) => id === investmentId)!;
  }

  async function counts(userId: string): Promise<{ journals: string; movements: string }> {
    return (
      await pool.query<{ journals: string; movements: string }>(
        `SELECT
          (SELECT count(*)::text FROM mymoneymap.journal_entries
            WHERE user_id=$1 AND source_module='investments') journals,
          (SELECT count(*)::text FROM mymoneymap.investment_movements
            WHERE user_id=$1) movements`,
        [userId],
      )
    ).rows[0]!;
  }
});

function first(response: request.Response): InvestmentResponse {
  return (response.body as { items: InvestmentResponse[] }).items[0]!;
}
