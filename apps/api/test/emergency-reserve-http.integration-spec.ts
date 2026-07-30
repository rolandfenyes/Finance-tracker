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

interface ReserveResponse {
  configured: boolean;
  targetAmount: string;
  currentAmount: string;
  currency: string;
  reserveAccountId: string | null;
  linkedInvestmentAccountId: string | null;
  targetMethodology: { code: string; educationalOnly: boolean };
  scheduledActivity: {
    classification: string;
    label: string;
    periodFrom: string;
    periodTo: string;
    totals: Array<{ currency: string; income: string; expense: string; transfer: string }>;
  };
  movements: Array<{
    id: string;
    journalEntryId: string;
    holdingAccountId: string;
    direction: 'contribution' | 'withdrawal';
    reserveAmount: string;
    reversedByJournalEntryId: string | null;
  }>;
}

describe('emergency reserve HTTP, ledger, and PostgreSQL contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-emergency-password';
  const owner = {
    id: randomUUID(),
    email: `emergency-owner-${randomUUID()}@example.test`,
  };
  const other = {
    id: randomUUID(),
    email: `emergency-other-${randomUUID()}@example.test`,
  };
  const admin = {
    id: randomUUID(),
    email: `emergency-admin-${randomUUID()}@example.test`,
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
      insertUser(owner.id, owner.email, 'premium'),
      insertUser(other.id, other.email, 'premium'),
      insertUser(admin.id, admin.email, 'admin'),
    ]);
  });

  afterAll(async () => app.close());

  it('posts contribution and withdrawal as retry-safe transfers with zero cash-flow effect', async () => {
    const agent = await loggedIn(owner.email);
    await agent
      .put('/api/v1/emergency-reserve/target')
      .send({ targetAmount: '1000.000000000001', currency: 'HUF' })
      .expect(200);

    const contributionKey = `reserve-contribution-${randomUUID()}`;
    await movement(agent, 'contributions', '250.000000000001', contributionKey)
      .expect(201)
      .expect('Idempotency-Replayed', 'false')
      .expect((response) => {
        expect(body(response)).toMatchObject({
          currentAmount: '250',
          targetAmount: '1000.000000000001',
        });
      });
    await movement(agent, 'contributions', '250.000000000001', contributionKey)
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    await movement(agent, 'withdrawals', '75', `reserve-withdrawal-${randomUUID()}`)
      .expect(201)
      .expect((response) => expect(body(response).currentAmount).toBe('175'));

    const summary = await pool.query<{
      total: string;
      transfers: string;
      income: string;
      expense: string;
    }>(
      `SELECT count(*)::text total,
              count(*) FILTER (WHERE economic_type='internal_transfer')::text transfers,
              count(*) FILTER (WHERE economic_type='external_income')::text income,
              count(*) FILTER (WHERE economic_type IN ('external_expense','fee'))::text expense
         FROM mymoneymap.journal_entries
        WHERE user_id=$1 AND source_module='emergency_fund'`,
      [owner.id],
    );
    expect(summary.rows[0]).toEqual({
      total: '2',
      transfers: '2',
      income: '0',
      expense: '0',
    });
  });

  it('serializes withdrawals, rejects insufficient allocation, and rolls back rejected journals', async () => {
    const agent = await loggedIn(other.email);
    await movement(agent, 'contributions', '100', `seed-${randomUUID()}`).expect(201);
    const responses = await Promise.all([
      movement(agent, 'withdrawals', '60', `withdraw-a-${randomUUID()}`),
      movement(agent, 'withdrawals', '60', `withdraw-b-${randomUUID()}`),
    ]);
    expect(responses.map(({ status }) => status).sort()).toEqual([201, 422]);
    expect((await read(agent)).currentAmount).toBe('40');
    expect(await movementCount(other.id)).toBe('2');
  });

  it('reverses atomically, safely retries, and prevents a negative historical reversal', async () => {
    const agent = await loggedIn(other.email);
    const reserve = await read(agent);
    const withdrawal = reserve.movements.find(({ direction }) => direction === 'withdrawal')!;
    const key = `reverse-withdrawal-${randomUUID()}`;
    await agent
      .post(`/api/v1/emergency-reserve/movements/${withdrawal.id}/reversals`)
      .set('Idempotency-Key', key)
      .send({ postedOn: '2026-07-29' })
      .expect(201)
      .expect('Idempotency-Replayed', 'false');
    await agent
      .post(`/api/v1/emergency-reserve/movements/${withdrawal.id}/reversals`)
      .set('Idempotency-Key', key)
      .send({ postedOn: '2026-07-29' })
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    expect((await read(agent)).currentAmount).toBe('100');

    await movement(agent, 'withdrawals', '80', `post-reversal-${randomUUID()}`).expect(201);
    const contribution = (await read(agent)).movements.find(
      ({ direction, reversedByJournalEntryId }) =>
        direction === 'contribution' && reversedByJournalEntryId === null,
    )!;
    await agent
      .post(`/api/v1/emergency-reserve/movements/${contribution.id}/reversals`)
      .set('Idempotency-Key', `invalid-reversal-${randomUUID()}`)
      .send({ postedOn: '2026-07-29' })
      .expect(409);
  });

  it('uses observed FX and one linked-investment posting without duplicate economic rows', async () => {
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'EUR',false,now()) ON CONFLICT DO NOTHING`,
      [owner.id],
    );
    await pool.query(
      `INSERT INTO mymoneymap.fx_quotes
        (id,provider,base_code,quote_code,rate,observed_on,observed_at,fetched_at,quality,status)
       VALUES ($1,'frankfurter','EUR','HUF','400','2026-07-29',
               '2026-07-29T12:00:00Z','2026-07-29T12:01:00Z','provider_observed','available')
       ON CONFLICT DO NOTHING`,
      [randomUUID()],
    );
    const investmentAccountId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.ledger_accounts
        (id,user_id,kind,module_reference_id,created_at)
       VALUES ($1,$2,'investment',$3,now())`,
      [investmentAccountId, owner.id, randomUUID()],
    );
    const agent = await loggedIn(owner.email);
    const emptyOwnerId = randomUUID();
    const emptyOwnerEmail = `emergency-linked-${randomUUID()}@example.test`;
    await insertUser(emptyOwnerId, emptyOwnerEmail, 'premium');
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'EUR',false,now())`,
      [emptyOwnerId],
    );
    const linkedAccountId = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.ledger_accounts
        (id,user_id,kind,module_reference_id,created_at)
       VALUES ($1,$2,'investment',$3,now())`,
      [linkedAccountId, emptyOwnerId, randomUUID()],
    );
    const linkedAgent = await loggedIn(emptyOwnerEmail);
    await linkedAgent
      .put('/api/v1/emergency-reserve/target')
      .send({
        targetAmount: '1000',
        currency: 'HUF',
        linkedInvestmentAccountId: linkedAccountId,
      })
      .expect(200);
    const linked = await movement(
      linkedAgent,
      'contributions',
      '1',
      `linked-${randomUUID()}`,
      'EUR',
    )
      .expect(201)
      .then(body);
    expect(linked.currentAmount).toBe('400');
    expect(linked.movements[0]!.holdingAccountId).toBe(linkedAccountId);
    expect(await journalCount(emptyOwnerId)).toBe('1');

    await agent
      .put('/api/v1/emergency-reserve/target')
      .send({
        targetAmount: '1000',
        currency: 'HUF',
        linkedInvestmentAccountId: linkedAccountId,
      })
      .expect(404);
    expect(investmentAccountId).not.toBe(linkedAccountId);
  });

  it('fails closed and rolls back when observed FX is unavailable', async () => {
    await pool.query(
      `INSERT INTO mymoneymap.user_currencies (user_id,code,is_main,created_at)
       VALUES ($1,'USD',false,now()) ON CONFLICT DO NOTHING`,
      [other.id],
    );
    const agent = await loggedIn(other.email);
    const before = await writeCounts(other.id);
    await movement(agent, 'contributions', '1', `missing-fx-${randomUUID()}`, 'USD').expect(422);
    expect(await writeCounts(other.id)).toEqual(before);
  });

  it('returns only neutral manual target and raw schedule data, and GET performs no writes', async () => {
    const agent = await loggedIn(owner.email);
    await agent
      .post('/api/v1/recurring-rules')
      .send({
        title: 'Synthetic scheduled expense',
        amount: '10',
        currency: 'HUF',
        economicType: 'expense',
        startsOn: '2026-08-01',
        rrule: 'FREQ=MONTHLY;BYMONTHDAY=1',
      })
      .expect(201);
    const before = await writeCounts(owner.id);
    const reserve = await read(agent);
    expect(reserve.targetMethodology).toEqual({
      code: 'manual_user_defined',
      label: 'User-defined reserve target',
      educationalOnly: true,
    });
    expect(reserve.scheduledActivity).toMatchObject({
      classification: 'raw_unclassified_scheduled_activity',
      label: 'Raw scheduled activity totals',
    });
    expect(JSON.stringify(reserve)).not.toMatch(/safe|should invest|needs/i);
    expect(await writeCounts(owner.id)).toEqual(before);
  });

  it('enforces validation, cross-user isolation, authentication, and admin denial', async () => {
    const ownerAgent = await loggedIn(owner.email);
    const otherAgent = await loggedIn(other.email);
    const movementId = (await read(ownerAgent)).movements[0]!.id;
    await otherAgent
      .post(`/api/v1/emergency-reserve/movements/${movementId}/reversals`)
      .set('Idempotency-Key', `foreign-${randomUUID()}`)
      .send({ postedOn: '2026-07-29' })
      .expect(404);
    await ownerAgent
      .post('/api/v1/emergency-reserve/contributions')
      .set('Idempotency-Key', `invalid-${randomUUID()}`)
      .send({ amount: '0', currency: 'HUF', occurredOn: '2026-07-29' })
      .expect(422);
    await ownerAgent
      .post('/api/v1/emergency-reserve/contributions')
      .set('Idempotency-Key', `invalid-date-${randomUUID()}`)
      .send({ amount: '1', currency: 'HUF', occurredOn: '2026-02-30' })
      .expect(400);
    await request(app.getHttpServer()).get('/api/v1/emergency-reserve').expect(401);
    await (await loggedIn(admin.email)).get('/api/v1/emergency-reserve').expect(403);
  });

  async function insertUser(id: string, email: string, role: 'premium' | 'admin'): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
        (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Emergency User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function loggedIn(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  function movement(
    agent: ReturnType<typeof request.agent>,
    endpoint: 'contributions' | 'withdrawals',
    amount: string,
    key: string,
    currency = 'HUF',
  ): request.Test {
    return agent
      .post(`/api/v1/emergency-reserve/${endpoint}`)
      .set('Idempotency-Key', key)
      .send({ amount, currency, occurredOn: '2026-07-29' });
  }

  async function read(agent: ReturnType<typeof request.agent>): Promise<ReserveResponse> {
    return agent.get('/api/v1/emergency-reserve').expect(200).then(body);
  }

  async function movementCount(userId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        'SELECT count(*)::text count FROM mymoneymap.emergency_reserve_movements WHERE user_id=$1',
        [userId],
      )
    ).rows[0]!.count;
  }

  async function journalCount(userId: string): Promise<string> {
    return (
      await pool.query<{ count: string }>(
        `SELECT count(*)::text count FROM mymoneymap.journal_entries
          WHERE user_id=$1 AND source_module='emergency_fund'`,
        [userId],
      )
    ).rows[0]!.count;
  }

  async function writeCounts(userId: string): Promise<{ journals: string; movements: string }> {
    const result = await pool.query<{ journals: string; movements: string }>(
      `SELECT
        (SELECT count(*)::text FROM mymoneymap.journal_entries WHERE user_id=$1) journals,
        (SELECT count(*)::text FROM mymoneymap.emergency_reserve_movements WHERE user_id=$1)
          movements`,
      [userId],
    );
    return result.rows[0]!;
  }
});

function body(response: request.Response): ReserveResponse {
  return response.body as ReserveResponse;
}
