import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { CurrencyRepository } from '../src/currency/currency.repository';
import { FxConversionService } from '../src/currency/fx-conversion.service';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { LedgerRepository } from '../src/ledger/ledger.repository';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('ledger journal PostgreSQL invariants', () => {
  it('migrates up, creates default cash accounts, and rolls Step 06 back cleanly', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM mymoneymap.ledger_accounts
              WHERE user_id = $1 AND kind = 'cash'`,
            [userId],
          )
        ).rows[0]?.count,
      ).toBe('1');

      await migrateOneDown(database);
      await migrateOneDown(database);
      await migrateOneDown(database);
      expect(
        (
          await pool.query<{ name: string | null }>(
            `SELECT to_regclass('mymoneymap.journal_entries')::text AS name`,
          )
        ).rows[0]?.name,
      ).toBeNull();
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM information_schema.columns
              WHERE table_schema = 'mymoneymap'
                AND table_name = 'users'
                AND column_name = 'theme'`,
          )
        ).rows[0]?.count,
      ).toBe('1');
    });
  });

  it('enforces balance, positivity, currency, ownership, and immutable posted history', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userA = await insertUser(pool);
      const userB = await insertUser(pool);
      const cashA = await cashAccount(pool, userA);
      const entryId = randomUUID();

      await pool.query('BEGIN');
      await insertEntry(pool, entryId, userA);
      await pool.query(
        `INSERT INTO mymoneymap.journal_legs
           (id,entry_id,user_id,account_id,side,amount,currency,created_at)
         VALUES ($1,$2,$3,$4,'debit','10.00','HUF',now())`,
        [randomUUID(), entryId, userA, cashA],
      );
      await expect(pool.query('COMMIT')).rejects.toMatchObject({ code: '23514' });
      await pool.query('ROLLBACK');

      await expect(postRawIncome(pool, userA, cashA, '0', 'HUF')).rejects.toMatchObject({
        code: '23514',
      });
      await expect(postRawIncome(pool, userA, cashA, '10', 'huf')).rejects.toMatchObject({
        code: '23514',
      });
      await expect(postRawPair(pool, userA, cashA, 'credit', 'HUF', 'HUF')).rejects.toMatchObject({
        code: '23514',
      });
      await expect(postRawPair(pool, userA, cashA, 'debit', 'HUF', 'EUR')).rejects.toMatchObject({
        code: '23514',
      });
      const cashB = await cashAccount(pool, userB);
      await expect(postRawIncome(pool, userA, cashB, '10', 'HUF')).rejects.toMatchObject({
        code: '23503',
      });

      const posted = await postRawIncome(pool, userA, cashA, '10.123456789012', 'HUF');
      await expect(
        pool.query(`UPDATE mymoneymap.journal_entries SET note = 'changed' WHERE id = $1`, [
          posted,
        ]),
      ).rejects.toMatchObject({ code: '55000' });
      await expect(
        pool.query(`DELETE FROM mymoneymap.journal_legs WHERE entry_id = $1`, [posted]),
      ).rejects.toMatchObject({ code: '55000' });
    });
  });

  it('posts balanced transfers with zero external cash-flow effect and exact balances', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      const cash = await cashAccount(pool, userId);
      const goal = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.ledger_accounts
           (id,user_id,kind,module_reference_id,created_at)
         VALUES ($1,$2,'goal',$3,now())`,
        [goal, userId, randomUUID()],
      );
      const repository = new LedgerRepository(database);
      const fx = testFx(database);
      await database.transaction().execute(async (transaction) => {
        const entry = await repository.post(transaction, {
          userId,
          actorUserId: userId,
          economicType: 'internal_transfer',
          amount: '300.00',
          currency: 'HUF',
          postedOn: '2026-07-29',
          effectiveAt: new Date('2026-07-29T10:00:00.000Z'),
          createdAt: new Date('2026-07-29T10:00:01.000Z'),
          sourceAccountId: cash,
          destinationAccountId: goal,
          sourceModule: 'manual',
          idempotencyKeyHash: 'a'.repeat(64),
        });
        await fx.snapshotPostedEntry(
          transaction,
          entry,
          userId,
          '2026-07-29',
          new Date('2026-07-29T10:00:01.000Z'),
        );
      });
      expect(await repository.accountBalance(userId, cash, 'HUF')).toBe('-300.000000000000');
      expect(await repository.accountBalance(userId, goal, 'HUF')).toBe('300.000000000000');
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM mymoneymap.journal_legs l
               JOIN mymoneymap.journal_entries e ON e.id = l.entry_id
              WHERE e.user_id = $1
                AND e.economic_type = 'internal_transfer'
                AND l.account_id IS NULL`,
            [userId],
          )
        ).rows[0]?.count,
      ).toBe('0');
    });
  });

  it('accepts only an exact inverse as a reversal and reconciles the aggregate to zero', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      const repository = new LedgerRepository(database);
      const fx = testFx(database);
      const original = await database.transaction().execute(async (transaction) => {
        const entry = await repository.post(transaction, {
          userId,
          actorUserId: userId,
          economicType: 'external_expense',
          amount: '125.50',
          currency: 'HUF',
          postedOn: '2026-07-29',
          effectiveAt: new Date('2026-07-29T10:00:00.000Z'),
          createdAt: new Date('2026-07-29T10:00:01.000Z'),
          sourceModule: 'manual',
          idempotencyKeyHash: 'b'.repeat(64),
        });
        await fx.snapshotPostedEntry(
          transaction,
          entry,
          userId,
          '2026-07-29',
          new Date('2026-07-29T10:00:01.000Z'),
        );
        return entry;
      });
      const reversal = await database.transaction().execute(async (transaction) => {
        const entry = await repository.reverse(transaction, original, {
          userId,
          actorUserId: userId,
          postedOn: '2026-07-30',
          effectiveAt: new Date('2026-07-30T10:00:00.000Z'),
          createdAt: new Date('2026-07-30T10:00:01.000Z'),
          idempotencyKeyHash: 'c'.repeat(64),
        });
        await fx.copyReversalSnapshot(
          transaction,
          original.id,
          entry.id,
          userId,
          new Date('2026-07-30T10:00:01.000Z'),
        );
        return entry;
      });
      expect(reversal.reversesEntryId).toBe(original.id);
      expect(
        (
          await pool.query<{ net: string }>(
            `SELECT sum(CASE WHEN side = 'debit' THEN amount ELSE -amount END)::text AS net
               FROM mymoneymap.journal_legs
              WHERE entry_id IN ($1,$2)
                AND account_id IS NULL`,
            [original.id, reversal.id],
          )
        ).rows[0]?.net,
      ).toBe('0.000000000000');
    });
  });
});

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
       (id,email,password_hash,full_name,date_of_birth,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-hash','Synthetic User','1990-01-01',now(),now(),now())`,
    [id, `${id}@example.test`],
  );
  return id;
}

function testFx(
  database: ConstructorParameters<typeof CurrencyRepository>[0],
): FxConversionService {
  return new FxConversionService(
    new CurrencyRepository(database),
    new FixedClock(UtcInstant.create('2026-07-29T12:00:00.000Z')),
  );
}

async function cashAccount(pool: Pool, userId: string): Promise<string> {
  return (
    await pool.query<{ id: string }>(
      `SELECT id FROM mymoneymap.ledger_accounts WHERE user_id = $1 AND kind = 'cash'`,
      [userId],
    )
  ).rows[0]!.id;
}

async function insertEntry(pool: Pool, entryId: string, userId: string): Promise<void> {
  await pool.query(
    `INSERT INTO mymoneymap.journal_entries
       (id,user_id,economic_type,source_module,idempotency_key_hash,posted_on,effective_at,
        created_at,actor_user_id)
     VALUES ($1,$2,'external_income','manual',$3,'2026-07-29',now(),now(),$2)`,
    [entryId, userId, randomUUID().replaceAll('-', '').padEnd(64, 'a')],
  );
}

async function postRawIncome(
  pool: Pool,
  userId: string,
  accountId: string,
  amount: string,
  currency: string,
): Promise<string> {
  const entryId = randomUUID();
  await pool.query('BEGIN');
  try {
    await insertEntry(pool, entryId, userId);
    await pool.query(
      `INSERT INTO mymoneymap.journal_legs
         (id,entry_id,user_id,account_id,side,amount,currency,created_at)
       VALUES
         ($1,$2,$3,$4,'debit',$5,$6,now()),
         ($7,$2,$3,NULL,'credit',$5,$6,now())`,
      [randomUUID(), entryId, userId, accountId, amount, currency, randomUUID()],
    );
    await insertSnapshot(pool, entryId, userId, amount, currency);
    await pool.query('COMMIT');
    return entryId;
  } catch (error) {
    await pool.query('ROLLBACK');
    throw error;
  }
}

async function postRawPair(
  pool: Pool,
  userId: string,
  accountId: string,
  ownedSide: 'debit' | 'credit',
  ownedCurrency: string,
  externalCurrency: string,
): Promise<void> {
  const entryId = randomUUID();
  const externalSide = ownedSide === 'debit' ? 'credit' : 'debit';
  await pool.query('BEGIN');
  try {
    await insertEntry(pool, entryId, userId);
    await pool.query(
      `INSERT INTO mymoneymap.journal_legs
         (id,entry_id,user_id,account_id,side,amount,currency,created_at)
       VALUES
         ($1,$2,$3,$4,$5,'10.00',$6,now()),
         ($7,$2,$3,NULL,$8,'10.00',$9,now())`,
      [
        randomUUID(),
        entryId,
        userId,
        accountId,
        ownedSide,
        ownedCurrency,
        randomUUID(),
        externalSide,
        externalCurrency,
      ],
    );
    await insertSnapshot(pool, entryId, userId, '10.00', ownedCurrency);
    await pool.query('COMMIT');
  } catch (error) {
    await pool.query('ROLLBACK');
    throw error;
  }
}

async function insertSnapshot(
  pool: Pool,
  entryId: string,
  userId: string,
  amount: string,
  sourceCurrency: string,
): Promise<void> {
  const identity = sourceCurrency === 'HUF';
  await pool.query(
    `INSERT INTO mymoneymap.fx_conversion_snapshots
      (id,entry_id,user_id,source_currency,target_currency,source_amount,converted_amount,
       source_rate,target_rate,conversion_rate,provider,rate_at,fetched_at,status,
       precision,rounding_mode,created_at)
     VALUES
      ($1,$2,$3,$4,'HUF',$5,
       CASE WHEN $6 THEN $5::numeric ELSE NULL END,
       CASE WHEN $6 THEN 1 ELSE NULL END,
       CASE WHEN $6 THEN 1 ELSE NULL END,
       CASE WHEN $6 THEN 1 ELSE NULL END,
       CASE WHEN $6 THEN 'identity' ELSE NULL END,
       CASE WHEN $6 THEN '2026-07-29T00:00:00Z'::timestamptz ELSE NULL END,
       CASE WHEN $6 THEN now() ELSE NULL END,
       CASE WHEN $6 THEN 'available' ELSE 'unavailable' END,
       2,'HALF_EVEN',now())`,
    [randomUUID(), entryId, userId, sourceCurrency, amount, identity],
  );
}
