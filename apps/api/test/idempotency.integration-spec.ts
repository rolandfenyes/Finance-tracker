import fc from 'fast-check';
import { sql } from 'kysely';
import { DatabaseTransactionService } from '../src/platform/database/database-transaction.service';
import { EntityId } from '../src/platform/identifiers/entity-id';
import {
  IdempotencyKey,
  IdempotencyOperation,
  RequestFingerprint,
} from '../src/platform/idempotency/idempotency';
import { IdempotencyService } from '../src/platform/idempotency/idempotency.service';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

const scopeA = EntityId.create('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
const scopeB = EntityId.create('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
const operation = IdempotencyOperation.create('synthetic.create');
const requestA = RequestFingerprint.fromCanonicalRequest('{"amount":"10.00","currency":"EUR"}');
const requestB = RequestFingerprint.fromCanonicalRequest('{"amount":"11.00","currency":"EUR"}');
const clock = new FixedClock(UtcInstant.create('2026-07-29T10:00:00.000Z'));

describe('PostgreSQL idempotency execution', () => {
  it('returns the identical stored response for a safe retry and executes work once', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);
      const key = IdempotencyKey.create('safe-retry-key');
      let executions = 0;
      const work = (): Promise<{ amount: string; currency: string }> => {
        executions += 1;
        return Promise.resolve({ amount: '9007199254740993.123456789', currency: 'EUR' });
      };

      const first = await service.execute(
        { scopeId: scopeA, operation, key, requestFingerprint: requestA },
        work,
      );
      const retry = await service.execute(
        { scopeId: scopeA, operation, key, requestFingerprint: requestA },
        work,
      );

      expect(first).toEqual({ value: retry.value, replayed: false });
      expect(retry).toEqual({ value: first.value, replayed: true });
      expect(executions).toBe(1);
      const stored = await pool.query<{
        count: string;
        key_hash: string;
        response: { amount: string };
      }>(
        'SELECT count(*) OVER ()::text AS count, key_hash, response FROM mymoneymap.idempotency_keys',
      );
      expect(stored.rows[0]).toMatchObject({
        count: '1',
        key_hash: expect.stringMatching(/^[0-9a-f]{64}$/),
        response: { amount: '9007199254740993.123456789' },
      });
      expect(JSON.stringify(stored.rows)).not.toContain('safe-retry-key');
    });
  });

  it('preserves idempotent retry semantics across generated client keys', async () => {
    await withIsolatedPostgresDatabase(async ({ database }) => {
      await migrateToLatest(database);
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);

      await fc.assert(
        fc.asyncProperty(fc.uuid(), async (clientKey) => {
          let executions = 0;
          const execution = {
            scopeId: scopeA,
            operation,
            key: IdempotencyKey.create(clientKey),
            requestFingerprint: requestA,
          };
          const work = (): Promise<{ key: string }> => {
            executions += 1;
            return Promise.resolve({ key: clientKey });
          };

          const first = await service.execute(execution, work);
          const retry = await service.execute(execution, work);

          expect(first).toEqual({ value: { key: clientKey }, replayed: false });
          expect(retry).toEqual({ value: first.value, replayed: true });
          expect(executions).toBe(1);
        }),
        { numRuns: 25 },
      );
    });
  });

  it('rejects a reused key with a different request fingerprint', async () => {
    await withIsolatedPostgresDatabase(async ({ database }) => {
      await migrateToLatest(database);
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);
      const key = IdempotencyKey.create('conflicting-key');

      await service.execute({ scopeId: scopeA, operation, key, requestFingerprint: requestA }, () =>
        Promise.resolve({ status: 'created' }),
      );

      const conflict = service.execute(
        { scopeId: scopeA, operation, key, requestFingerprint: requestB },
        () => Promise.resolve({ status: 'must-not-run' }),
      );
      await expect(conflict).rejects.toMatchObject({
        code: 'IDEMPOTENCY_CONFLICT',
        status: 409,
      });
    });
  });

  it('rolls back the claim and callback writes when execution fails, then permits a retry', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await pool.query('CREATE TABLE mymoneymap.synthetic_writes (value text NOT NULL)');
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);
      const execution = {
        scopeId: scopeA,
        operation,
        key: IdempotencyKey.create('rollback-key'),
        requestFingerprint: requestA,
      };

      await expect(
        service.execute(execution, async (transaction) => {
          await sql`INSERT INTO mymoneymap.synthetic_writes (value) VALUES ('rolled-back')`.execute(
            transaction,
          );
          throw new Error('synthetic failure');
        }),
      ).rejects.toThrow('synthetic failure');

      expect(
        (
          await pool.query<{ count: string }>(
            'SELECT count(*)::text AS count FROM mymoneymap.idempotency_keys',
          )
        ).rows[0]?.count,
      ).toBe('0');
      expect(
        (
          await pool.query<{ count: string }>(
            'SELECT count(*)::text AS count FROM mymoneymap.synthetic_writes',
          )
        ).rows[0]?.count,
      ).toBe('0');
      await expect(
        service.execute(execution, () => Promise.resolve({ status: 'retried' })),
      ).resolves.toEqual({ value: { status: 'retried' }, replayed: false });
    });
  });

  it('serializes concurrent same-key execution and runs the callback once', async () => {
    await withIsolatedPostgresDatabase(async ({ database }) => {
      await migrateToLatest(database);
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);
      const execution = {
        scopeId: scopeA,
        operation,
        key: IdempotencyKey.create('concurrent-key'),
        requestFingerprint: requestA,
      };
      let executions = 0;
      const work = async (): Promise<{ status: string }> => {
        executions += 1;
        await new Promise<void>((resolve) => setImmediate(resolve));
        return { status: 'created' };
      };

      const [first, second] = await Promise.all([
        service.execute(execution, work),
        service.execute(execution, work),
      ]);

      expect(executions).toBe(1);
      expect([first.replayed, second.replayed].sort()).toEqual([false, true]);
      expect(first.value).toEqual(second.value);
    });
  });

  it('isolates the same idempotency key across user scopes', async () => {
    await withIsolatedPostgresDatabase(async ({ database }) => {
      await migrateToLatest(database);
      const service = new IdempotencyService(new DatabaseTransactionService(database), clock);
      const key = IdempotencyKey.create('cross-user-key');
      let executions = 0;
      const work = (): Promise<{ sequence: string }> => {
        executions += 1;
        return Promise.resolve({ sequence: String(executions) });
      };

      const first = await service.execute(
        { scopeId: scopeA, operation, key, requestFingerprint: requestA },
        work,
      );
      const second = await service.execute(
        { scopeId: scopeB, operation, key, requestFingerprint: requestA },
        work,
      );

      expect(first.replayed).toBe(false);
      expect(second.replayed).toBe(false);
      expect(first.value).not.toEqual(second.value);
      expect(executions).toBe(2);
    });
  });

  it('enforces idempotency state and hash invariants in PostgreSQL', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);

      await expect(
        pool.query(
          `INSERT INTO mymoneymap.idempotency_keys
             (scope_id, operation, key_hash, request_hash, status, response, created_at, completed_at)
           VALUES ($1, 'synthetic.create', $2, $2, 'completed', NULL, now(), NULL)`,
          [scopeA.toString(), 'a'.repeat(64)],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.idempotency_keys
             (scope_id, operation, key_hash, request_hash, status, created_at)
           VALUES ($1, 'synthetic.create', 'raw-key', 'raw-request', 'in_progress', now())`,
          [scopeA.toString()],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });
});
