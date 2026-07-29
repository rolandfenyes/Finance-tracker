import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { FxConversionService } from '../currency/fx-conversion.service';
import { LedgerRepository } from '../ledger/ledger.repository';
import type { JournalEntry } from '../ledger/ledger.types';
import type { DatabaseSchema } from '../platform/database/database.types';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import type { JsonValue } from '../platform/events/outbox.port';
import { ApplicationError } from '../platform/http/application-error';
import {
  IdempotencyKey,
  IdempotencyOperation,
  RequestFingerprint,
} from '../platform/idempotency/idempotency';
import { IdempotencyService } from '../platform/idempotency/idempotency.service';
import { EntityId } from '../platform/identifiers/entity-id';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { EntitlementsService } from '../users/entitlements.service';
import { InvalidRecurrenceRuleError, parseRecurrenceRule } from '../recurrence/recurrence-rule';
import type { RecurringRuleWrite } from '../recurrence/recurrence.repository';
import type {
  CreateGoalContributionDto,
  CreateGoalDto,
  CreateGoalRecurringRuleDto,
  ReverseGoalContributionDto,
  UpdateGoalDto,
} from './goals.dto';
import { GoalsRepository, type GoalWrite } from './goals.repository';
import type { Goal, LockedGoal } from './goals.types';

export interface IdempotentGoal {
  value: Goal;
  replayed: boolean;
}

@Injectable()
export class GoalsService {
  constructor(
    @Inject(GoalsRepository) private readonly repository: GoalsRepository,
    @Inject(LedgerRepository) private readonly ledger: LedgerRepository,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(IdempotencyService) private readonly idempotency: IdempotencyService,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async goals(userId: string): Promise<{ items: Goal[] }> {
    return { items: await this.repository.list(userId) };
  }

  async create(userId: string, role: UserRole, dto: CreateGoalDto): Promise<{ items: Goal[] }> {
    const values = normalizeGoal(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role !== role || user.role === 'admin') throw forbidden();
        this.entitlements.assertWithinQuota(
          user.role,
          'activeGoals',
          await this.repository.countUnarchived(userId, transaction),
        );
        await this.assertReferences(transaction, userId, values);
        const goalId = randomUUID();
        const now = this.clock.now().toDate();
        await this.repository.create(transaction, userId, goalId, values, now);
        await this.ledger.createModuleAccount(transaction, userId, 'goal', goalId, now);
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async update(userId: string, goalId: string, dto: UpdateGoalDto): Promise<{ items: Goal[] }> {
    assertNonEmpty(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const goal = await this.lockOpenGoal(transaction, userId, goalId, false);
        const target = exact(dto.targetAmount ?? goal.targetAmount, 'Target amount');
        const current = ExactDecimal.create(goal.currentAmount);
        if (target.compare(current) < 0) {
          throw semantic('Target amount cannot be below the derived goal balance');
        }
        if (dto.currency !== undefined && dto.currency !== goal.currency) {
          const references = await this.repository.references(userId, goalId, transaction);
          if (references.contributions > 0 || references.recurringRules > 0) {
            throw conflict('Goal currency cannot change after contribution or schedule history');
          }
        }
        const values: Partial<Omit<GoalWrite, 'status'>> & {
          status?: 'active' | 'paused' | 'completed';
        } = {
          ...(dto.title === undefined ? {} : { title: dto.title.trim() }),
          ...(dto.targetAmount === undefined ? {} : { targetAmount: target.toString() }),
          ...(dto.currency === undefined ? {} : { currency: dto.currency }),
          ...(dto.deadline === undefined ? {} : { deadline: normalizeDate(dto.deadline) }),
          ...(dto.priority === undefined ? {} : { priority: dto.priority }),
          ...(dto.categoryId === undefined ? {} : { categoryId: dto.categoryId }),
        };
        const finalCurrency = dto.currency ?? goal.currency;
        await this.assertReferences(transaction, userId, {
          currency: finalCurrency,
          categoryId: dto.categoryId ?? null,
        });
        values.status =
          target.compare(current) === 0
            ? 'completed'
            : (dto.status ?? (goal.status === 'completed' ? 'active' : goal.status));
        await this.repository.update(
          transaction,
          userId,
          goalId,
          values,
          this.clock.now().toDate(),
        );
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async archive(userId: string, goalId: string): Promise<{ items: Goal[] }> {
    try {
      await this.repository.transaction(async (transaction) => {
        const goal = await this.repository.lockGoal(transaction, userId, goalId);
        if (!goal) throw notFound();
        const now = this.clock.now().toDate();
        if (goal.archivedAt === null) {
          await this.repository.archive(transaction, userId, goalId, now, now);
        }
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async unarchive(userId: string, role: UserRole, goalId: string): Promise<{ items: Goal[] }> {
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        const goal = await this.repository.lockGoal(transaction, userId, goalId);
        if (!user || user.role !== role || user.role === 'admin') throw forbidden();
        if (!goal) throw notFound();
        if (goal.archivedAt !== null) {
          this.entitlements.assertWithinQuota(
            user.role,
            'activeGoals',
            await this.repository.countUnarchived(userId, transaction),
          );
          await this.repository.archive(
            transaction,
            userId,
            goalId,
            null,
            this.clock.now().toDate(),
          );
        }
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async delete(userId: string, goalId: string): Promise<void> {
    try {
      await this.repository.transaction(async (transaction) => {
        const goal = await this.repository.lockGoal(transaction, userId, goalId);
        if (!goal) throw notFound();
        const references = await this.repository.references(userId, goalId, transaction);
        if (references.contributions > 0) {
          throw conflict('A goal with contribution history must be archived instead of deleted');
        }
        await this.repository.unlinkRule(transaction, userId, goalId, this.clock.now().toDate());
        await this.repository.deleteGoalAndAccount(transaction, userId, goalId);
      });
    } catch (error) {
      throw translate(error);
    }
  }

  contribution(
    userId: string,
    goalId: string,
    rawKey: string | undefined,
    dto: CreateGoalContributionDto,
  ): Promise<IdempotentGoal> {
    return this.postContribution(userId, goalId, rawKey, dto, null);
  }

  async correctContribution(
    userId: string,
    goalId: string,
    contributionId: string,
    rawKey: string | undefined,
    dto: CreateGoalContributionDto,
  ): Promise<IdempotentGoal> {
    CalendarDate.create(dto.occurredOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `goals.contributions.correct:${contributionId}`, key, dto),
      async (transaction) => {
        const goal = await this.requiredGoal(transaction, userId, goalId);
        this.assertContributionCorrectionAllowed(goal);
        const original = await this.repository.contribution(
          transaction,
          userId,
          goalId,
          contributionId,
        );
        if (!original) throw contributionNotFound();
        if (original.reversedByJournalEntryId !== null) {
          throw conflict('The goal contribution was already reversed or corrected');
        }
        await this.assertContributionCurrency(transaction, userId, dto.currency);
        const converted = await this.convert(dto, goal.currency);
        const prospective = ExactDecimal.create(goal.currentAmount)
          .subtract(ExactDecimal.create(original.goalAmount))
          .add(ExactDecimal.create(converted));
        this.assertWithinTarget(prospective, goal.targetAmount);
        const now = this.clock.now().toDate();
        const originalEntry = await this.ledger.findOwnedEntry(
          transaction,
          userId,
          original.journalEntryId,
        );
        const reversal = await this.reverseJournal(
          transaction,
          userId,
          original,
          originalEntry,
          dto.occurredOn,
          derivedHash(key.toHash(), 'reversal'),
          now,
        );
        await this.repository.markReversed(transaction, userId, original.id, reversal.id);
        const replacementId = randomUUID();
        const replacement = await this.postJournal(
          transaction,
          goal,
          replacementId,
          dto,
          derivedHash(key.toHash(), 'replacement'),
          now,
          original.journalEntryId,
        );
        await this.repository.insertContribution(transaction, {
          id: replacementId,
          userId,
          goalId,
          journalEntryId: replacement.id,
          amount: exact(dto.amount, 'Contribution amount').toString(),
          currency: dto.currency,
          goalAmount: converted,
          goalCurrency: goal.currency,
          occurredOn: dto.occurredOn,
          note: dto.note?.trim() ?? null,
          correctsContributionId: original.id,
          createdAt: now,
        });
        await this.updateDerivedStatus(transaction, goal, prospective);
        return goalJson(await this.requiredResponseGoal(transaction, userId, goalId));
      },
    );
    return { value: result.value.goal as unknown as Goal, replayed: result.replayed };
  }

  async reverseContribution(
    userId: string,
    goalId: string,
    contributionId: string,
    rawKey: string | undefined,
    dto: ReverseGoalContributionDto,
  ): Promise<IdempotentGoal> {
    CalendarDate.create(dto.postedOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `goals.contributions.reverse:${contributionId}`, key, dto),
      async (transaction) => {
        const goal = await this.requiredGoal(transaction, userId, goalId);
        const contribution = await this.repository.contribution(
          transaction,
          userId,
          goalId,
          contributionId,
        );
        if (!contribution) throw contributionNotFound();
        if (contribution.reversedByJournalEntryId !== null) {
          throw conflict('The goal contribution was already reversed or corrected');
        }
        const now = this.clock.now().toDate();
        const original = await this.ledger.findOwnedEntry(
          transaction,
          userId,
          contribution.journalEntryId,
        );
        const reversal = await this.reverseJournal(
          transaction,
          userId,
          contribution,
          original,
          dto.postedOn,
          key.toHash(),
          now,
          dto.note,
        );
        await this.repository.markReversed(transaction, userId, contribution.id, reversal.id);
        const balance = ExactDecimal.create(goal.currentAmount).subtract(
          ExactDecimal.create(contribution.goalAmount),
        );
        await this.updateDerivedStatus(transaction, goal, balance);
        return goalJson(await this.requiredResponseGoal(transaction, userId, goalId));
      },
    );
    return { value: result.value.goal as unknown as Goal, replayed: result.replayed };
  }

  async createRule(
    userId: string,
    role: UserRole,
    goalId: string,
    dto: CreateGoalRecurringRuleDto,
  ): Promise<{ items: Goal[] }> {
    const values = schedule(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        const goal = await this.lockOpenGoal(transaction, userId, goalId, true);
        if (!user || user.role !== role || user.role === 'admin') throw forbidden();
        this.entitlements.assertWithinQuota(
          user.role,
          'activeScheduledItems',
          await this.countRules(transaction, userId),
        );
        await this.repository.createGoalRule(
          transaction,
          userId,
          goalId,
          { ...values, currency: goal.currency },
          this.clock.now().toDate(),
        );
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async updateRule(
    userId: string,
    goalId: string,
    dto: CreateGoalRecurringRuleDto,
  ): Promise<{ items: Goal[] }> {
    const values = schedule(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const goal = await this.lockOpenGoal(transaction, userId, goalId, true);
        if (
          !(await this.repository.updateGoalRule(
            transaction,
            userId,
            goalId,
            { ...values, currency: goal.currency },
            this.clock.now().toDate(),
          ))
        ) {
          throw ruleNotFound();
        }
      });
      return this.goals(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async deleteRule(userId: string, goalId: string): Promise<void> {
    if (!(await this.repository.deleteGoalRule(userId, goalId))) throw ruleNotFound();
  }

  private async postContribution(
    userId: string,
    goalId: string,
    rawKey: string | undefined,
    dto: CreateGoalContributionDto,
    correctsContributionId: string | null,
  ): Promise<IdempotentGoal> {
    CalendarDate.create(dto.occurredOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `goals.contributions.create:${goalId}`, key, dto),
      async (transaction) => {
        const goal = await this.requiredGoal(transaction, userId, goalId);
        this.assertContributionAllowed(goal);
        await this.assertContributionCurrency(transaction, userId, dto.currency);
        const converted = await this.convert(dto, goal.currency);
        const prospective = ExactDecimal.create(goal.currentAmount).add(
          ExactDecimal.create(converted),
        );
        this.assertWithinTarget(prospective, goal.targetAmount);
        const contributionId = randomUUID();
        const now = this.clock.now().toDate();
        const entry = await this.postJournal(
          transaction,
          goal,
          contributionId,
          dto,
          key.toHash(),
          now,
        );
        await this.repository.insertContribution(transaction, {
          id: contributionId,
          userId,
          goalId,
          journalEntryId: entry.id,
          amount: exact(dto.amount, 'Contribution amount').toString(),
          currency: dto.currency,
          goalAmount: converted,
          goalCurrency: goal.currency,
          occurredOn: dto.occurredOn,
          note: dto.note?.trim() ?? null,
          correctsContributionId,
          createdAt: now,
        });
        await this.updateDerivedStatus(transaction, goal, prospective);
        return goalJson(await this.requiredResponseGoal(transaction, userId, goalId));
      },
    );
    return { value: result.value.goal as unknown as Goal, replayed: result.replayed };
  }

  private async postJournal(
    transaction: Transaction<DatabaseSchema>,
    goal: LockedGoal,
    contributionId: string,
    dto: CreateGoalContributionDto,
    keyHash: string,
    now: Date,
    replacesEntryId?: string,
  ): Promise<JournalEntry> {
    const entry = await this.ledger.post(transaction, {
      userId: goal.userId,
      actorUserId: goal.userId,
      economicType: 'internal_transfer',
      amount: exact(dto.amount, 'Contribution amount').toString(),
      currency: dto.currency,
      postedOn: dto.occurredOn,
      effectiveAt: now,
      createdAt: now,
      sourceAccountId: await this.repository.defaultCashAccount(transaction, goal.userId),
      destinationAccountId: goal.ledgerAccountId,
      note: dto.note,
      sourceModule: 'goals',
      sourceReferenceId: contributionId,
      idempotencyKeyHash: keyHash,
      replacesEntryId,
    });
    await this.fx.snapshotPostedEntry(transaction, entry, goal.userId, dto.occurredOn, now);
    return entry;
  }

  private async reverseJournal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    contribution: { id: string },
    original: JournalEntry,
    postedOn: string,
    keyHash: string,
    now: Date,
    note?: string,
  ): Promise<JournalEntry> {
    const reversal = await this.ledger.reverse(transaction, original, {
      userId,
      actorUserId: userId,
      postedOn,
      effectiveAt: now,
      createdAt: now,
      note,
      idempotencyKeyHash: keyHash,
      sourceModule: 'goals',
      sourceReferenceId: contribution.id,
    });
    await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
    return reversal;
  }

  private async convert(dto: CreateGoalContributionDto, goalCurrency: string): Promise<string> {
    const amount = exact(dto.amount, 'Contribution amount');
    if (!amount.isPositive()) throw semantic('Contribution amount must be greater than zero');
    const conversion = await this.fx.convertObserved(
      amount.toString(),
      dto.currency,
      goalCurrency,
      dto.occurredOn,
    );
    if (conversion.status === 'unavailable' || conversion.convertedAmount === undefined) {
      throw semantic('An observed FX conversion is required for the contribution date');
    }
    if (!ExactDecimal.create(conversion.convertedAmount).isPositive()) {
      throw semantic('Converted contribution amount must be greater than zero');
    }
    return conversion.convertedAmount;
  }

  private async assertReferences(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: Pick<GoalWrite, 'currency' | 'categoryId'>,
  ): Promise<void> {
    if (!(await this.repository.currencyOwned(userId, values.currency, transaction))) {
      throw semantic('Goal currency must be selected by the current user');
    }
    if (
      values.categoryId !== null &&
      !(await this.repository.categoryOwned(userId, values.categoryId, transaction))
    ) {
      throw notFound('Category was not found');
    }
  }

  private async assertContributionCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    currency: string,
  ): Promise<void> {
    if (!(await this.repository.currencyOwned(userId, currency, transaction))) {
      throw semantic('Contribution currency must be selected by the current user');
    }
  }

  private async requiredGoal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
  ): Promise<LockedGoal> {
    const goal = await this.repository.lockGoal(transaction, userId, goalId);
    if (!goal) throw notFound();
    return goal;
  }

  private async lockOpenGoal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
    requireContributionOpen: boolean,
  ): Promise<LockedGoal> {
    const goal = await this.requiredGoal(transaction, userId, goalId);
    if (goal.archivedAt !== null) throw conflict('Archived goals are locked');
    if (requireContributionOpen && goal.status === 'completed') {
      throw conflict('Completed goals are locked');
    }
    return goal;
  }

  private assertContributionAllowed(goal: LockedGoal): void {
    if (goal.archivedAt !== null) throw conflict('Archived goals cannot receive contributions');
    if (
      goal.status === 'completed' ||
      ExactDecimal.create(goal.currentAmount).compare(ExactDecimal.create(goal.targetAmount)) >= 0
    ) {
      throw conflict('Completed goals cannot receive contributions');
    }
  }

  private assertContributionCorrectionAllowed(goal: LockedGoal): void {
    if (goal.archivedAt !== null) throw conflict('Archived goals are locked');
  }

  private assertWithinTarget(balance: ExactDecimal, target: string): void {
    if (balance.compare(ExactDecimal.create(target)) > 0) {
      throw semantic('Contribution would exceed the remaining goal amount');
    }
  }

  private async updateDerivedStatus(
    transaction: Transaction<DatabaseSchema>,
    goal: LockedGoal,
    balance: ExactDecimal,
  ): Promise<void> {
    const target = ExactDecimal.create(goal.targetAmount);
    const status =
      balance.compare(target) === 0 ? 'completed' : goal.status === 'paused' ? 'paused' : 'active';
    if (status !== goal.status) {
      await this.repository.update(
        transaction,
        goal.userId,
        goal.id,
        { status },
        this.clock.now().toDate(),
      );
    }
  }

  private async requiredResponseGoal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    goalId: string,
  ): Promise<Goal> {
    const goal = await this.repository.goal(userId, goalId, transaction);
    if (!goal) throw notFound();
    return goal;
  }

  private countRules(transaction: Transaction<DatabaseSchema>, userId: string): Promise<number> {
    return transaction
      .selectFrom('mymoneymap.recurring_rules')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow()
      .then(({ count }) => Number(count));
  }
}

function normalizeGoal(dto: CreateGoalDto): GoalWrite {
  const target = exact(dto.targetAmount, 'Target amount');
  if (!target.isPositive()) throw semantic('Target amount must be greater than zero');
  return {
    title: dto.title.trim(),
    targetAmount: target.toString(),
    currency: dto.currency,
    deadline: normalizeDate(dto.deadline),
    priority: dto.priority ?? 3,
    status: dto.status ?? 'active',
    categoryId: dto.categoryId ?? null,
  };
}

function schedule(dto: CreateGoalRecurringRuleDto): RecurringRuleWrite {
  CalendarDate.create(dto.startsOn);
  const amount = exact(dto.amount, 'Recurring contribution amount');
  if (!amount.isPositive())
    throw semantic('Recurring contribution amount must be greater than zero');
  try {
    return {
      title: dto.title.trim(),
      amount: amount.toString(),
      currency: '',
      economicType: 'transfer',
      startsOn: dto.startsOn,
      rrule: parseRecurrenceRule(dto.rrule).canonical,
      categoryId: null,
    };
  } catch (error) {
    if (error instanceof InvalidRecurrenceRuleError) throw semantic(error.message);
    throw error;
  }
}

function normalizeDate(value: string | null | undefined): string | null {
  if (value === null || value === undefined) return null;
  return CalendarDate.create(value).toString();
}

function exact(value: string, label: string): ExactDecimal {
  try {
    return ExactDecimal.create(value);
  } catch {
    throw semantic(`${label} must be an exact base-10 decimal string`);
  }
}

function assertNonEmpty(value: object): void {
  if (Object.values(value).every((item) => item === undefined)) {
    throw new ApplicationError(400, 'BAD_REQUEST', 'At least one field is required');
  }
}

function requiredKey(value: string | undefined): IdempotencyKey {
  if (!value) throw new ApplicationError(400, 'BAD_REQUEST', 'Idempotency-Key header is required');
  try {
    return IdempotencyKey.create(value);
  } catch {
    throw new ApplicationError(400, 'BAD_REQUEST', 'Idempotency-Key header is invalid');
  }
}

function execution(
  userId: string,
  operation: string,
  key: IdempotencyKey,
  request: object,
): {
  scopeId: EntityId;
  operation: IdempotencyOperation;
  key: IdempotencyKey;
  requestFingerprint: RequestFingerprint;
} {
  return {
    scopeId: EntityId.create(userId),
    operation: IdempotencyOperation.create(operation),
    key,
    requestFingerprint: RequestFingerprint.fromCanonicalRequest(JSON.stringify(request)),
  };
}

function derivedHash(hash: string, purpose: string): string {
  return createHash('sha256').update(`${hash}:${purpose}`, 'utf8').digest('hex');
}

function goalJson(goal: Goal): Record<string, JsonValue> {
  return { goal: goal as unknown as JsonValue };
}

function notFound(message = 'Goal was not found'): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', message);
}

function contributionNotFound(): ApplicationError {
  return notFound('Goal contribution was not found');
}

function ruleNotFound(): ApplicationError {
  return notFound('Goal recurring rule was not found');
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function conflict(message: string): ApplicationError {
  return new ApplicationError(409, 'CONFLICT', message);
}

function semantic(message: string): ApplicationError {
  return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', message);
}

function translate(error: unknown): Error {
  if (error instanceof ApplicationError) return error;
  const code =
    typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
  if (code === '23505') return conflict('Goal, contribution, or schedule state already exists');
  if (code === '23503' || code === '23514') {
    return semantic('Goal ownership or financial invariant failed');
  }
  if (code === '55000') return conflict('Posted financial history is immutable');
  return error instanceof Error ? error : new Error('Goal persistence failed');
}
