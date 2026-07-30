import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Kysely, type Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type { RecurringRuleWrite } from '../recurrence/recurrence.repository';
import type {
  InvestmentMovement,
  InvestmentMovementDirection,
  InvestmentRecord,
  InvestmentRecurringRule,
  InvestmentType,
} from './investments.types';
import type { InvestmentFrequency } from './investment-calculator';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface InvestmentWrite {
  type: InvestmentType;
  name: string;
  provider: string | null;
  identifier: string | null;
  notes: string | null;
  currency: string;
  scenarioAnnualRate: string | null;
  scenarioFrequency: InvestmentFrequency;
}

export interface InvestmentMovementWrite {
  id: string;
  userId: string;
  investmentId: string;
  journalEntryId: string;
  direction: InvestmentMovementDirection;
  amount: string;
  currency: string;
  investmentAmount: string;
  investmentCurrency: string;
  occurredOn: string;
  note: string | null;
  createdAt: Date;
}

@Injectable()
export class InvestmentsRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async lockUser(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<{ role: UserRole } | null> {
    return (
      (await transaction
        .selectFrom('mymoneymap.users')
        .select('role')
        .where('id', '=', userId)
        .forUpdate()
        .executeTakeFirst()) ?? null
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

  async currencyPolicy(
    currency: string,
    executor: Executor = this.database,
  ): Promise<{
    minorUnit: number;
    roundingMode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN';
  }> {
    const row = await executor
      .selectFrom('mymoneymap.currencies')
      .select(['minor_unit', 'rounding_mode'])
      .where('code', '=', currency)
      .executeTakeFirstOrThrow();
    return { minorUnit: row.minor_unit, roundingMode: row.rounding_mode };
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

  create(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    id: string,
    accountId: string,
    values: InvestmentWrite,
    now: Date,
  ): Promise<unknown> {
    return transaction
      .insertInto('mymoneymap.investments')
      .values({
        id,
        user_id: userId,
        type: values.type,
        name: values.name,
        provider: values.provider,
        identifier: values.identifier,
        notes: values.notes,
        currency: values.currency,
        scenario_annual_rate: values.scenarioAnnualRate,
        scenario_frequency: values.scenarioFrequency,
        scenario_version: 'nominal_compound_scenario_v1',
        account_id: accountId,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async list(userId: string, executor: Executor = this.database): Promise<InvestmentRecord[]> {
    const rows = await executor
      .selectFrom('mymoneymap.investments')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('created_at', 'desc')
      .orderBy(sql`lower(name)`)
      .orderBy('id')
      .execute();
    return Promise.all(
      rows.map(async (row) => ({
        id: row.id,
        type: row.type,
        name: row.name,
        provider: row.provider,
        identifier: row.identifier,
        notes: row.notes,
        currency: row.currency,
        scenarioAnnualRate: row.scenario_annual_rate,
        scenarioFrequency: row.scenario_frequency,
        accountId: row.account_id,
        movements: await this.movements(userId, row.id, executor),
        recurringRule: await this.rule(userId, row.id, executor),
        createdAt: row.created_at.toISOString(),
        updatedAt: row.updated_at.toISOString(),
      })),
    );
  }

  async investment(
    userId: string,
    investmentId: string,
    executor: Executor,
  ): Promise<InvestmentRecord | null> {
    return (await this.list(userId, executor)).find(({ id }) => id === investmentId) ?? null;
  }

  async lockInvestment(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
  ): Promise<InvestmentRecord | null> {
    const exists = await transaction
      .selectFrom('mymoneymap.investments')
      .select('id')
      .where('id', '=', investmentId)
      .where('user_id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    return exists ? this.investment(userId, investmentId, transaction) : null;
  }

  update(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
    values: Partial<InvestmentWrite>,
    now: Date,
  ): Promise<unknown> {
    return transaction
      .updateTable('mymoneymap.investments')
      .set({
        ...(values.type === undefined ? {} : { type: values.type }),
        ...(values.name === undefined ? {} : { name: values.name }),
        ...(values.provider === undefined ? {} : { provider: values.provider }),
        ...(values.identifier === undefined ? {} : { identifier: values.identifier }),
        ...(values.notes === undefined ? {} : { notes: values.notes }),
        ...(values.scenarioAnnualRate === undefined
          ? {}
          : { scenario_annual_rate: values.scenarioAnnualRate }),
        ...(values.scenarioFrequency === undefined
          ? {}
          : { scenario_frequency: values.scenarioFrequency }),
        updated_at: now,
      })
      .where('id', '=', investmentId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async deleteEmpty(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
    accountId: string,
  ): Promise<void> {
    await transaction
      .deleteFrom('mymoneymap.recurring_rules')
      .where('user_id', '=', userId)
      .where('investment_id', '=', investmentId)
      .execute();
    await transaction
      .deleteFrom('mymoneymap.investments')
      .where('id', '=', investmentId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    await transaction
      .deleteFrom('mymoneymap.ledger_accounts')
      .where('id', '=', accountId)
      .where('user_id', '=', userId)
      .execute();
  }

  async isEmergencyLinked(userId: string, accountId: string, executor: Executor): Promise<boolean> {
    return executor
      .selectFrom('mymoneymap.emergency_reserves')
      .select('user_id')
      .where('user_id', '=', userId)
      .where('linked_investment_account_id', '=', accountId)
      .executeTakeFirst()
      .then(Boolean);
  }

  async movements(
    userId: string,
    investmentId: string,
    executor: Executor,
  ): Promise<InvestmentMovement[]> {
    const rows = await executor
      .selectFrom('mymoneymap.investment_movements')
      .selectAll()
      .where('user_id', '=', userId)
      .where('investment_id', '=', investmentId)
      .orderBy('occurred_on', 'desc')
      .orderBy('created_at', 'desc')
      .orderBy('id', 'desc')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      journalEntryId: row.journal_entry_id,
      direction: row.direction,
      amount: row.amount,
      currency: row.currency,
      investmentAmount: row.investment_amount,
      investmentCurrency: row.investment_currency,
      occurredOn: dateText(row.occurred_on),
      note: row.note,
      reversedByJournalEntryId: row.reversed_by_journal_entry_id,
      createdAt: row.created_at.toISOString(),
    }));
  }

  movement(
    userId: string,
    investmentId: string,
    movementId: string,
    executor: Executor,
  ): Promise<InvestmentMovement | null> {
    return this.movements(userId, investmentId, executor).then(
      (items) => items.find(({ id }) => id === movementId) ?? null,
    );
  }

  insertMovement(
    transaction: Transaction<DatabaseSchema>,
    values: InvestmentMovementWrite,
  ): Promise<unknown> {
    return transaction
      .insertInto('mymoneymap.investment_movements')
      .values({
        id: values.id,
        user_id: values.userId,
        investment_id: values.investmentId,
        journal_entry_id: values.journalEntryId,
        direction: values.direction,
        amount: values.amount,
        currency: values.currency,
        investment_amount: values.investmentAmount,
        investment_currency: values.investmentCurrency,
        occurred_on: values.occurredOn,
        note: values.note,
        reversed_by_journal_entry_id: null,
        created_at: values.createdAt,
      })
      .execute();
  }

  markReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    movementId: string,
    reversalEntryId: string,
  ): Promise<unknown> {
    return transaction
      .updateTable('mymoneymap.investment_movements')
      .set({ reversed_by_journal_entry_id: reversalEntryId })
      .where('id', '=', movementId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async rule(
    userId: string,
    investmentId: string,
    executor: Executor,
  ): Promise<InvestmentRecurringRule | null> {
    const row = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .selectAll()
      .where('user_id', '=', userId)
      .where('investment_id', '=', investmentId)
      .executeTakeFirst();
    return row
      ? {
          id: row.id,
          title: row.title,
          amount: row.amount,
          currency: row.currency,
          economicType: 'transfer',
          startsOn: dateText(row.starts_on),
          rrule: row.rrule,
          investmentId,
          createdAt: row.created_at.toISOString(),
          updatedAt: row.updated_at.toISOString(),
        }
      : null;
  }

  countRules(userId: string, executor: Executor): Promise<number> {
    return executor
      .selectFrom('mymoneymap.recurring_rules')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow()
      .then(({ count }) => Number(count));
  }

  createRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<unknown> {
    return transaction
      .insertInto('mymoneymap.recurring_rules')
      .values({
        id: randomUUID(),
        user_id: userId,
        title: values.title,
        amount: values.amount,
        currency: values.currency,
        economic_type: 'transfer',
        starts_on: values.startsOn,
        rrule: values.rrule,
        category_id: null,
        goal_id: null,
        loan_id: null,
        investment_id: values.investmentId ?? null,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async deleteRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
  ): Promise<boolean> {
    const rule = await transaction
      .selectFrom('mymoneymap.recurring_rules')
      .select('id')
      .where('user_id', '=', userId)
      .where('investment_id', '=', investmentId)
      .executeTakeFirst();
    if (!rule) return false;
    await transaction
      .deleteFrom('mymoneymap.recurring_occurrences')
      .where('rule_id', '=', rule.id)
      .where('user_id', '=', userId)
      .execute();
    await transaction
      .deleteFrom('mymoneymap.recurring_rules')
      .where('id', '=', rule.id)
      .where('user_id', '=', userId)
      .execute();
    return true;
  }
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return value.toISOString().slice(0, 10);
}
