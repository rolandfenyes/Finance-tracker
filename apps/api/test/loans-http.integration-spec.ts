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
import { RecurrenceProcessor } from '../src/recurrence/recurrence-queue.service';

jest.setTimeout(30_000);

interface LoanResponse {
  id: string;
  title: string;
  principal: string;
  outstandingPrincipal: string;
  currency: string;
  completedAt: string | null;
  archivedAt: string | null;
  estimate: { version: string; isApr: boolean; monthlyPayment: string };
  projectedSchedule: Array<{ status: 'projected' }>;
  payments: Array<{
    id: string;
    source: 'manual' | 'scheduled';
    loanPrincipalComponent: string;
    conversion: { rate: string; provider: string };
    reversedByJournalEntryId: string | null;
  }>;
}

describe('loans HTTP, ledger, FX, and lifecycle contract', () => {
  let app: INestApplication;
  let pool: Pool;
  let passwordHash: string;
  const password = 'synthetic-loan-password';
  const owner = { id: randomUUID(), email: `loan-owner-${randomUUID()}@example.test` };
  const other = { id: randomUUID(), email: `loan-other-${randomUUID()}@example.test` };
  const admin = { id: randomUUID(), email: `loan-admin-${randomUUID()}@example.test` };

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

  it('keeps projections separate from posted history and GET performs no writes', async () => {
    const agent = await loggedIn(owner.email);
    const loan = await createLoan(agent, '120000', '12', 12);
    expect(loan.estimate).toMatchObject({
      version: 'standard_nominal_monthly_annuity_v1',
      isApr: false,
      monthlyPayment: '10661.85',
    });
    expect(loan.projectedSchedule.every(({ status }) => status === 'projected')).toBe(true);
    expect(loan.payments).toEqual([]);
    const before = await counts(owner.id);
    await agent.get('/api/v1/loans').expect(200);
    expect(await counts(owner.id)).toEqual(before);
  });

  it('posts exact retry-safe components, rejects overpayment, and reconciles the journal', async () => {
    const agent = await loggedIn(other.email);
    const loan = await createLoan(agent, '100', '0', 1);
    const key = `loan-payment-${randomUUID()}`;
    await payment(agent, loan.id, '40', '40', '0', '0', key)
      .expect(201)
      .expect('Idempotency-Replayed', 'false')
      .expect((response) => expect(body(response).outstandingPrincipal).toBe('60'));
    await payment(agent, loan.id, '40', '40', '0', '0', key)
      .expect(201)
      .expect('Idempotency-Replayed', 'true');
    await payment(agent, loan.id, '61', '61', '0', '0', `over-${randomUUID()}`).expect(422);
    expect(await counts(other.id)).toMatchObject({ payments: '1', loanJournals: '1' });
  });

  it('serializes concurrent principal posts and atomically reverse-replaces corrections', async () => {
    const agent = await loggedIn(owner.email);
    let loan = await createLoan(agent, '100', '0', 1);
    const concurrent = await Promise.all([
      payment(agent, loan.id, '60', '60', '0', '0', `concurrent-a-${randomUUID()}`),
      payment(agent, loan.id, '60', '60', '0', '0', `concurrent-b-${randomUUID()}`),
    ]);
    expect(concurrent.map(({ status }) => status).sort()).toEqual([201, 422]);
    loan = (await list(agent)).find(({ id }) => id === loan.id)!;
    expect(loan.outstandingPrincipal).toBe('40');

    const original = loan.payments[0]!;
    loan = await agent
      .post(`/api/v1/loans/${loan.id}/payments/${original.id}/corrections`)
      .set('Idempotency-Key', `correction-${randomUUID()}`)
      .send({
        amount: '30',
        currency: 'HUF',
        principalComponent: '30',
        interestComponent: '0',
        feeComponent: '0',
        paidOn: '2026-07-27',
      })
      .expect(201)
      .then(body);
    expect(loan.outstandingPrincipal).toBe('70');
    expect(loan.payments).toHaveLength(2);
    await expect(
      pool.query('UPDATE mymoneymap.loan_payments SET amount=amount+1 WHERE id=$1', [original.id]),
    ).rejects.toMatchObject({ code: '23514' });
  });

  it('completes only on exact payoff, reverses to reopen, and archives explicit history', async () => {
    const agent = await loggedIn(other.email);
    let loan = (await list(agent)).find(({ principal }) => principal === '100')!;
    loan = await payment(agent, loan.id, '60', '60', '0', '0', `payoff-${randomUUID()}`)
      .expect(201)
      .then(body);
    expect(loan.outstandingPrincipal).toBe('0');
    expect(loan.completedAt).not.toBeNull();
    const payoff = loan.payments.find(
      ({ loanPrincipalComponent }) => loanPrincipalComponent === '60',
    )!;
    loan = await agent
      .post(`/api/v1/loans/${loan.id}/payments/${payoff.id}/reversals`)
      .set('Idempotency-Key', `reverse-${randomUUID()}`)
      .send({ postedOn: '2026-07-29' })
      .expect(201)
      .then(body);
    expect(loan).toMatchObject({ outstandingPrincipal: '60', completedAt: null, archivedAt: null });
    await agent.delete(`/api/v1/loans/${loan.id}`).expect(409);
    loan = await payment(agent, loan.id, '60', '60', '0', '0', `second-payoff-${randomUUID()}`)
      .expect(201)
      .then(body);
    await agent.post(`/api/v1/loans/${loan.id}/archive`).expect(200);
    expect((await list(agent)).find(({ id }) => id === loan.id)!.archivedAt).not.toBeNull();
  });

  it('applies dated FX consistently and stores provenance for cross-currency repayment', async () => {
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
    const agent = await loggedIn(owner.email);
    const loan = await createLoan(agent, '1000', '0', 10);
    const posted = await payment(agent, loan.id, '1', '1', '0', '0', `fx-${randomUUID()}`, 'EUR')
      .expect(201)
      .then(body);
    expect(posted.outstandingPrincipal).toBe('600');
    expect(posted.payments[0]).toMatchObject({
      loanPrincipalComponent: '400',
      conversion: { rate: '400', provider: 'frankfurter' },
    });
  });

  it('owns schedules, validates access, isolates users, and deletes only an empty loan', async () => {
    const ownerAgent = await loggedIn(owner.email);
    const otherAgent = await loggedIn(other.email);
    const empty = await createLoan(ownerAgent, '10', '0', 1);
    await ownerAgent
      .post(`/api/v1/loans/${empty.id}/recurring-rule`)
      .send({
        title: 'Synthetic repayment',
        amount: '10',
        currency: 'HUF',
        startsOn: '2026-07-29',
        rrule: 'FREQ=MONTHLY;BYMONTHDAY=29',
      })
      .expect(201);
    await otherAgent.patch(`/api/v1/loans/${empty.id}`).send({ title: 'Foreign' }).expect(404);
    await ownerAgent.delete(`/api/v1/loans/${empty.id}`).expect(204);
    await request(app.getHttpServer()).get('/api/v1/loans').expect(401);
    await (await loggedIn(admin.email)).get('/api/v1/loans').expect(403);
  });

  it('materializes a scheduled repayment once and makes worker retry a no-op', async () => {
    const agent = await loggedIn(owner.email);
    const marker = Number.parseInt(randomUUID().replaceAll('-', '').slice(0, 8), 16);
    const dueOn = `${1900 + (marker % 100)}-${String(1 + (marker % 12)).padStart(2, '0')}-${String(
      1 + (marker % 28),
    ).padStart(2, '0')}`;
    const loan = await createLoan(agent, '25', '0', 1, dueOn);
    await agent
      .post(`/api/v1/loans/${loan.id}/recurring-rule`)
      .send({
        title: 'One scheduled payoff',
        amount: '25',
        currency: 'HUF',
        startsOn: dueOn,
        rrule: 'FREQ=DAILY;COUNT=1',
      })
      .expect(201);
    const processor = app.get(RecurrenceProcessor);
    await expect(
      processor.process({ dueThrough: dueOn }, 1, 3, `loan-${randomUUID()}`),
    ).resolves.toEqual({ status: 'completed', occurrences: 1 });
    await expect(
      processor.process({ dueThrough: dueOn }, 2, 3, `loan-retry-${randomUUID()}`),
    ).resolves.toEqual({ status: 'duplicate', occurrences: 0 });
    const persisted = (await list(agent)).find(({ id }) => id === loan.id)!;
    expect(persisted).toMatchObject({ outstandingPrincipal: '0' });
    expect(persisted.payments).toHaveLength(1);
    expect(persisted.payments[0]).toMatchObject({ source: 'scheduled' });
  });

  async function insertUser(id: string, email: string, role: 'premium' | 'admin'): Promise<void> {
    await pool.query(
      `INSERT INTO mymoneymap.users
        (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
       VALUES ($1,$2,$3,'Synthetic Loan User','1990-01-01',$4,now(),now(),now())`,
      [id, email, passwordHash, role],
    );
  }

  async function loggedIn(email: string): Promise<ReturnType<typeof request.agent>> {
    const agent = request.agent(app.getHttpServer());
    await agent.post('/api/v1/auth/sessions').send({ email, password }).expect(204);
    return agent;
  }

  async function createLoan(
    agent: ReturnType<typeof request.agent>,
    principal: string,
    rate: string,
    termMonths: number,
    startsOn = '2026-07-01',
  ): Promise<LoanResponse> {
    const title = `Synthetic loan ${randomUUID()}`;
    return agent
      .post('/api/v1/loans')
      .send({
        title,
        principal,
        currency: 'HUF',
        nominalAnnualRate: rate,
        termMonths,
        startsOn,
        paymentDay: 29,
      })
      .expect(201)
      .then((response) => {
        const items = response.body.items as LoanResponse[];
        return items.find((item) => item.title === title)!;
      });
  }

  function payment(
    agent: ReturnType<typeof request.agent>,
    loanId: string,
    amount: string,
    principalComponent: string,
    interestComponent: string,
    feeComponent: string,
    key: string,
    currency = 'HUF',
  ): request.Test {
    return agent.post(`/api/v1/loans/${loanId}/payments`).set('Idempotency-Key', key).send({
      amount,
      currency,
      principalComponent,
      interestComponent,
      feeComponent,
      paidOn: '2026-07-29',
    });
  }

  async function list(agent: ReturnType<typeof request.agent>): Promise<LoanResponse[]> {
    return agent
      .get('/api/v1/loans')
      .expect(200)
      .then((response) => response.body.items as LoanResponse[]);
  }

  async function counts(userId: string): Promise<{ payments: string; loanJournals: string }> {
    return (
      await pool.query<{ payments: string; loanJournals: string }>(
        `SELECT
          (SELECT count(*)::text FROM mymoneymap.loan_payments WHERE user_id=$1) payments,
          (SELECT count(*)::text FROM mymoneymap.journal_entries
            WHERE user_id=$1 AND source_module='loans') "loanJournals"`,
        [userId],
      )
    ).rows[0]!;
  }
});

function body(response: request.Response): LoanResponse {
  return response.body as LoanResponse;
}
