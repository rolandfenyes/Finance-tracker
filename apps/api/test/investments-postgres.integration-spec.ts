import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { LedgerRepository } from '../src/ledger/ledger.repository';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);

describe('generic investment PostgreSQL invariants', () => {
  it('migrates Step 14 up and rolls it back without disturbing Step 13', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await relation(pool, 'investments')).toBe('mymoneymap.investments');
      expect(await relation(pool, 'investment_movements')).toBe('mymoneymap.investment_movements');
      expect(await column(pool, 'recurring_rules', 'investment_id')).toBe(true);

      await migrateOneDown(database);
      expect(await relation(pool, 'investments')).toBeNull();
      expect(await relation(pool, 'investment_movements')).toBeNull();
      expect(await column(pool, 'recurring_rules', 'investment_id')).toBe(false);
      expect(await relation(pool, 'loans')).toBe('mymoneymap.loans');

      await migrateToLatest(database);
      expect(await relation(pool, 'investments')).toBe('mymoneymap.investments');
    });
  });

  it('rejects a cross-user account and negative scenario rate at the database boundary', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const ownerId = await insertUser(pool);
      const foreignId = await insertUser(pool);
      const ledger = new LedgerRepository(database);
      const now = new Date('2026-07-30T12:00:00.000Z');
      const investmentId = randomUUID();
      const foreignAccount = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, foreignId, 'investment', investmentId, now),
        );
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.investments
            (id,user_id,type,name,currency,scenario_annual_rate,scenario_frequency,
             scenario_version,account_id,created_at,updated_at)
           VALUES ($1,$2,'savings','Synthetic cross-user','HUF','0','monthly',
                   'nominal_compound_scenario_v1',$3,now(),now())`,
          [investmentId, ownerId, foreignAccount],
        ),
      ).rejects.toMatchObject({ code: '23503' });

      const ownedInvestment = randomUUID();
      const ownedAccount = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, ownerId, 'investment', ownedInvestment, now),
        );
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.investments
            (id,user_id,type,name,currency,scenario_annual_rate,scenario_frequency,
             scenario_version,account_id,created_at,updated_at)
           VALUES ($1,$2,'savings','Synthetic negative','HUF','-1','monthly',
                   'nominal_compound_scenario_v1',$3,now(),now())`,
          [ownedInvestment, ownerId, ownedAccount],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('rolls back a journal-linked withdrawal that would make derived balance negative', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      const ledger = new LedgerRepository(database);
      const now = new Date('2026-07-30T12:00:00.000Z');
      const investmentId = randomUUID();
      const accountId = await database
        .transaction()
        .execute((transaction) =>
          ledger.createModuleAccount(transaction, userId, 'investment', investmentId, now),
        );
      await pool.query(
        `INSERT INTO mymoneymap.investments
          (id,user_id,type,name,currency,scenario_annual_rate,scenario_frequency,
           scenario_version,account_id,created_at,updated_at)
         VALUES ($1,$2,'savings','Synthetic invariant','HUF','0','monthly',
                 'nominal_compound_scenario_v1',$3,now(),now())`,
        [investmentId, userId, accountId],
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
            postedOn: '2026-07-30',
            effectiveAt: now,
            createdAt: now,
            sourceAccountId: accountId,
            destinationAccountId: cash.id,
            sourceModule: 'investments',
            sourceReferenceId: movementId,
            idempotencyKeyHash: 'a'.repeat(64),
          });
          await transaction
            .insertInto('mymoneymap.investment_movements')
            .values({
              id: movementId,
              user_id: userId,
              investment_id: investmentId,
              journal_entry_id: entry.id,
              direction: 'withdrawal',
              amount: '1',
              currency: 'HUF',
              investment_amount: '1',
              investment_currency: 'HUF',
              occurred_on: '2026-07-30',
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
            WHERE source_module='investments') journals,
          (SELECT count(*)::text FROM mymoneymap.investment_movements) movements`,
      );
      expect(counts.rows[0]).toEqual({ journals: '0', movements: '0' });
    });
  });
});

async function relation(pool: Pool, name: string): Promise<string | null> {
  return (
    await pool.query<{ relation: string | null }>('SELECT to_regclass($1)::text relation', [
      `mymoneymap.${name}`,
    ])
  ).rows[0]!.relation;
}

async function column(pool: Pool, table: string, name: string): Promise<boolean> {
  return (
    await pool.query<{ present: boolean }>(
      `SELECT EXISTS (
         SELECT 1 FROM information_schema.columns
          WHERE table_schema='mymoneymap' AND table_name=$1 AND column_name=$2
       ) present`,
      [table, name],
    )
  ).rows[0]!.present;
}

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-not-a-real-password-hash','Synthetic Investment DB User',
             '1990-01-01','premium',now(),now(),now())`,
    [id, `investment-db-${id}@example.test`],
  );
  return id;
}
