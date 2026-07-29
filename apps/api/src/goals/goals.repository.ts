import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Kysely, type Selectable, type Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema, GoalContributionsTable } from '../platform/database/database.types';
import type { RecurringRuleWrite } from '../recurrence/recurrence.repository';
import type {
  Goal,
  GoalContribution,
  GoalRecurringRule,
  GoalStatus,
  LockedGoal,
} from './goals.types';
import { deriveGoalProgress } from './goal-progress';
import { ExactDecimal } from '../platform/decimal/exact-decimal';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface GoalWrite {
  title: string;
  targetAmount: string;
  currency: string;
  deadline: string | null;
  priority: number;
  status: Exclude<GoalStatus, 'completed'>;
  categoryId: string | null;
}

export interface ContributionWrite {
  id: string;
  userId: string;
  goalId: string;
  journalEntryId: string;
  amount: string;
  currency: string;
  goalAmount: string;
  goalCurrency: string;
  occurredOn: string;
  note: string | null;
  correctsContributionId: string | null;
  createdAt: Date;
}

@Injectable()
export class GoalsRepository {
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

  async countUnarchived(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.goals')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .where('archived_at', 'is', null)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async list(userId: string, executor: Executor = this.database): Promise<Goal[]> {
    const rows = await executor
      .selectFrom('mymoneymap.goals as g')
      .innerJoin('mymoneymap.ledger_accounts as a', (join) =>
        join
          .onRef('a.user_id', '=', 'g.user_id')
          .onRef('a.module_reference_id', '=', 'g.id')
          .on('a.kind', '=', 'goal'),
      )
      .leftJoin('mymoneymap.categories as c', (join) =>
        join.onRef('c.id', '=', 'g.category_id').onRef('c.user_id', '=', 'g.user_id'),
      )
      .select([
        'g.id',
        'g.title',
        'g.target_amount',
        'g.currency',
        'g.deadline',
        'g.priority',
        'g.status',
        'g.category_id',
        'g.archived_at',
        'g.created_at',
        'g.updated_at',
        'c.label as category_label',
        'a.id as account_id',
      ])
      .where('g.user_id', '=', userId)
      .orderBy('g.archived_at', 'asc')
      .orderBy('g.priority')
      .orderBy('g.deadline')
      .orderBy('g.id')
      .execute();

    return Promise.all(
      rows.map(async (row) => {
        const [contributions, recurringRule] = await Promise.all([
          this.contributions(userId, row.id, executor),
          this.goalRule(userId, row.id, executor),
        ]);
        const targetAmount = exactText(row.target_amount);
        const derived = deriveGoalProgress(targetAmount, contributions);
        const currentAmount = derived.currentAmount;
        return {
          id: row.id,
          title: row.title,
          targetAmount,
          currentAmount,
          remainingAmount: derived.remainingAmount,
          progressPercent: derived.progressPercent,
          currency: row.currency,
          deadline: row.deadline === null ? null : dateText(row.deadline),
          priority: row.priority,
          status:
            ExactDecimal.create(currentAmount).compare(ExactDecimal.create(targetAmount)) === 0
              ? 'completed'
              : normalizeOpenStatus(row.status),
          categoryId: row.category_id,
          categoryLabel: row.category_label,
          archivedAt: row.archived_at?.toISOString() ?? null,
          recurringRule,
          contributions,
          createdAt: row.created_at.toISOString(),
          updatedAt: row.updated_at.toISOString(),
        };
      }),
    );
  }

  async goal(
    userId: string,
    goalId: string,
    executor: Executor = this.database,
  ): Promise<Goal | null> {
    return (await this.list(userId, executor)).find(({ id }) => id === goalId) ?? null;
  }

  async create(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    values: GoalWrite,
    now: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.goals')
      .values({
        id: goalId,
        user_id: userId,
        title: values.title,
        target_amount: values.targetAmount,
        currency: values.currency,
        deadline: values.deadline,
        priority: values.priority,
        status: values.status,
        category_id: values.categoryId,
        archived_at: null,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async lockGoal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
  ): Promise<LockedGoal | null> {
    const row = await transaction
      .selectFrom('mymoneymap.goals as g')
      .innerJoin('mymoneymap.ledger_accounts as a', (join) =>
        join
          .onRef('a.user_id', '=', 'g.user_id')
          .onRef('a.module_reference_id', '=', 'g.id')
          .on('a.kind', '=', 'goal'),
      )
      .select([
        'g.id',
        'g.user_id',
        'g.target_amount',
        'g.currency',
        'g.status',
        'g.archived_at',
        'g.title',
        'a.id as account_id',
      ])
      .where('g.id', '=', goalId)
      .where('g.user_id', '=', userId)
      .forUpdate('g')
      .executeTakeFirst();
    if (!row) return null;
    return {
      id: row.id,
      userId: row.user_id,
      targetAmount: exactText(row.target_amount),
      currency: row.currency,
      status: row.status,
      archivedAt: row.archived_at,
      title: row.title,
      ledgerAccountId: row.account_id,
      currentAmount: exactText(await this.currentAmount(transaction, userId, goalId)),
    };
  }

  async update(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    values: Partial<Omit<GoalWrite, 'status'>> & { status?: GoalStatus },
    now: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.goals')
      .set({
        ...(values.title === undefined ? {} : { title: values.title }),
        ...(values.targetAmount === undefined ? {} : { target_amount: values.targetAmount }),
        ...(values.currency === undefined ? {} : { currency: values.currency }),
        ...(values.deadline === undefined ? {} : { deadline: values.deadline }),
        ...(values.priority === undefined ? {} : { priority: values.priority }),
        ...(values.status === undefined ? {} : { status: values.status }),
        ...(values.categoryId === undefined ? {} : { category_id: values.categoryId }),
        updated_at: now,
      })
      .where('id', '=', goalId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  archive(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    archivedAt: Date | null,
    now: Date,
  ): Promise<unknown> {
    return transaction
      .updateTable('mymoneymap.goals')
      .set({ archived_at: archivedAt, updated_at: now })
      .where('id', '=', goalId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async references(
    userId: string,
    goalId: string,
    executor: Executor,
  ): Promise<{ contributions: number; recurringRules: number }> {
    const row = await executor
      .selectNoFrom((expression) => [
        expression
          .selectFrom('mymoneymap.goal_contributions')
          .select(({ fn }) => fn.countAll<number>().as('count'))
          .where('user_id', '=', userId)
          .where('goal_id', '=', goalId)
          .as('contributions'),
        expression
          .selectFrom('mymoneymap.recurring_rules')
          .select(({ fn }) => fn.countAll<number>().as('count'))
          .where('user_id', '=', userId)
          .where('goal_id', '=', goalId)
          .as('recurringRules'),
      ])
      .executeTakeFirstOrThrow();
    return {
      contributions: Number(row.contributions),
      recurringRules: Number(row.recurringRules),
    };
  }

  async unlinkRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    now: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.recurring_rules')
      .set({ goal_id: null, updated_at: now })
      .where('user_id', '=', userId)
      .where('goal_id', '=', goalId)
      .execute();
  }

  async deleteGoalAndAccount(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
  ): Promise<void> {
    await transaction
      .deleteFrom('mymoneymap.ledger_accounts')
      .where('user_id', '=', userId)
      .where('kind', '=', 'goal')
      .where('module_reference_id', '=', goalId)
      .execute();
    await transaction
      .deleteFrom('mymoneymap.goals')
      .where('id', '=', goalId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async categoryOwned(userId: string, categoryId: string, executor: Executor): Promise<boolean> {
    return (
      (await executor
        .selectFrom('mymoneymap.categories')
        .select('id')
        .where('id', '=', categoryId)
        .where('user_id', '=', userId)
        .executeTakeFirst()) !== undefined
    );
  }

  async currencyOwned(userId: string, currency: string, executor: Executor): Promise<boolean> {
    return (
      (await executor
        .selectFrom('mymoneymap.user_currencies')
        .select('code')
        .where('user_id', '=', userId)
        .where('code', '=', currency)
        .executeTakeFirst()) !== undefined
    );
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

  async contribution(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    contributionId: string,
  ): Promise<GoalContribution | null> {
    const row = await transaction
      .selectFrom('mymoneymap.goal_contributions')
      .selectAll()
      .where('id', '=', contributionId)
      .where('user_id', '=', userId)
      .where('goal_id', '=', goalId)
      .forUpdate()
      .executeTakeFirst();
    return row ? mapContribution(row) : null;
  }

  async insertContribution(
    transaction: Transaction<DatabaseSchema>,
    values: ContributionWrite,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.goal_contributions')
      .values({
        id: values.id,
        user_id: values.userId,
        goal_id: values.goalId,
        journal_entry_id: values.journalEntryId,
        amount: values.amount,
        currency: values.currency,
        goal_amount: values.goalAmount,
        goal_currency: values.goalCurrency,
        occurred_on: values.occurredOn,
        note: values.note,
        reversed_by_journal_entry_id: null,
        corrects_contribution_id: values.correctsContributionId,
        created_at: values.createdAt,
      })
      .execute();
  }

  async markReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    contributionId: string,
    reversalEntryId: string,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.goal_contributions')
      .set({ reversed_by_journal_entry_id: reversalEntryId })
      .where('id', '=', contributionId)
      .where('user_id', '=', userId)
      .where('reversed_by_journal_entry_id', 'is', null)
      .executeTakeFirstOrThrow();
  }

  async createGoalRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<void> {
    await transaction
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
        goal_id: goalId,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async updateGoalRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<boolean> {
    const row = await transaction
      .updateTable('mymoneymap.recurring_rules')
      .set({
        title: values.title,
        amount: values.amount,
        starts_on: values.startsOn,
        rrule: values.rrule,
        updated_at: now,
      })
      .where('user_id', '=', userId)
      .where('goal_id', '=', goalId)
      .returning('id')
      .executeTakeFirst();
    if (!row) return false;
    await transaction
      .deleteFrom('mymoneymap.recurring_occurrences')
      .where('user_id', '=', userId)
      .where('rule_id', '=', row.id)
      .execute();
    return true;
  }

  async deleteGoalRule(userId: string, goalId: string): Promise<boolean> {
    return (
      (await this.database
        .deleteFrom('mymoneymap.recurring_rules')
        .where('user_id', '=', userId)
        .where('goal_id', '=', goalId)
        .returning('id')
        .executeTakeFirst()) !== undefined
    );
  }

  private async currentAmount(executor: Executor, userId: string, goalId: string): Promise<string> {
    return (
      await executor
        .selectFrom('mymoneymap.goal_contributions')
        .select(sql<string>`coalesce(sum(goal_amount), 0)::text`.as('amount'))
        .where('user_id', '=', userId)
        .where('goal_id', '=', goalId)
        .where('reversed_by_journal_entry_id', 'is', null)
        .executeTakeFirstOrThrow()
    ).amount;
  }

  private async contributions(
    userId: string,
    goalId: string,
    executor: Executor,
  ): Promise<GoalContribution[]> {
    const rows = await executor
      .selectFrom('mymoneymap.goal_contributions')
      .selectAll()
      .where('user_id', '=', userId)
      .where('goal_id', '=', goalId)
      .orderBy('occurred_on')
      .orderBy('created_at')
      .orderBy('id')
      .execute();
    return rows.map(mapContribution);
  }

  private async goalRule(
    userId: string,
    goalId: string,
    executor: Executor,
  ): Promise<GoalRecurringRule | null> {
    const row = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .selectAll()
      .where('user_id', '=', userId)
      .where('goal_id', '=', goalId)
      .executeTakeFirst();
    return row
      ? {
          id: row.id,
          title: row.title,
          amount: exactText(row.amount),
          currency: row.currency,
          economicType: 'transfer',
          startsOn: dateText(row.starts_on),
          rrule: row.rrule,
          categoryId: null,
          categoryLabel: null,
          goalId: row.goal_id!,
          createdAt: row.created_at.toISOString(),
          updatedAt: row.updated_at.toISOString(),
        }
      : null;
  }
}

function mapContribution(row: Selectable<GoalContributionsTable>): GoalContribution {
  return {
    id: row.id,
    journalEntryId: row.journal_entry_id,
    amount: exactText(row.amount),
    currency: row.currency,
    goalAmount: exactText(row.goal_amount),
    goalCurrency: row.goal_currency,
    occurredOn: dateText(row.occurred_on),
    note: row.note,
    reversedByJournalEntryId: row.reversed_by_journal_entry_id,
    correctsContributionId: row.corrects_contribution_id,
    createdAt: row.created_at.toISOString(),
  };
}

function normalizeOpenStatus(status: GoalStatus): Exclude<GoalStatus, 'completed'> {
  return status === 'paused' ? 'paused' : 'active';
}

function exactText(value: string): string {
  return ExactDecimal.create(value).toString();
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}
