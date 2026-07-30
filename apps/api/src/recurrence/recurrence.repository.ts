import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Kysely, type Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type {
  RecurrenceEconomicType,
  RecurrenceJobExecution,
  RecurrenceJobStatus,
  RecurringRule,
} from './recurrence.types';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface RecurringRuleWrite {
  title: string;
  amount: string;
  currency: string;
  economicType: RecurrenceEconomicType;
  startsOn: string;
  rrule: string;
  categoryId: string | null;
  goalId?: string | null;
  loanId?: string | null;
  investmentId?: string | null;
}

export interface MaterializedRule {
  id: string;
  userId: string;
  amount: string;
  currency: string;
  economicType: RecurrenceEconomicType;
  startsOn: string;
  rrule: string;
  categoryId: string | null;
  loanId: string | null;
  investmentId: string | null;
}

@Injectable()
export class RecurrenceRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async lockUser(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<{ role: UserRole } | null> {
    const row = await transaction
      .selectFrom('mymoneymap.users')
      .select('role')
      .where('id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    return row ?? null;
  }

  async listRules(userId: string, executor: Executor = this.database): Promise<RecurringRule[]> {
    const rows = await executor
      .selectFrom('mymoneymap.recurring_rules as r')
      .leftJoin('mymoneymap.categories as c', (join) =>
        join.onRef('c.id', '=', 'r.category_id').onRef('c.user_id', '=', 'r.user_id'),
      )
      .select([
        'r.id',
        'r.title',
        'r.amount',
        'r.currency',
        'r.economic_type',
        'r.starts_on',
        'r.rrule',
        'r.category_id',
        'r.goal_id',
        'r.loan_id',
        'r.investment_id',
        'r.created_at',
        'r.updated_at',
        'c.label as category_label',
      ])
      .where('r.user_id', '=', userId)
      .orderBy('r.starts_on')
      .orderBy(sql`lower(r.title)`)
      .orderBy('r.id')
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
      categoryLabel: row.category_label,
      goalId: row.goal_id,
      loanId: row.loan_id,
      investmentId: row.investment_id,
      createdAt: row.created_at.toISOString(),
      updatedAt: row.updated_at.toISOString(),
    }));
  }

  async rule(
    userId: string,
    ruleId: string,
    executor: Executor = this.database,
  ): Promise<RecurringRule | null> {
    return (await this.listRules(userId, executor)).find(({ id }) => id === ruleId) ?? null;
  }

  async countRules(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async createRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<string> {
    const id = randomUUID();
    await transaction
      .insertInto('mymoneymap.recurring_rules')
      .values({
        id,
        user_id: userId,
        title: values.title,
        amount: values.amount,
        currency: values.currency,
        economic_type: values.economicType,
        starts_on: values.startsOn,
        rrule: values.rrule,
        category_id: values.categoryId,
        goal_id: values.goalId ?? null,
        loan_id: values.loanId ?? null,
        investment_id: values.investmentId ?? null,
        created_at: now,
        updated_at: now,
      })
      .execute();
    return id;
  }

  async updateRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    ruleId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<boolean> {
    const updated = await transaction
      .updateTable('mymoneymap.recurring_rules')
      .set({
        title: values.title,
        amount: values.amount,
        currency: values.currency,
        economic_type: values.economicType,
        starts_on: values.startsOn,
        rrule: values.rrule,
        category_id: values.categoryId,
        updated_at: now,
      })
      .where('id', '=', ruleId)
      .where('user_id', '=', userId)
      .returning('id')
      .executeTakeFirst();
    if (!updated) return false;
    await transaction
      .deleteFrom('mymoneymap.recurring_occurrences')
      .where('rule_id', '=', ruleId)
      .where('user_id', '=', userId)
      .execute();
    return true;
  }

  async deleteRule(userId: string, ruleId: string): Promise<boolean> {
    return (
      (await this.database
        .deleteFrom('mymoneymap.recurring_rules')
        .where('id', '=', ruleId)
        .where('user_id', '=', userId)
        .returning('id')
        .executeTakeFirst()) !== undefined
    );
  }

  async currencyMembershipExists(
    userId: string,
    currency: string,
    executor: Executor,
  ): Promise<boolean> {
    return (
      (await executor
        .selectFrom('mymoneymap.user_currencies')
        .select('code')
        .where('user_id', '=', userId)
        .where('code', '=', currency)
        .executeTakeFirst()) !== undefined
    );
  }

  async categoryMatches(
    userId: string,
    categoryId: string,
    economicType: RecurrenceEconomicType,
    executor: Executor,
  ): Promise<boolean> {
    if (economicType === 'transfer') return false;
    const expected = economicType === 'income' ? 'income' : 'spending';
    return (
      (await executor
        .selectFrom('mymoneymap.categories')
        .select('id')
        .where('id', '=', categoryId)
        .where('user_id', '=', userId)
        .where('kind', '=', expected)
        .executeTakeFirst()) !== undefined
    );
  }

  async prepareExecution(
    jobKey: string,
    queueJobId: string,
    dueThrough: string,
    maxAttempts: number,
    now: Date,
  ): Promise<RecurrenceJobExecution> {
    return this.transaction(async (transaction) => {
      const id = randomUUID();
      const inserted = await transaction
        .insertInto('mymoneymap.recurrence_job_executions')
        .values({
          id,
          job_key: jobKey,
          queue_job_id: queueJobId,
          due_through: dueThrough,
          status: 'queued',
          attempt_count: 0,
          max_attempts: maxAttempts,
          error_code: null,
          started_at: null,
          finished_at: null,
          created_at: now,
          updated_at: now,
        })
        .onConflict((conflict) => conflict.column('job_key').doNothing())
        .returningAll()
        .executeTakeFirst();
      if (inserted) {
        await this.insertEvent(transaction, id, 'queued', 0, null, now);
        return mapExecution(inserted);
      }
      const existing = await transaction
        .selectFrom('mymoneymap.recurrence_job_executions')
        .selectAll()
        .where('job_key', '=', jobKey)
        .forUpdate()
        .executeTakeFirstOrThrow();
      return mapExecution(existing);
    });
  }

  async claimExecution(jobKey: string, attempt: number, now: Date): Promise<string | null> {
    return this.transaction(async (transaction) => {
      const execution = await transaction
        .selectFrom('mymoneymap.recurrence_job_executions')
        .selectAll()
        .where('job_key', '=', jobKey)
        .forUpdate()
        .executeTakeFirst();
      if (
        !execution ||
        execution.status === 'completed' ||
        execution.status === 'dead_letter' ||
        attempt <= execution.attempt_count
      ) {
        return null;
      }
      await transaction
        .updateTable('mymoneymap.recurrence_job_executions')
        .set({
          status: 'running',
          attempt_count: attempt,
          error_code: null,
          started_at: now,
          finished_at: null,
          updated_at: now,
        })
        .where('id', '=', execution.id)
        .execute();
      await this.insertEvent(transaction, execution.id, 'running', attempt, null, now);
      return execution.id;
    });
  }

  materializedRules(
    transaction: Transaction<DatabaseSchema>,
    dueThrough: string,
  ): Promise<MaterializedRule[]> {
    return transaction
      .selectFrom('mymoneymap.recurring_rules as r')
      .leftJoin('mymoneymap.goals as g', (join) =>
        join.onRef('g.id', '=', 'r.goal_id').onRef('g.user_id', '=', 'r.user_id'),
      )
      .leftJoin('mymoneymap.loans as l', (join) =>
        join.onRef('l.id', '=', 'r.loan_id').onRef('l.user_id', '=', 'r.user_id'),
      )
      .select([
        'r.id',
        'r.user_id as userId',
        'r.amount',
        'r.currency',
        'r.economic_type as economicType',
        'r.starts_on as startsOn',
        'r.rrule',
        'r.category_id as categoryId',
        'r.loan_id as loanId',
        'r.investment_id as investmentId',
      ])
      .where('r.starts_on', '<=', dueThrough)
      .where((expression) =>
        expression.or([
          expression('r.goal_id', 'is', null),
          expression.and([
            expression('g.archived_at', 'is', null),
            expression('g.status', '!=', 'completed'),
          ]),
        ]),
      )
      .where((expression) =>
        expression.or([
          expression('r.loan_id', 'is', null),
          expression.and([
            expression('l.completed_at', 'is', null),
            expression('l.archived_at', 'is', null),
          ]),
        ]),
      )
      .orderBy('r.user_id')
      .orderBy('r.id')
      .execute()
      .then((rows) =>
        rows.map((row) => ({
          ...row,
          startsOn: dateText(row.startsOn),
        })),
      );
  }

  async insertOccurrence(
    transaction: Transaction<DatabaseSchema>,
    rule: MaterializedRule,
    dueOn: string,
    executionId: string,
    now: Date,
  ): Promise<{ id: string; inserted: boolean }> {
    const id = randomUUID();
    const row = await transaction
      .insertInto('mymoneymap.recurring_occurrences')
      .values({
        id,
        rule_id: rule.id,
        user_id: rule.userId,
        due_on: dueOn,
        economic_type: rule.economicType,
        amount: rule.amount,
        currency: rule.currency,
        category_id: rule.categoryId,
        state: 'forecast',
        job_execution_id: executionId,
        created_at: now,
      })
      .onConflict((conflict) => conflict.columns(['rule_id', 'due_on']).doNothing())
      .returning('id')
      .executeTakeFirst();
    return { id, inserted: row !== undefined };
  }

  async completeExecution(
    transaction: Transaction<DatabaseSchema>,
    executionId: string,
    attempt: number,
    now: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.recurrence_job_executions')
      .set({
        status: 'completed',
        error_code: null,
        finished_at: now,
        updated_at: now,
      })
      .where('id', '=', executionId)
      .execute();
    await this.insertEvent(transaction, executionId, 'completed', attempt, null, now);
  }

  async recordFailure(
    jobKey: string,
    attempt: number,
    errorCode: string,
    now: Date,
  ): Promise<void> {
    await this.transaction(async (transaction) => {
      const execution = await transaction
        .selectFrom('mymoneymap.recurrence_job_executions')
        .selectAll()
        .where('job_key', '=', jobKey)
        .forUpdate()
        .executeTakeFirst();
      if (!execution || execution.status === 'completed') return;
      const status: RecurrenceJobStatus =
        attempt >= execution.max_attempts ? 'dead_letter' : 'retryable_failed';
      await transaction
        .updateTable('mymoneymap.recurrence_job_executions')
        .set({
          status,
          attempt_count: Math.min(attempt, execution.max_attempts),
          error_code: errorCode,
          finished_at: status === 'dead_letter' ? now : null,
          updated_at: now,
        })
        .where('id', '=', execution.id)
        .execute();
      await this.insertEvent(
        transaction,
        execution.id,
        status,
        Math.min(attempt, execution.max_attempts),
        errorCode,
        now,
      );
    });
  }

  async execution(jobKey: string): Promise<RecurrenceJobExecution | null> {
    const row = await this.database
      .selectFrom('mymoneymap.recurrence_job_executions')
      .selectAll()
      .where('job_key', '=', jobKey)
      .executeTakeFirst();
    return row ? mapExecution(row) : null;
  }

  private async insertEvent(
    transaction: Transaction<DatabaseSchema>,
    executionId: string,
    status: RecurrenceJobStatus,
    attempt: number,
    errorCode: string | null,
    now: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.recurrence_job_events')
      .values({
        id: randomUUID(),
        execution_id: executionId,
        status,
        attempt,
        error_code: errorCode,
        occurred_at: now,
      })
      .execute();
  }
}

function mapExecution(row: {
  id: string;
  job_key: string;
  queue_job_id: string;
  due_through: string | Date;
  status: RecurrenceJobStatus;
  attempt_count: number;
  max_attempts: number;
  error_code: string | null;
  started_at: Date | null;
  finished_at: Date | null;
  created_at: Date;
  updated_at: Date;
}): RecurrenceJobExecution {
  return {
    id: row.id,
    jobKey: row.job_key,
    queueJobId: row.queue_job_id,
    dueThrough: dateText(row.due_through),
    status: row.status,
    attemptCount: row.attempt_count,
    maxAttempts: row.max_attempts,
    errorCode: row.error_code,
    startedAt: row.started_at?.toISOString() ?? null,
    finishedAt: row.finished_at?.toISOString() ?? null,
    createdAt: row.created_at.toISOString(),
    updatedAt: row.updated_at.toISOString(),
  };
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}
