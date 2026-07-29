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
import type { JournalEntry } from '../src/ledger/ledger.types';

describe('ledger HTTP contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-ledger-password';
  const users = {
    free: { id: randomUUID(), email: `ledger-free-${randomUUID()}@example.test` },
    premium: { id: randomUUID(), email: `ledger-premium-${randomUUID()}@example.test` },
    admin: { id: randomUUID(), email: `ledger-admin-${randomUUID()}@example.test` },
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

  it('posts exact income once and safely replays the same idempotency request', async () => {
    const agent = await loggedIn('free');
    const key = randomUUID();
    const body = entryBody('external_income', '1000.00');
    const first = await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', key)
      .send(body)
      .expect(201)
      .expect('Idempotency-Replayed', 'false');
    const replay = await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', key)
      .send(body)
      .expect(201)
      .expect('Idempotency-Replayed', 'true');

    expect(replay.body).toEqual(first.body);
    expect(first.body).toMatchObject({
      economicType: 'external_income',
      postedOn: '2026-07-29',
      actorUserId: users.free.id,
      reversesEntryId: null,
      replacesEntryId: null,
    });
    expect(first.body.legs).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ side: 'debit', amount: '1000.000000000000', currency: 'HUF' }),
        expect.objectContaining({ side: 'credit', amount: '1000.000000000000', currency: 'HUF' }),
      ]),
    );
    expect(
      (
        await pool.query<{ count: string }>(
          'SELECT count(*)::text AS count FROM mymoneymap.journal_entries WHERE id = $1',
          [first.body.id],
        )
      ).rows[0]?.count,
    ).toBe('1');

    await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', key)
      .send({ ...body, amount: '1001.00' })
      .expect(409);
  });

  it('rejects invalid amount, currency, date, transfer shape, missing key, and category ownership', async () => {
    const agent = await loggedIn('premium');
    for (const body of [
      entryBody('external_expense', '0'),
      entryBody('external_expense', '-1'),
      { ...entryBody('external_expense', '1'), currency: 'huf' },
      { ...entryBody('external_expense', '1'), postedOn: '2026-02-30' },
      {
        ...entryBody('internal_transfer', '1'),
        sourceAccountId: randomUUID(),
        destinationAccountId: undefined,
      },
      { ...entryBody('external_expense', '1'), categoryId: randomUUID() },
    ]) {
      await agent
        .post('/api/v1/journal/entries')
        .set('Idempotency-Key', randomUUID())
        .send(body)
        .expect((response) => expect([400, 422]).toContain(response.status));
    }
    await agent.post('/api/v1/journal/entries').send(entryBody('external_income', '1')).expect(400);
  });

  it('posts an owned transfer with zero external legs and rejects cross-user accounts', async () => {
    const agent = await loggedIn('premium');
    const investment = await createAccount(users.premium.id, 'investment');
    const goal = await createAccount(users.premium.id, 'goal');
    const foreignGoal = await createAccount(users.free.id, 'goal');
    const response = await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', randomUUID())
      .send({
        ...entryBody('internal_transfer', '300.00'),
        sourceAccountId: investment,
        destinationAccountId: goal,
      })
      .expect(201);
    const transfer = response.body as JournalEntry;
    expect(transfer.legs.every((leg) => leg.accountId)).toBe(true);
    expect(await balance(users.premium.id, investment)).toContain('-300');
    expect(await balance(users.premium.id, goal)).toContain('300');

    await agent
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', randomUUID())
      .send({
        ...entryBody('internal_transfer', '1'),
        sourceAccountId: investment,
        destinationAccountId: foreignGoal,
      })
      .expect(404);
  });

  it('reverses without deletion and makes concurrent reversal attempts single-winner', async () => {
    const agent = await loggedIn('premium');
    const original = await post(agent, entryBody('external_expense', '125.50'));
    const reversal = await agent
      .post(`/api/v1/journal/entries/${original.id}/reversals`)
      .set('Idempotency-Key', randomUUID())
      .send({ postedOn: '2026-07-30' })
      .expect(201);
    expect(reversal.body.reversesEntryId).toBe(original.id);
    expect(
      (
        await pool.query<{ count: string }>(
          'SELECT count(*)::text AS count FROM mymoneymap.journal_entries WHERE id IN ($1,$2)',
          [original.id, reversal.body.id],
        )
      ).rows[0]?.count,
    ).toBe('2');
    expect(
      (
        await pool.query<{ net: string }>(
          `SELECT COALESCE(sum(CASE WHEN l.side = 'debit' THEN l.amount ELSE -l.amount END), 0)::text
                  AS net
             FROM mymoneymap.journal_legs l
            WHERE l.entry_id IN ($1,$2)
              AND l.account_id IS NULL`,
          [original.id, reversal.body.id],
        )
      ).rows[0]?.net,
    ).toBe('0.000000000000');

    const concurrentOriginal = await post(agent, entryBody('external_income', '77.25'));
    const [left, right] = await Promise.all([
      agent
        .post(`/api/v1/journal/entries/${concurrentOriginal.id}/reversals`)
        .set('Idempotency-Key', randomUUID())
        .send({ postedOn: '2026-07-30' }),
      agent
        .post(`/api/v1/journal/entries/${concurrentOriginal.id}/reversals`)
        .set('Idempotency-Key', randomUUID())
        .send({ postedOn: '2026-07-30' }),
    ]);
    expect([left.status, right.status].sort()).toEqual([201, 409]);
  });

  it('atomically corrects with a reversal and replacement', async () => {
    const agent = await loggedIn('free');
    const original = await post(agent, entryBody('external_expense', '20.00'));
    const response = await agent
      .post(`/api/v1/journal/entries/${original.id}/corrections`)
      .set('Idempotency-Key', randomUUID())
      .send(entryBody('external_expense', '25.00'))
      .expect(201);
    expect(response.body.reversal.reversesEntryId).toBe(original.id);
    expect(response.body.replacement.replacesEntryId).toBe(original.id);
    expect(response.body.replacement.legs[0].amount).toBe('25.000000000000');
  });

  it('rolls back the correction reversal when replacement validation fails', async () => {
    const agent = await loggedIn('free');
    const original = await post(agent, entryBody('external_income', '9.00'));
    const foreignAccount = await createAccount(users.premium.id, 'investment');
    await agent
      .post(`/api/v1/journal/entries/${original.id}/corrections`)
      .set('Idempotency-Key', randomUUID())
      .send({ ...entryBody('external_income', '10.00'), accountId: foreignAccount })
      .expect(404);
    expect(
      (
        await pool.query<{ count: string }>(
          `SELECT count(*)::text AS count
             FROM mymoneymap.journal_entries
            WHERE reverses_entry_id = $1 OR replaces_entry_id = $1`,
          [original.id],
        )
      ).rows[0]?.count,
    ).toBe('0');
  });

  it('isolates entries by owner and paginates deterministically with date filters', async () => {
    const freeAgent = await loggedIn('free');
    const premiumAgent = await loggedIn('premium');
    const foreign = await post(freeAgent, {
      ...entryBody('external_income', '11'),
      postedOn: '2026-07-27',
    });
    await premiumAgent
      .post(`/api/v1/journal/entries/${foreign.id}/reversals`)
      .set('Idempotency-Key', randomUUID())
      .send({ postedOn: '2026-07-30' })
      .expect(404);

    for (const postedOn of ['2026-07-26', '2026-07-27', '2026-07-28']) {
      await post(premiumAgent, { ...entryBody('external_income', '2'), postedOn });
    }
    const first = await premiumAgent
      .get('/api/v1/journal/entries')
      .query({ dateFrom: '2026-07-26', dateTo: '2026-07-28', limit: 2 })
      .expect(200);
    expect(first.body.items).toHaveLength(2);
    expect(first.body.nextCursor).toEqual(expect.any(String));
    const second = await premiumAgent
      .get('/api/v1/journal/entries')
      .query({
        dateFrom: '2026-07-26',
        dateTo: '2026-07-28',
        limit: 2,
        cursor: first.body.nextCursor,
      })
      .expect(200);
    expect(second.body.items).toHaveLength(1);
    expect(
      new Set([...first.body.items, ...second.body.items].map((entry: { id: string }) => entry.id))
        .size,
    ).toBe(3);
    await premiumAgent.get('/api/v1/journal/entries').query({ cursor: 'not-json' }).expect(400);
  });

  it('requires authentication and denies admin personal-finance access', async () => {
    await request(app.getHttpServer()).get('/api/v1/journal/entries').expect(401);
    const admin = await loggedIn('admin');
    await admin.get('/api/v1/journal/entries').expect(403);
    await admin
      .post('/api/v1/journal/entries')
      .set('Idempotency-Key', randomUUID())
      .send(entryBody('external_income', '1'))
      .expect(403);
  });

  function entryBody(economicType: string, amount: string): Record<string, string> {
    return { economicType, amount, currency: 'HUF', postedOn: '2026-07-29' };
  }

  async function post(
    agent: ReturnType<typeof request.agent>,
    body: Record<string, unknown>,
  ): Promise<JournalEntry> {
    return (
      await agent
        .post('/api/v1/journal/entries')
        .set('Idempotency-Key', randomUUID())
        .send(body)
        .expect(201)
    ).body as JournalEntry;
  }

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
      [id, email, passwordHash, `${role} Ledger User`, role],
    );
  }

  async function createAccount(userId: string, kind: string): Promise<string> {
    const id = randomUUID();
    await pool.query(
      `INSERT INTO mymoneymap.ledger_accounts
         (id,user_id,kind,module_reference_id,created_at)
       VALUES ($1,$2,$3,$4,now())`,
      [id, userId, kind, randomUUID()],
    );
    return id;
  }

  async function balance(userId: string, accountId: string): Promise<string> {
    return (
      await pool.query<{ balance: string }>(
        `SELECT COALESCE(sum(CASE WHEN side = 'debit' THEN amount ELSE -amount END), 0)::text
                AS balance
           FROM mymoneymap.journal_legs
          WHERE user_id = $1 AND account_id = $2 AND currency = 'HUF'`,
        [userId, accountId],
      )
    ).rows[0]!.balance;
  }
});
