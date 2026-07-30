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

describe('securities HTTP, FIFO, isolation, and read contracts', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-securities-password';
  const owner = { id: randomUUID(), email: `securities-owner-${randomUUID()}@example.test` };
  const other = { id: randomUUID(), email: `securities-other-${randomUUID()}@example.test` };

  beforeAll(async () => {
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
    const redis = await app.get(RedisSecurityService).ready();
    const keys: string[] = [];
    for await (const batch of redis.scanIterator({ MATCH: 'mymoneymap:*' })) keys.push(...batch);
    if (keys.length) await redis.del(keys);
    pool = app.get(POSTGRES_POOL);
    passwordHash = await app.get(PasswordService).hash(password);
    await Promise.all([insertUser(owner.id, owner.email), insertUser(other.id, other.email)]);
  });

  afterAll(async () => app.close());

  it('keeps symbol plus market canonical and rejects concurrent oversells atomically', async () => {
    const agent = await loggedIn(owner.email);
    await postTrade(agent, 'buy', 'ACME', 'NASDAQ', '3', '10', '1').expect(201);
    await postTrade(agent, 'buy', 'ACME', 'NYSE', '1', '20', '0').expect(201);
    const competing = await Promise.all([
      postTrade(agent, 'sell', 'ACME', 'NASDAQ', '2', '15', '0.5'),
      postTrade(agent, 'sell', 'ACME', 'NASDAQ', '2', '15', '0.5'),
    ]);
    expect(competing.map(({ status }) => status).sort()).toEqual([201, 422]);

    const portfolio = await agent.get('/api/v1/securities/portfolio').expect(200);
    const positions = (portfolio.body as { positions: Array<Record<string, unknown>> }).positions;
    expect(positions).toHaveLength(2);
    expect(positions.map(({ market }) => market).sort()).toEqual(['NASDAQ', 'NYSE']);
    expect(positions.find(({ market }) => market === 'NASDAQ')).toMatchObject({
      quantity: '1.000000000000000000',
      remainingCostLocal: '10.333333333333',
      marketValueLocal: null,
      quote: { status: 'unavailable', last: null },
    });

    const realized = await pool.query<{ realized: string }>(
      `SELECT realized_local::text realized
         FROM mymoneymap.securities_realized_results r
         JOIN mymoneymap.securities_instruments i ON i.id=r.instrument_id
        WHERE r.user_id=$1 AND i.symbol='ACME' AND i.market='NASDAQ'`,
      [owner.id],
    );
    expect(realized.rows).toEqual([{ realized: '8.833333333333' }]);
  });

  it('makes portfolio reads side-effect free and reports missing valuation explicitly', async () => {
    const agent = await loggedIn(owner.email);
    const before = await counts(owner.id);
    await agent
      .get('/api/v1/securities/portfolio')
      .expect(200)
      .expect((response) => {
        expect(response.body).toMatchObject({ valuationStatus: 'partial' });
        expect(JSON.stringify(response.body)).not.toContain('"marketValueLocal":"11');
      });
    expect(await counts(owner.id)).toEqual(before);
  });

  it('reverses linked trade cash and fee entries and rebuilds FIFO without deleting history', async () => {
    const agent = await loggedIn(owner.email);
    const activity = await agent.get('/api/v1/securities/activity').expect(200);
    const sell = (
      activity.body as { trades: Array<{ id: string; side: string; feeJournalEntryId: string }> }
    ).trades.find(({ side }) => side === 'sell')!;
    await agent
      .post(`/api/v1/securities/trades/${sell.id}/reversals`)
      .send({ postedOn: '2026-07-30' })
      .expect(201);
    const current = await agent.get('/api/v1/securities/activity').expect(200);
    const reversed = (
      current.body as {
        trades: Array<{
          id: string;
          reversedByCashJournalEntryId: string | null;
          reversedByFeeJournalEntryId: string | null;
        }>;
      }
    ).trades.find(({ id }) => id === sell.id)!;
    expect(reversed.reversedByCashJournalEntryId).not.toBeNull();
    expect(reversed.reversedByFeeJournalEntryId).not.toBeNull();
    const portfolio = (await agent.get('/api/v1/securities/portfolio').expect(200)).body as {
      positions: Array<{ market: string; quantity: string }>;
    };
    expect(portfolio.positions.find(({ market }) => market === 'NASDAQ')!.quantity).toBe(
      '3.000000000000000000',
    );
  });

  it('fingerprints duplicate imports and rolls back an invalid import commit', async () => {
    const agent = await loggedIn(other.email);
    const csv =
      'Date,Type,Symbol,Market,Quantity,Price,Currency\n' + '2026-07-30,SELL,EMPTY,NASDAQ,1,10,HUF';
    const first = await agent.post('/api/v1/securities/imports').send({ csv }).expect(201);
    const duplicate = await agent.post('/api/v1/securities/imports').send({ csv }).expect(201);
    expect(duplicate.body.id).toBe(first.body.id);
    await agent.post(`/api/v1/securities/imports/${first.body.id}/commit`).expect(422);
    expect(await counts(other.id)).toEqual({ trades: '0', journals: '0', lots: '0' });

    const invalid = await agent
      .post('/api/v1/securities/imports')
      .send({ csv: 'Date,Type,Symbol,Quantity,Price,Currency\n2026-07-30,BUY,X,1,10,HUF' })
      .expect(201);
    expect(invalid.body.errorCount).toBe(1);
    await agent.post(`/api/v1/securities/imports/${invalid.body.id}/commit`).expect(422);
  });

  it('isolates users, validates dates and decimals, and keeps refresh disabled by approval', async () => {
    const ownerAgent = await loggedIn(owner.email);
    const otherAgent = await loggedIn(other.email);
    const instrumentId = (
      await pool.query<{ id: string }>(
        `SELECT id FROM mymoneymap.securities_instruments WHERE symbol='ACME' AND market='NASDAQ'`,
      )
    ).rows[0]!.id;
    await otherAgent.get(`/api/v1/securities/instruments/${instrumentId}`).expect(404);
    await otherAgent.get(`/api/v1/securities/quotes?instrumentId=${instrumentId}`).expect(404);
    await pool.query(
      `INSERT INTO mymoneymap.securities_daily_prices
        (id,instrument_id,trading_on,close,currency,provider,observed_at,retrieved_at)
       VALUES ($1,$3,'2026-07-24','10','HUF','synthetic','2026-07-24T16:00:00Z',now()),
              ($2,$3,'2026-07-27','11','HUF','synthetic','2026-07-27T16:00:00Z',now())
       ON CONFLICT (instrument_id,trading_on,provider)
       DO UPDATE SET close=EXCLUDED.close, observed_at=EXCLUDED.observed_at,
                     retrieved_at=EXCLUDED.retrieved_at`,
      [randomUUID(), randomUUID(), instrumentId],
    );
    await ownerAgent
      .get(`/api/v1/securities/instruments/${instrumentId}/prices?from=2026-07-24&to=2026-07-27`)
      .expect(200)
      .expect((response) => {
        expect(
          (response.body as { items: Array<{ tradingOn: string }> }).items.map(
            ({ tradingOn }) => tradingOn,
          ),
        ).toEqual(['2026-07-24', '2026-07-27']);
      });
    await ownerAgent
      .get(`/api/v1/securities/instruments/${instrumentId}/prices?from=2026-07-31&to=2026-07-30`)
      .expect(400);
    await postTrade(ownerAgent, 'buy', 'BAD', 'NASDAQ', '0', '10', '0').expect(400);
    await ownerAgent.post('/api/v1/securities/refresh-jobs').send({}).expect(201).expect({
      status: 'disabled',
      reason: 'production provider approval is pending',
    });
    await request(app.getHttpServer()).get('/api/v1/securities/portfolio').expect(401);
  });

  async function insertUser(id: string, email: string): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
        (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Securities User','1990-01-01','premium',now(),now(),now())`,
      [id, email, passwordHash],
    );
  }

  async function loggedIn(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  function postTrade(
    agent: ReturnType<typeof request.agent>,
    side: 'buy' | 'sell',
    symbol: string,
    market: string,
    quantity: string,
    unitPrice: string,
    fee: string,
  ): request.Test {
    return agent.post('/api/v1/securities/trades').send({
      side,
      symbol,
      market,
      quantity,
      unitPrice,
      fee,
      currency: 'HUF',
      executedAt: '2026-07-30T10:00:00.000Z',
    });
  }

  async function counts(
    userId: string,
  ): Promise<{ trades: string; journals: string; lots: string }> {
    return (
      await pool.query<{ trades: string; journals: string; lots: string }>(
        `SELECT
          (SELECT count(*)::text FROM mymoneymap.securities_trades WHERE user_id=$1) trades,
          (SELECT count(*)::text FROM mymoneymap.journal_entries
            WHERE user_id=$1 AND source_module='securities') journals,
          (SELECT count(*)::text FROM mymoneymap.securities_lots WHERE user_id=$1) lots`,
        [userId],
      )
    ).rows[0]!;
  }
});
