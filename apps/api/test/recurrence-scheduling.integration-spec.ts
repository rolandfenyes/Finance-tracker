import { ConfigService } from '@nestjs/config';
import { randomUUID } from 'node:crypto';
import { Queue, type ConnectionOptions } from 'bullmq';
import type { Pool } from 'pg';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import {
  RECURRENCE_MAX_ATTEMPTS,
  RECURRENCE_QUEUE,
  RecurrenceProcessor,
  RecurrenceQueueService,
  recurrenceJobKey,
} from '../src/recurrence/recurrence-queue.service';
import { RecurrenceRepository } from '../src/recurrence/recurrence.repository';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('recurrence scheduling PostgreSQL and BullMQ invariants', () => {
  it('migrates Step 09 up and rolls it back without disturbing Step 08', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'recurring_occurrences')).toBe(
        'mymoneymap.recurring_occurrences',
      );
      expect(await relation(pool, 'recurrence_job_executions')).toBe(
        'mymoneymap.recurrence_job_executions',
      );

      await migrateOneDown(database);
      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'recurring_occurrences')).toBe(
        'mymoneymap.recurring_occurrences',
      );

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'recurring_occurrences')).toBe(
        'mymoneymap.recurring_occurrences',
      );

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
      expect(await relation(pool, 'recurring_occurrences')).toBe(
        'mymoneymap.recurring_occurrences',
      );

      await migrateOneDown(database);
      expect(await relation(pool, 'recurring_rules')).toBeNull();
      expect(await relation(pool, 'recurring_occurrences')).toBeNull();
      expect(await relation(pool, 'budget_rules')).toBe('mymoneymap.budget_rules');

      await migrateToLatest(database);
      expect(await relation(pool, 'recurring_rules')).toBe('mymoneymap.recurring_rules');
    });
  });

  it('enforces exact amount, ownership, currency, economic type, and category semantics', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userA = await insertUser(pool);
      const userB = await insertUser(pool);
      const incomeA = await insertCategory(pool, userA, 'income');
      const spendingA = await insertCategory(pool, userA, 'spending');
      const spendingB = await insertCategory(pool, userB, 'spending');

      await expect(insertRule(pool, userA, '0', 'expense', null)).rejects.toMatchObject({
        code: '23514',
      });
      await expect(insertRule(pool, userA, '10', 'expense', spendingB)).rejects.toMatchObject({
        code: '23503',
      });
      await expect(insertRule(pool, userA, '10', 'expense', incomeA)).rejects.toMatchObject({
        code: '23514',
      });
      await expect(insertRule(pool, userA, '10', 'transfer', spendingA)).rejects.toMatchObject({
        code: '23514',
      });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.recurring_rules
            (id,user_id,title,amount,currency,economic_type,starts_on,rrule,created_at,updated_at)
           VALUES ($1,$2,'Wrong currency','10','EUR','expense','2026-07-01','',now(),now())`,
          [randomUUID(), userA],
        ),
      ).rejects.toMatchObject({ code: '23503' });

      const exact = await insertRule(pool, userA, '10.123456789012', 'income', incomeA);
      expect(
        (
          await pool.query<{ amount: string }>(
            'SELECT amount::text FROM mymoneymap.recurring_rules WHERE id = $1',
            [exact],
          )
        ).rows[0]?.amount,
      ).toBe('10.123456789012');
    });
  });

  it('deduplicates retry/concurrent workers and rolls back a partially failed attempt', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      await insertRule(pool, userId, '12.5', 'expense', null, {
        id: '10000000-0000-4000-8000-000000000001',
        startsOn: '2026-07-01',
        rrule: 'FREQ=DAILY;COUNT=3',
      });
      const repository = new RecurrenceRepository(database);
      const clock = new FixedClock(UtcInstant.create('2026-07-03T12:00:00.000Z'));
      const processor = new RecurrenceProcessor(repository, clock);

      const results = await Promise.all([
        processor.process({ dueThrough: '2026-07-03' }, 1, 3, 'concurrent-a'),
        processor.process({ dueThrough: '2026-07-03' }, 1, 3, 'concurrent-b'),
      ]);
      expect(results.map(({ status }) => status).sort()).toEqual(['completed', 'duplicate']);
      expect(await occurrenceCount(pool)).toBe('3');
      expect(
        (
          await pool.query<{ count: string }>(
            'SELECT count(*)::text AS count FROM mymoneymap.journal_entries',
          )
        ).rows[0]?.count,
      ).toBe('0');
      await expect(processor.process({ dueThrough: '2026-07-03' }, 2, 3, 'retry')).resolves.toEqual(
        { status: 'duplicate', occurrences: 0 },
      );
      expect(await occurrenceCount(pool)).toBe('3');

      await pool.query('DELETE FROM mymoneymap.recurring_rules');
      await insertRule(pool, userId, '1.000000000001', 'expense', null, {
        id: '20000000-0000-4000-8000-000000000001',
        startsOn: '2026-07-01',
        rrule: 'FREQ=DAILY;COUNT=2',
      });
      await insertRule(pool, userId, '2', 'expense', null, {
        id: 'f0000000-0000-4000-8000-000000000001',
        startsOn: '2026-07-01',
        rrule: 'FREQ=DAILY;BYSETPOS=1',
      });

      for (let attempt = 1; attempt <= RECURRENCE_MAX_ATTEMPTS; attempt += 1) {
        await expect(
          processor.process(
            { dueThrough: '2026-07-04' },
            attempt,
            RECURRENCE_MAX_ATTEMPTS,
            'partial-failure',
          ),
        ).rejects.toThrow('Unsupported RRULE component');
        expect(await occurrenceCount(pool)).toBe('0');
      }
      expect(await repository.execution(recurrenceJobKey('2026-07-04'))).toMatchObject({
        status: 'dead_letter',
        attemptCount: 3,
        errorCode: 'invalid_stored_rrule',
      });
      const events = await pool.query<{ status: string; count: string }>(
        `SELECT e.status, count(*)::text AS count
           FROM mymoneymap.recurrence_job_events e
           JOIN mymoneymap.recurrence_job_executions x ON x.id = e.execution_id
          WHERE x.job_key = $1
          GROUP BY e.status`,
        [recurrenceJobKey('2026-07-04')],
      );
      expect(Object.fromEntries(events.rows.map(({ status, count }) => [status, count]))).toEqual({
        queued: '1',
        running: '3',
        retryable_failed: '2',
        dead_letter: '1',
      });
    });
  });

  it('uses a stable BullMQ job with bounded retry and retained dead-letter state', async () => {
    await withIsolatedPostgresDatabase(async ({ database }) => {
      await migrateToLatest(database);
      const config = new ConfigService({
        RECURRENCE_ENABLED: true,
        REDIS_URL: process.env.REDIS_URL,
      });
      const repository = new RecurrenceRepository(database);
      const clock = new FixedClock(UtcInstant.create('2026-07-29T12:00:00.000Z'));
      const processor = new RecurrenceProcessor(repository, clock);
      const service = new RecurrenceQueueService(config, processor, repository, clock, true);
      const inspector = new Queue(RECURRENCE_QUEUE, {
        connection: connection(process.env.REDIS_URL!),
      });
      try {
        await inspector.obliterate({ force: true });
        await service.onModuleInit();
        const first = await service.enqueueDueThrough('2026-07-29');
        const second = await service.enqueueDueThrough('2026-07-29');
        expect(second).toBe(first);
        const job = await inspector.getJob(first!);
        expect(job?.opts).toMatchObject({
          attempts: RECURRENCE_MAX_ATTEMPTS,
          backoff: { type: 'exponential', delay: 500 },
          removeOnFail: false,
        });
        expect(await repository.execution(recurrenceJobKey('2026-07-29'))).toMatchObject({
          status: 'queued',
          attemptCount: 0,
          maxAttempts: RECURRENCE_MAX_ATTEMPTS,
        });
      } finally {
        await service.onApplicationShutdown();
        await inspector.obliterate({ force: true });
        await inspector.close();
      }
    });
  });
});

async function relation(pool: Pool, name: string): Promise<string | null> {
  return (
    (
      await pool.query<{ relation: string | null }>('SELECT to_regclass($1)::text AS relation', [
        `mymoneymap.${name}`,
      ])
    ).rows[0]?.relation ?? null
  );
}

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic Recurrence User','1990-01-01','premium',
             now(),now(),now())`,
    [id, `${id}@example.test`],
  );
  return id;
}

async function insertCategory(
  pool: Pool,
  userId: string,
  kind: 'income' | 'spending',
): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.categories
      (id,user_id,label,kind,color,created_at,updated_at)
     VALUES ($1,$2,$3,$4,'#AABBCC',now(),now())`,
    [id, userId, `${kind} ${id}`, kind],
  );
  return id;
}

async function insertRule(
  pool: Pool,
  userId: string,
  amount: string,
  economicType: string,
  categoryId: string | null,
  options: { id?: string; startsOn?: string; rrule?: string } = {},
): Promise<string> {
  const id = options.id ?? randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.recurring_rules
      (id,user_id,title,amount,currency,economic_type,starts_on,rrule,category_id,created_at,updated_at)
     VALUES ($1,$2,$3,$4,'HUF',$5,$6,$7,$8,now(),now())`,
    [
      id,
      userId,
      `Rule ${id}`,
      amount,
      economicType,
      options.startsOn ?? '2026-07-01',
      options.rrule ?? '',
      categoryId,
    ],
  );
  return id;
}

async function occurrenceCount(pool: Pool): Promise<string> {
  return (
    await pool.query<{ count: string }>(
      'SELECT count(*)::text AS count FROM mymoneymap.recurring_occurrences',
    )
  ).rows[0]!.count;
}

function connection(redisUrl: string): ConnectionOptions {
  const url = new URL(redisUrl);
  return {
    host: url.hostname,
    port: Number.parseInt(url.port || '6379', 10),
    maxRetriesPerRequest: null,
  };
}
