import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Kysely, type Transaction } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type { UserRole } from '../identity/identity.types';
import type { BasicIncome, BudgetRule, Category, CategoryKind } from './budgeting.types';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

interface LockedUser {
  role: UserRole;
  onboardStep: number;
}

export interface ActiveBasicIncome {
  amount: string;
  currency: string;
}

@Injectable()
export class BudgetingRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async lockUser(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<LockedUser | null> {
    const row = await transaction
      .selectFrom('mymoneymap.users')
      .select(['role', 'onboard_step'])
      .where('id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    return row ? { role: row.role, onboardStep: row.onboard_step } : null;
  }

  async listRules(userId: string, executor: Executor = this.database): Promise<BudgetRule[]> {
    const [rules, assignments] = await Promise.all([
      executor
        .selectFrom('mymoneymap.budget_rules')
        .selectAll()
        .where('user_id', '=', userId)
        .orderBy(sql`lower(label)`)
        .orderBy('id')
        .execute(),
      executor
        .selectFrom('mymoneymap.categories')
        .select(['id', 'budget_rule_id'])
        .where('user_id', '=', userId)
        .where('budget_rule_id', 'is not', null)
        .orderBy('id')
        .execute(),
    ]);
    const assigned = new Map<string, string[]>();
    for (const row of assignments) {
      const ruleId = row.budget_rule_id!;
      assigned.set(ruleId, [...(assigned.get(ruleId) ?? []), row.id]);
    }
    return rules.map((row) => ({
      id: row.id,
      label: row.label,
      percent: row.percent,
      targetHint: row.target_hint,
      assignedCategoryIds: assigned.get(row.id) ?? [],
      createdAt: row.created_at.toISOString(),
      updatedAt: row.updated_at.toISOString(),
    }));
  }

  async countRules(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.budget_rules')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async createRule(
    executor: Executor,
    userId: string,
    values: { label: string; percent: string; targetHint?: string | null },
    now: Date,
  ): Promise<string> {
    const id = randomUUID();
    await executor
      .insertInto('mymoneymap.budget_rules')
      .values({
        id,
        user_id: userId,
        label: values.label,
        percent: values.percent,
        target_hint: values.targetHint ?? null,
        created_at: now,
        updated_at: now,
      })
      .execute();
    return id;
  }

  createRuleDirect(
    userId: string,
    values: { label: string; percent: string; targetHint?: string | null },
    now: Date,
  ): Promise<string> {
    return this.createRule(this.database, userId, values, now);
  }

  async updateRule(
    userId: string,
    ruleId: string,
    values: { label?: string; percent?: string; targetHint?: string | null },
    now: Date,
  ): Promise<boolean> {
    const row = await this.database
      .updateTable('mymoneymap.budget_rules')
      .set({
        ...(values.label === undefined ? {} : { label: values.label }),
        ...(values.percent === undefined ? {} : { percent: values.percent }),
        ...(values.targetHint === undefined ? {} : { target_hint: values.targetHint }),
        updated_at: now,
      })
      .where('id', '=', ruleId)
      .where('user_id', '=', userId)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async deleteRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    ruleId: string,
    now: Date,
  ): Promise<boolean> {
    await transaction
      .updateTable('mymoneymap.categories')
      .set({ budget_rule_id: null, updated_at: now })
      .where('user_id', '=', userId)
      .where('budget_rule_id', '=', ruleId)
      .execute();
    const row = await transaction
      .deleteFrom('mymoneymap.budget_rules')
      .where('id', '=', ruleId)
      .where('user_id', '=', userId)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async listCategories(userId: string, executor: Executor = this.database): Promise<Category[]> {
    const rows = await executor
      .selectFrom('mymoneymap.categories as c')
      .leftJoin('mymoneymap.budget_rules as r', (join) =>
        join.onRef('r.id', '=', 'c.budget_rule_id').onRef('r.user_id', '=', 'c.user_id'),
      )
      .select([
        'c.id',
        'c.label',
        'c.kind',
        'c.color',
        'c.budget_rule_id',
        'c.system_key',
        'c.protected',
        'c.created_at',
        'c.updated_at',
        'r.label as budget_rule_label',
      ])
      .where('c.user_id', '=', userId)
      .orderBy('c.kind')
      .orderBy(sql`lower(c.label)`)
      .orderBy('c.id')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      label: row.label,
      kind: row.kind,
      color: row.color,
      budgetRuleId: row.budget_rule_id,
      budgetRuleLabel: row.budget_rule_label,
      systemKey: row.system_key,
      protected: row.protected,
      createdAt: row.created_at.toISOString(),
      updatedAt: row.updated_at.toISOString(),
    }));
  }

  async countCategories(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.categories')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async category(
    userId: string,
    categoryId: string,
    executor: Executor = this.database,
  ): Promise<Category | null> {
    const rows = await this.listCategories(userId, executor);
    return rows.find(({ id }) => id === categoryId) ?? null;
  }

  async createCategory(
    executor: Executor,
    userId: string,
    values: { label: string; kind: CategoryKind; color: string },
    now: Date,
  ): Promise<string> {
    const id = randomUUID();
    await executor
      .insertInto('mymoneymap.categories')
      .values({
        id,
        user_id: userId,
        label: values.label,
        kind: values.kind,
        color: values.color,
        budget_rule_id: null,
        system_key: null,
        protected: false,
        created_at: now,
        updated_at: now,
      })
      .execute();
    return id;
  }

  async updateCategory(
    userId: string,
    categoryId: string,
    values: { label?: string; kind?: CategoryKind; color?: string },
    now: Date,
  ): Promise<boolean> {
    const row = await this.database
      .updateTable('mymoneymap.categories')
      .set({
        ...(values.label === undefined ? {} : { label: values.label }),
        ...(values.kind === undefined ? {} : { kind: values.kind }),
        ...(values.color === undefined ? {} : { color: values.color }),
        ...(values.kind === 'income' ? { budget_rule_id: null } : {}),
        updated_at: now,
      })
      .where('id', '=', categoryId)
      .where('user_id', '=', userId)
      .where('protected', '=', false)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async assignCategoryRule(
    userId: string,
    categoryId: string,
    ruleId: string | null,
    now: Date,
  ): Promise<boolean> {
    const row = await this.database
      .updateTable('mymoneymap.categories')
      .set({ budget_rule_id: ruleId, updated_at: now })
      .where('id', '=', categoryId)
      .where('user_id', '=', userId)
      .where('kind', '=', 'spending')
      .where('protected', '=', false)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async deleteCategory(
    userId: string,
    categoryId: string,
  ): Promise<'deleted' | 'protected' | 'not_found'> {
    const existing = await this.database
      .selectFrom('mymoneymap.categories')
      .select('protected')
      .where('id', '=', categoryId)
      .where('user_id', '=', userId)
      .executeTakeFirst();
    if (!existing) return 'not_found';
    if (existing.protected) return 'protected';
    await this.database
      .deleteFrom('mymoneymap.categories')
      .where('id', '=', categoryId)
      .where('user_id', '=', userId)
      .execute();
    return 'deleted';
  }

  async listBasicIncomes(
    userId: string,
    executor: Executor = this.database,
  ): Promise<BasicIncome[]> {
    const rows = await executor
      .selectFrom('mymoneymap.basic_incomes as b')
      .leftJoin('mymoneymap.categories as c', (join) =>
        join.onRef('c.id', '=', 'b.category_id').onRef('c.user_id', '=', 'b.user_id'),
      )
      .select([
        'b.id',
        'b.label',
        'b.amount',
        'b.currency',
        'b.valid_from',
        'b.valid_to',
        'b.category_id',
        'b.created_at',
        'b.updated_at',
        'c.label as category_label',
      ])
      .where('b.user_id', '=', userId)
      .orderBy(sql`lower(b.label)`)
      .orderBy('b.valid_from', 'desc')
      .orderBy('b.id', 'desc')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      label: row.label,
      amount: row.amount,
      currency: row.currency,
      validFrom: dateText(row.valid_from),
      validTo: row.valid_to === null ? null : dateText(row.valid_to),
      categoryId: row.category_id,
      categoryLabel: row.category_label,
      createdAt: row.created_at.toISOString(),
      updatedAt: row.updated_at.toISOString(),
    }));
  }

  async createBasicIncome(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: {
      label: string;
      amount: string;
      currency: string;
      validFrom: string;
      validTo?: string | null;
      categoryId?: string | null;
    },
    now: Date,
  ): Promise<string> {
    await transaction
      .updateTable('mymoneymap.basic_incomes')
      .set({
        valid_to: sql`${values.validFrom}::date - 1`,
        updated_at: now,
      })
      .where('user_id', '=', userId)
      .where('label', '=', values.label)
      .where('valid_from', '<', values.validFrom)
      .where((expression) =>
        expression.or([
          expression('valid_to', 'is', null),
          expression('valid_to', '>=', values.validFrom),
        ]),
      )
      .execute();
    const id = randomUUID();
    await transaction
      .insertInto('mymoneymap.basic_incomes')
      .values({
        id,
        user_id: userId,
        label: values.label,
        amount: values.amount,
        currency: values.currency,
        valid_from: values.validFrom,
        valid_to: values.validTo ?? null,
        category_id: values.categoryId ?? null,
        created_at: now,
        updated_at: now,
      })
      .execute();
    return id;
  }

  async updateBasicIncome(
    executor: Executor,
    userId: string,
    incomeId: string,
    values: {
      label?: string;
      amount?: string;
      currency?: string;
      validFrom?: string;
      validTo?: string | null;
      categoryId?: string | null;
    },
    now: Date,
  ): Promise<boolean> {
    const row = await executor
      .updateTable('mymoneymap.basic_incomes')
      .set({
        ...(values.label === undefined ? {} : { label: values.label }),
        ...(values.amount === undefined ? {} : { amount: values.amount }),
        ...(values.currency === undefined ? {} : { currency: values.currency }),
        ...(values.validFrom === undefined ? {} : { valid_from: values.validFrom }),
        ...(values.validTo === undefined ? {} : { valid_to: values.validTo }),
        ...(values.categoryId === undefined ? {} : { category_id: values.categoryId }),
        updated_at: now,
      })
      .where('id', '=', incomeId)
      .where('user_id', '=', userId)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async deleteBasicIncome(userId: string, incomeId: string): Promise<boolean> {
    const row = await this.database
      .deleteFrom('mymoneymap.basic_incomes')
      .where('id', '=', incomeId)
      .where('user_id', '=', userId)
      .returning('id')
      .executeTakeFirst();
    return row !== undefined;
  }

  async activeBasicIncomes(
    userId: string,
    first: string,
    last: string,
  ): Promise<ActiveBasicIncome[]> {
    return this.database
      .selectFrom('mymoneymap.basic_incomes')
      .select(['amount', 'currency'])
      .where('user_id', '=', userId)
      .where('valid_from', '<=', last)
      .where((expression) =>
        expression.or([expression('valid_to', 'is', null), expression('valid_to', '>=', first)]),
      )
      .orderBy('id')
      .execute();
  }

  async advanceOnboarding(
    executor: Executor,
    userId: string,
    step: number,
    now: Date,
  ): Promise<void> {
    await executor
      .updateTable('mymoneymap.users')
      .set((expression) => ({
        onboard_step: expression.fn('greatest', ['onboard_step', expression.val(step)]),
        updated_at: now,
      }))
      .where('id', '=', userId)
      .execute();
  }

  async ruleExists(
    userId: string,
    ruleId: string,
    executor: Executor = this.database,
  ): Promise<boolean> {
    return (
      (await executor
        .selectFrom('mymoneymap.budget_rules')
        .select('id')
        .where('id', '=', ruleId)
        .where('user_id', '=', userId)
        .executeTakeFirst()) !== undefined
    );
  }

  async incomeCategoryExists(
    userId: string,
    categoryId: string,
    executor: Executor = this.database,
  ): Promise<boolean> {
    return (
      (await executor
        .selectFrom('mymoneymap.categories')
        .select('id')
        .where('id', '=', categoryId)
        .where('user_id', '=', userId)
        .where('kind', '=', 'income')
        .executeTakeFirst()) !== undefined
    );
  }

  async currencyMembershipExists(
    userId: string,
    currency: string,
    executor: Executor = this.database,
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
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}
