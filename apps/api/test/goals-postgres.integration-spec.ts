import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { CurrencyRepository } from '../src/currency/currency.repository';
import { FxConversionService } from '../src/currency/fx-conversion.service';
import { LedgerRepository } from '../src/ledger/ledger.repository';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('goals PostgreSQL invariants', () => {
  it('rejects a cross-user category relationship at the database boundary', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const ownerId = await insertUser(pool);
      const foreignId = await insertUser(pool);
      const foreignCategoryId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.categories
          (id,user_id,label,kind,color,created_at,updated_at)
         VALUES ($1,$2,'Foreign goal category','spending','#AABBCC',now(),now())`,
        [foreignCategoryId, foreignId],
      );

      await expect(
        pool.query(
          `INSERT INTO mymoneymap.goals
            (id,user_id,title,target_amount,currency,priority,status,category_id,created_at,updated_at)
           VALUES ($1,$2,'Invalid ownership','100','HUF',3,'active',$3,now(),now())`,
          [randomUUID(), ownerId, foreignCategoryId],
        ),
      ).rejects.toMatchObject({ code: '23514' });
    });
  });

  it('rolls back a balanced goal transfer when its derived amount would overfund the target', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = await insertUser(pool);
      const goalId = randomUUID();
      const ledger = new LedgerRepository(database);
      const fx = new FxConversionService(
        new CurrencyRepository(database),
        new FixedClock(UtcInstant.create('2026-07-29T12:00:00.000Z')),
      );
      await database.transaction().execute(async (transaction) => {
        await transaction
          .insertInto('mymoneymap.goals')
          .values({
            id: goalId,
            user_id: userId,
            title: 'Database overfund guard',
            target_amount: '100',
            currency: 'HUF',
            deadline: null,
            priority: 3,
            status: 'active',
            category_id: null,
            archived_at: null,
            created_at: new Date('2026-07-29T12:00:00.000Z'),
            updated_at: new Date('2026-07-29T12:00:00.000Z'),
          })
          .execute();
        await ledger.createModuleAccount(
          transaction,
          userId,
          'goal',
          goalId,
          new Date('2026-07-29T12:00:00.000Z'),
        );
      });

      const contributionId = randomUUID();
      await expect(
        database.transaction().execute(async (transaction) => {
          const accounts = await transaction
            .selectFrom('mymoneymap.ledger_accounts')
            .select(['id', 'kind'])
            .where('user_id', '=', userId)
            .where('kind', 'in', ['cash', 'goal'])
            .execute();
          const cash = accounts.find(({ kind }) => kind === 'cash')!;
          const goal = accounts.find(({ kind }) => kind === 'goal')!;
          const now = new Date('2026-07-29T12:00:00.000Z');
          const entry = await ledger.post(transaction, {
            userId,
            actorUserId: userId,
            economicType: 'internal_transfer',
            amount: '101',
            currency: 'HUF',
            postedOn: '2026-07-29',
            effectiveAt: now,
            createdAt: now,
            sourceAccountId: cash.id,
            destinationAccountId: goal.id,
            sourceModule: 'goals',
            sourceReferenceId: contributionId,
            idempotencyKeyHash: 'a'.repeat(64),
          });
          await fx.snapshotPostedEntry(transaction, entry, userId, '2026-07-29', now);
          await transaction
            .insertInto('mymoneymap.goal_contributions')
            .values({
              id: contributionId,
              user_id: userId,
              goal_id: goalId,
              journal_entry_id: entry.id,
              amount: '101',
              currency: 'HUF',
              goal_amount: '101',
              goal_currency: 'HUF',
              occurred_on: '2026-07-29',
              note: null,
              reversed_by_journal_entry_id: null,
              corrects_contribution_id: null,
              created_at: now,
            })
            .execute();
        }),
      ).rejects.toMatchObject({ code: '23514' });

      const persisted = await pool.query<{ contributions: string; journals: string }>(
        `SELECT
           (SELECT count(*)::text FROM mymoneymap.goal_contributions WHERE goal_id = $1)
             AS contributions,
           (SELECT count(*)::text FROM mymoneymap.journal_entries
             WHERE source_module = 'goals' AND source_reference_id = $2)
             AS journals`,
        [goalId, contributionId],
      );
      expect(persisted.rows[0]).toEqual({ contributions: '0', journals: '0' });
    });
  });
});

async function insertUser(pool: Pool): Promise<string> {
  const id = randomUUID();
  await pool.query(
    `INSERT INTO mymoneymap.users
      (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
     VALUES ($1,$2,'synthetic-not-a-real-password-hash','Synthetic Goal DB User',
             '1990-01-01','premium',now(),now(),now())`,
    [id, `goal-db-${id}@example.test`],
  );
  return id;
}
