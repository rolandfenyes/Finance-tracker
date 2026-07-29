import { randomUUID } from 'node:crypto';
import { CurrencyRepository } from '../src/currency/currency.repository';
import { FxConversionService } from '../src/currency/fx-conversion.service';
import { LedgerRepository } from '../src/ledger/ledger.repository';
import type { PostJournalCommand } from '../src/ledger/ledger.types';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('currency and FX PostgreSQL contract', () => {
  it('creates one HUF main currency and preserves it through concurrent main updates', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const repository = new CurrencyRepository(database);
      const userId = await insertUser(pool, 'premium');
      expect(await repository.userCurrencies(userId)).toEqual([
        expect.objectContaining({ code: 'HUF', isMain: true, minorUnit: 2 }),
      ]);
      await repository.transaction(async (transaction) => {
        await repository.lockFinanceUser(transaction, userId);
        await repository.addUserCurrency(transaction, userId, 'EUR', new Date());
        await repository.addUserCurrency(transaction, userId, 'USD', new Date());
      });
      await Promise.all([
        repository.transaction(async (transaction) => {
          await repository.lockFinanceUser(transaction, userId);
          await repository.setMainCurrency(transaction, userId, 'EUR');
        }),
        repository.transaction(async (transaction) => {
          await repository.lockFinanceUser(transaction, userId);
          await repository.setMainCurrency(transaction, userId, 'USD');
        }),
      ]);
      const memberships = await repository.userCurrencies(userId);
      expect(memberships.filter(({ isMain }) => isMain)).toHaveLength(1);
      expect(['EUR', 'USD']).toContain(memberships.find(({ isMain }) => isMain)?.code);

      await expect(
        pool.query(
          `DELETE FROM mymoneymap.user_currencies
            WHERE user_id = $1 AND is_main`,
          [userId],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('uses exact EUR-pivot cross rates, target minor units, identity, stale as-of, and unavailable', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const repository = new CurrencyRepository(database);
      const conversion = new FxConversionService(repository, fixedClock());
      await insertQuote(pool, 'HUF', '400', '2026-07-24');
      await insertQuote(pool, 'USD', '1.25', '2026-07-24');

      await expect(conversion.convertObserved('800', 'HUF', 'USD', '2026-07-24')).resolves.toEqual(
        expect.objectContaining({
          status: 'available',
          convertedAmount: '2.5',
          sourceRate: '400',
          targetRate: '1.25',
          conversionRate: '0.003125',
          provider: 'frankfurter',
          precision: 2,
        }),
      );
      await expect(
        conversion.convertObserved('100.005', 'EUR', 'EUR', '2026-07-27'),
      ).resolves.toEqual(
        expect.objectContaining({
          status: 'available',
          convertedAmount: '100',
          conversionRate: '1',
          provider: 'identity',
        }),
      );
      await expect(conversion.convertObserved('800', 'HUF', 'USD', '2026-07-26')).resolves.toEqual(
        expect.objectContaining({
          status: 'stale',
          rateAt: '2026-07-24T00:00:00.000Z',
        }),
      );
      const missing = await conversion.convertObserved('800', 'HUF', 'GBP', '2026-07-26');
      expect(missing).toEqual({
        status: 'unavailable',
        sourceAmount: '800',
        sourceCurrency: 'HUF',
        targetCurrency: 'GBP',
        precision: 2,
        roundingMode: 'HALF_EVEN',
      });
      expect(missing).not.toHaveProperty('convertedAmount');
      const future = await conversion.convertObserved('800', 'HUF', 'USD', '2026-07-30');
      expect(future.status).toBe('unavailable');
      expect(future).not.toHaveProperty('convertedAmount');

      await expect(
        pool.query(
          `INSERT INTO mymoneymap.fx_quotes
            (id,provider,base_code,quote_code,rate,observed_on,observed_at,fetched_at,quality,status)
           VALUES ($1,'frankfurter','EUR','USD',0,'2026-07-25','2026-07-25T00:00:00Z',
                   '2026-07-25T01:00:00Z','provider_observed','available')`,
          [randomUUID()],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('stores immutable posting snapshots and copies original provenance to reversals', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool, 'premium');
      const currencies = new CurrencyRepository(database);
      const fx = new FxConversionService(currencies, fixedClock());
      const ledger = new LedgerRepository(database);
      await insertQuote(pool, 'USD', '1.25', '2026-07-24');
      await insertQuote(pool, 'HUF', '400', '2026-07-24');
      const original = await database.transaction().execute(async (transaction) => {
        const entry = await ledger.post(transaction, command(userId, 'USD', '125', '2026-07-24'));
        await fx.snapshotPostedEntry(
          transaction,
          entry,
          userId,
          '2026-07-24',
          new Date('2026-07-24T12:00:00Z'),
        );
        return ledger.findOwnedEntry(transaction, userId, entry.id);
      });
      expect(original.conversion).toMatchObject({
        status: 'available',
        convertedAmount: '40000.000000000000',
        sourceCurrency: 'USD',
        targetCurrency: 'HUF',
        rateAt: '2026-07-24T00:00:00.000Z',
      });

      await insertQuote(pool, 'HUF', '500', '2026-07-25');
      const reversal = await database.transaction().execute(async (transaction) => {
        const entry = await ledger.reverse(transaction, original, {
          userId,
          actorUserId: userId,
          postedOn: '2026-07-25',
          effectiveAt: new Date('2026-07-25T12:00:00Z'),
          createdAt: new Date('2026-07-25T12:00:00Z'),
          idempotencyKeyHash: 'b'.repeat(64),
        });
        await fx.copyReversalSnapshot(
          transaction,
          original.id,
          entry.id,
          userId,
          new Date('2026-07-25T12:00:00Z'),
        );
        return ledger.findOwnedEntry(transaction, userId, entry.id);
      });
      expect(reversal.conversion).toEqual(original.conversion);
      await expect(
        pool.query(
          'UPDATE mymoneymap.fx_conversion_snapshots SET converted_amount = 1 WHERE entry_id = $1',
          [original.id],
        ),
      ).rejects.toMatchObject({ code: '55000' });
    });
  });
});

async function insertUser(
  pool: { query: (sql: string, parameters?: unknown[]) => Promise<unknown> },
  role: 'free' | 'premium',
): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic FX User','1990-01-01',$3,now(),now(),now())`,
    [id, `${id}@example.test`, role],
  );
  return id;
}

async function insertQuote(
  pool: { query: (sql: string, parameters?: unknown[]) => Promise<unknown> },
  currency: string,
  rate: string,
  date: string,
): Promise<void> {
  await pool.query(
    `INSERT INTO mymoneymap.fx_quotes
      (id,provider,base_code,quote_code,rate,observed_on,observed_at,fetched_at,quality,status)
     VALUES ($1,'frankfurter','EUR',$2,$3,$4,$4::date::timestamptz,
             ($4::date + interval '12 hours')::timestamptz,'provider_observed','available')
     ON CONFLICT (provider,base_code,quote_code,observed_on)
     DO UPDATE SET rate = excluded.rate, fetched_at = excluded.fetched_at`,
    [randomUUID(), currency, rate, date],
  );
}

function command(
  userId: string,
  currency: string,
  amount: string,
  postedOn: string,
): PostJournalCommand {
  return {
    userId,
    actorUserId: userId,
    economicType: 'external_income' as const,
    amount,
    currency,
    postedOn,
    effectiveAt: new Date(`${postedOn}T12:00:00Z`),
    createdAt: new Date(`${postedOn}T12:00:00Z`),
    sourceModule: 'manual' as const,
    idempotencyKeyHash: 'a'.repeat(64),
  };
}

function fixedClock(): FixedClock {
  return new FixedClock(UtcInstant.create('2026-07-29T12:00:00.000Z'));
}
