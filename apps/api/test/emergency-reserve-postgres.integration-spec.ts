import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { FxConversionService } from '../src/currency/fx-conversion.service';
import { CurrencyRepository } from '../src/currency/currency.repository';
import { LedgerRepository } from '../src/ledger/ledger.repository';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);

describe('emergency reserve PostgreSQL invariants', () => {
  it('rejects cross-user and wrong-kind linked accounts at the database boundary', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const ownerId = await insertUser(pool);
      const foreignId = await insertUser(pool);
      const ledger = new LedgerRepository(database);
      const now = new Date('2026-07-29T12:00:00.000Z');
      const reserveAccount = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, ownerId, 'emergency_reserve', ownerId, now),
        );
      const foreignInvestment = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, foreignId, 'investment', randomUUID(), now),
        );
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.emergency_reserves
            (user_id,target_amount,currency,reserve_account_id,linked_investment_account_id,
             created_at,updated_at)
           VALUES ($1,'100','HUF',$2,$3,now(),now())`,
          [ownerId, reserveAccount, foreignInvestment],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('rolls back a journal and movement whose withdrawal would make allocation negative', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      const ledger = new LedgerRepository(database);
      const fx = new FxConversionService(
        new CurrencyRepository(database),
        new FixedClock(UtcInstant.create('2026-07-29T12:00:00.000Z')),
      );
      const now = new Date('2026-07-29T12:00:00.000Z');
      const reserveAccount = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, userId, 'emergency_reserve', userId, now),
        );
      await pool.query(
        `INSERT INTO mymoneymap.emergency_reserves
          (user_id,target_amount,currency,reserve_account_id,created_at,updated_at)
         VALUES ($1,'100','HUF',$2,now(),now())`,
        [userId, reserveAccount],
      );

      const movementId = randomUUID();
      await expect(
        database.transaction().execute(async (transaction) => {
          const cash = await transaction
            .selectFrom('mymoneymap.ledger_accounts')
            .select('id')
            .where('user_id', '=', userId)
            .where('kind', '=', 'cash')
            .executeTakeFirstOrThrow();
          const entry = await ledger.post(transaction, {
            userId,
            actorUserId: userId,
            economicType: 'internal_transfer',
            amount: '1',
            currency: 'HUF',
            postedOn: '2026-07-29',
            effectiveAt: now,
            createdAt: now,
            sourceAccountId: reserveAccount,
            destinationAccountId: cash.id,
            sourceModule: 'emergency_fund',
            sourceReferenceId: movementId,
            idempotencyKeyHash: 'a'.repeat(64),
          });
          await fx.snapshotPostedEntry(transaction, entry, userId, '2026-07-29', now);
          await transaction
            .insertInto('mymoneymap.emergency_reserve_movements')
            .values({
              id: movementId,
              user_id: userId,
              journal_entry_id: entry.id,
              holding_account_id: reserveAccount,
              direction: 'withdrawal',
              amount: '1',
              currency: 'HUF',
              reserve_amount: '1',
              reserve_currency: 'HUF',
              occurred_on: '2026-07-29',
              note: null,
              reversed_by_journal_entry_id: null,
              created_at: now,
            })
            .execute();
        }),
      ).rejects.toMatchObject({ code: '23514' });

      const counts = await pool.query<{ journals: string; movements: string }>(
        `SELECT
          (SELECT count(*)::text FROM mymoneymap.journal_entries
            WHERE source_module='emergency_fund') journals,
          (SELECT count(*)::text FROM mymoneymap.emergency_reserve_movements) movements`,
      );
      expect(counts.rows[0]).toEqual({ journals: '0', movements: '0' });
    });
  });
});

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-not-a-real-password-hash','Synthetic Emergency DB User',
             '1990-01-01','premium',now(),now(),now())`,
    [id, `emergency-db-${id}@example.test`],
  );
  return id;
}
