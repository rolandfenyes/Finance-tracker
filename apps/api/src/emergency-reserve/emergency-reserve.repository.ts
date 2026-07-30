import { Inject, Injectable } from '@nestjs/common';
import { sql, type Kysely, type Transaction } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type { RecurringRule } from '../recurrence/recurrence.types';
import type {
  EmergencyReserveMovement,
  EmergencyReserveMovementDirection,
  LockedEmergencyReserve,
} from './emergency-reserve.types';
import { deriveEmergencyReserveBalance } from './emergency-reserve-calculator';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface EmergencyReserveMovementWrite {
  id: string;
  userId: string;
  journalEntryId: string;
  holdingAccountId: string;
  direction: EmergencyReserveMovementDirection;
  amount: string;
  currency: string;
  reserveAmount: string;
  reserveCurrency: string;
  occurredOn: string;
  note: string | null;
  createdAt: Date;
}

@Injectable()
export class EmergencyReserveRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async mainCurrency(userId: string, executor: Executor = this.database): Promise<string | null> {
    return (
      (
        await executor
          .selectFrom('mymoneymap.user_currencies')
          .select('code')
          .where('user_id', '=', userId)
          .where('is_main', '=', true)
          .executeTakeFirst()
      )?.code ?? null
    );
  }

  currencyOwned(userId: string, currency: string, executor: Executor): Promise<boolean> {
    return executor
      .selectFrom('mymoneymap.user_currencies')
      .select('code')
      .where('user_id', '=', userId)
      .where('code', '=', currency)
      .executeTakeFirst()
      .then(Boolean);
  }

  defaultCashAccount(transaction: Transaction<DatabaseSchema>, userId: string): Promise<string> {
    return transaction
      .selectFrom('mymoneymap.ledger_accounts')
      .select('id')
      .where('user_id', '=', userId)
      .where('kind', '=', 'cash')
      .executeTakeFirstOrThrow()
      .then(({ id }) => id);
  }

  async createConfiguration(
    transaction: Transaction<DatabaseSchema>,
    values: {
      userId: string;
      targetAmount: string;
      currency: string;
      reserveAccountId: string;
      linkedInvestmentAccountId: string | null;
      now: Date;
    },
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.emergency_reserves')
      .values({
        user_id: values.userId,
        target_amount: values.targetAmount,
        currency: values.currency,
        reserve_account_id: values.reserveAccountId,
        linked_investment_account_id: values.linkedInvestmentAccountId,
        created_at: values.now,
        updated_at: values.now,
      })
      .onConflict((conflict) => conflict.column('user_id').doNothing())
      .execute();
  }

  async updateConfiguration(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: {
      targetAmount: string;
      currency: string;
      linkedInvestmentAccountId: string | null;
      now: Date;
    },
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.emergency_reserves')
      .set({
        target_amount: values.targetAmount,
        currency: values.currency,
        linked_investment_account_id: values.linkedInvestmentAccountId,
        updated_at: values.now,
      })
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async lockConfiguration(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<LockedEmergencyReserve | null> {
    const row = await transaction
      .selectFrom('mymoneymap.emergency_reserves')
      .selectAll()
      .where('user_id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    if (!row) return null;
    const movements = await this.movements(userId, transaction);
    return {
      userId,
      targetAmount: row.target_amount,
      currency: row.currency,
      reserveAccountId: row.reserve_account_id,
      linkedInvestmentAccountId: row.linked_investment_account_id,
      holdingAccountId: row.linked_investment_account_id ?? row.reserve_account_id,
      currentAmount: deriveEmergencyReserveBalance(movements),
      createdAt: row.created_at,
      updatedAt: row.updated_at,
    };
  }

  async configuration(userId: string): Promise<{
    targetAmount: string;
    currency: string;
    reserveAccountId: string;
    linkedInvestmentAccountId: string | null;
    createdAt: Date;
    updatedAt: Date;
  } | null> {
    const row = await this.database
      .selectFrom('mymoneymap.emergency_reserves')
      .selectAll()
      .where('user_id', '=', userId)
      .executeTakeFirst();
    return row
      ? {
          targetAmount: row.target_amount,
          currency: row.currency,
          reserveAccountId: row.reserve_account_id,
          linkedInvestmentAccountId: row.linked_investment_account_id,
          createdAt: row.created_at,
          updatedAt: row.updated_at,
        }
      : null;
  }

  async movements(
    userId: string,
    executor: Executor = this.database,
  ): Promise<EmergencyReserveMovement[]> {
    const rows = await executor
      .selectFrom('mymoneymap.emergency_reserve_movements')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('occurred_on', 'desc')
      .orderBy('created_at', 'desc')
      .orderBy('id', 'desc')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      journalEntryId: row.journal_entry_id,
      holdingAccountId: row.holding_account_id,
      direction: row.direction,
      amount: row.amount,
      currency: row.currency,
      reserveAmount: row.reserve_amount,
      reserveCurrency: row.reserve_currency,
      occurredOn: dateText(row.occurred_on),
      note: row.note,
      reversedByJournalEntryId: row.reversed_by_journal_entry_id,
      createdAt: row.created_at.toISOString(),
    }));
  }

  async movement(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    movementId: string,
  ): Promise<EmergencyReserveMovement | null> {
    return (await this.movements(userId, transaction)).find(({ id }) => id === movementId) ?? null;
  }

  async insertMovement(
    transaction: Transaction<DatabaseSchema>,
    values: EmergencyReserveMovementWrite,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.emergency_reserve_movements')
      .values({
        id: values.id,
        user_id: values.userId,
        journal_entry_id: values.journalEntryId,
        holding_account_id: values.holdingAccountId,
        direction: values.direction,
        amount: values.amount,
        currency: values.currency,
        reserve_amount: values.reserveAmount,
        reserve_currency: values.reserveCurrency,
        occurred_on: values.occurredOn,
        note: values.note,
        reversed_by_journal_entry_id: null,
        created_at: values.createdAt,
      })
      .execute();
  }

  async markReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    movementId: string,
    reversalEntryId: string,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.emergency_reserve_movements')
      .set({ reversed_by_journal_entry_id: reversalEntryId })
      .where('id', '=', movementId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async investmentAccountOwned(
    userId: string,
    accountId: string,
    executor: Executor,
  ): Promise<boolean> {
    return executor
      .selectFrom('mymoneymap.ledger_accounts')
      .select('id')
      .where('id', '=', accountId)
      .where('user_id', '=', userId)
      .where('kind', '=', 'investment')
      .executeTakeFirst()
      .then(Boolean);
  }

  async recurringRules(
    userId: string,
    executor: Executor = this.database,
  ): Promise<RecurringRule[]> {
    const rows = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('id')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      title: row.title,
      amount: row.amount,
      currency: row.currency,
      economicType: row.economic_type,
      startsOn: dateText(row.starts_on),
      rrule: row.rrule,
      categoryId: row.category_id,
      categoryLabel: null,
      goalId: row.goal_id,
      loanId: row.loan_id,
      createdAt: row.created_at.toISOString(),
      updatedAt: row.updated_at.toISOString(),
    }));
  }

  async hasMovementHistory(userId: string, executor: Executor): Promise<boolean> {
    const row = await executor
      .selectFrom('mymoneymap.emergency_reserve_movements')
      .select(sql<number>`1`.as('present'))
      .where('user_id', '=', userId)
      .limit(1)
      .executeTakeFirst();
    return row !== undefined;
  }
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    String(value.getFullYear()).padStart(4, '0'),
    String(value.getMonth() + 1).padStart(2, '0'),
    String(value.getDate()).padStart(2, '0'),
  ].join('-');
}
