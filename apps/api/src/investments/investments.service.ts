import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type { Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { FxConversionService } from '../currency/fx-conversion.service';
import { LedgerRepository } from '../ledger/ledger.repository';
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
import {
  expandRecurrence,
  InvalidRecurrenceRuleError,
  parseRecurrenceRule,
} from '../recurrence/recurrence-rule';
import type { RecurringRuleWrite } from '../recurrence/recurrence.repository';
import { EntitlementsService } from '../users/entitlements.service';
import { deriveInvestmentBalance, projectScenarioWithContributions } from './investment-calculator';
import type {
  CreateInvestmentDto,
  CreateInvestmentMovementDto,
  CreateInvestmentRecurringRuleDto,
  ReverseInvestmentMovementDto,
  UpdateInvestmentDto,
} from './investments.dto';
import { InvestmentsRepository, type InvestmentWrite } from './investments.repository';
import type { Investment, InvestmentRecord } from './investments.types';

@Injectable()
export class InvestmentsService {
  constructor(
    @Inject(InvestmentsRepository) private readonly repository: InvestmentsRepository,
    @Inject(LedgerRepository) private readonly ledger: LedgerRepository,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(IdempotencyService) private readonly idempotency: IdempotencyService,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async investments(userId: string): Promise<{ items: Investment[] }> {
    return { items: await this.decorate(await this.repository.list(userId)) };
  }

  async create(
    userId: string,
    role: UserRole,
    dto: CreateInvestmentDto,
  ): Promise<{ items: Investment[] }> {
    const values = normalizeInvestment(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role !== role || role === 'admin') throw forbidden();
        await this.assertCurrency(transaction, userId, values.currency);
        const id = randomUUID();
        const now = this.clock.now().toDate();
        const accountId = await this.ledger.createModuleAccount(
          transaction,
          userId,
          'investment',
          id,
          now,
        );
        await this.repository.create(transaction, userId, id, accountId, values, now);
      });
      return this.investments(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async update(
    userId: string,
    investmentId: string,
    dto: UpdateInvestmentDto,
  ): Promise<{ items: Investment[] }> {
    if (Object.values(dto).every((value) => value === undefined)) {
      throw new ApplicationError(400, 'BAD_REQUEST', 'At least one field is required');
    }
    try {
      await this.repository.transaction(async (transaction) => {
        await this.requiredInvestment(transaction, userId, investmentId);
        const rate =
          dto.scenarioAnnualRate === undefined
            ? undefined
            : dto.scenarioAnnualRate === null
              ? null
              : nonNegative(dto.scenarioAnnualRate, 'Scenario annual rate');
        await this.repository.update(
          transaction,
          userId,
          investmentId,
          {
            ...(dto.type === undefined ? {} : { type: dto.type }),
            ...(dto.name === undefined ? {} : { name: dto.name.trim() }),
            ...(dto.provider === undefined ? {} : { provider: optionalText(dto.provider) }),
            ...(dto.identifier === undefined ? {} : { identifier: optionalText(dto.identifier) }),
            ...(dto.notes === undefined ? {} : { notes: optionalText(dto.notes) }),
            ...(rate === undefined ? {} : { scenarioAnnualRate: rate }),
            ...(dto.scenarioFrequency === undefined
              ? {}
              : { scenarioFrequency: dto.scenarioFrequency }),
          },
          this.clock.now().toDate(),
        );
      });
      return this.investments(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async delete(userId: string, investmentId: string): Promise<void> {
    try {
      await this.repository.transaction(async (transaction) => {
        const investment = await this.requiredInvestment(transaction, userId, investmentId);
        if (investment.movements.length > 0) {
          throw conflict('An investment with immutable movement history cannot be deleted');
        }
        if (await this.repository.isEmergencyLinked(userId, investment.accountId, transaction)) {
          throw conflict('Unlink this investment from the emergency reserve before deleting it');
        }
        await this.repository.deleteEmpty(transaction, userId, investmentId, investment.accountId);
      });
    } catch (error) {
      throw translate(error);
    }
  }

  async movement(
    userId: string,
    investmentId: string,
    rawKey: string | undefined,
    dto: CreateInvestmentMovementDto,
  ): Promise<{ value: Investment; replayed: boolean }> {
    CalendarDate.create(dto.occurredOn);
    const amount = exact(dto.amount, 'Movement amount');
    if (!amount.isPositive()) throw semantic('Movement amount must be greater than zero');
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `investments.movements:${investmentId}`, key, dto),
      async (transaction) => {
        await this.assertCurrency(transaction, userId, dto.currency);
        const investment = await this.requiredInvestment(transaction, userId, investmentId);
        const conversion = await this.fx.convertObserved(
          amount.toString(),
          dto.currency,
          investment.currency,
          dto.occurredOn,
        );
        if (conversion.status === 'unavailable' || conversion.convertedAmount === undefined) {
          throw semantic('An observed FX conversion is required for the movement date');
        }
        const investmentAmount = exact(conversion.convertedAmount, 'Converted investment amount');
        const balance = ExactDecimal.create(deriveInvestmentBalance(investment.movements));
        if (dto.direction === 'withdrawal' && balance.compare(investmentAmount) < 0) {
          throw semantic('Withdrawal exceeds the available investment balance');
        }
        const movementId = randomUUID();
        const now = this.clock.now().toDate();
        const cashAccountId = await this.repository.defaultCashAccount(transaction, userId);
        const entry = await this.ledger.post(transaction, {
          userId,
          actorUserId: userId,
          economicType: 'internal_transfer',
          amount: amount.toString(),
          currency: dto.currency,
          postedOn: dto.occurredOn,
          effectiveAt: now,
          createdAt: now,
          sourceAccountId: dto.direction === 'deposit' ? cashAccountId : investment.accountId,
          destinationAccountId: dto.direction === 'deposit' ? investment.accountId : cashAccountId,
          note: dto.note,
          sourceModule: 'investments',
          sourceReferenceId: movementId,
          idempotencyKeyHash: key.toHash(),
        });
        await this.fx.snapshotPostedEntry(transaction, entry, userId, dto.occurredOn, now);
        await this.repository.insertMovement(transaction, {
          id: movementId,
          userId,
          investmentId,
          journalEntryId: entry.id,
          direction: dto.direction,
          amount: amount.toString(),
          currency: dto.currency,
          investmentAmount: investmentAmount.toString(),
          investmentCurrency: investment.currency,
          occurredOn: dto.occurredOn,
          note: dto.note?.trim() ?? null,
          createdAt: now,
        });
        return investmentJson(await this.responseInTransaction(transaction, userId, investmentId));
      },
    );
    return {
      value: result.value.investment as unknown as Investment,
      replayed: result.replayed,
    };
  }

  async reverseMovement(
    userId: string,
    investmentId: string,
    movementId: string,
    rawKey: string | undefined,
    dto: ReverseInvestmentMovementDto,
  ): Promise<{ value: Investment; replayed: boolean }> {
    CalendarDate.create(dto.postedOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `investments.movements.reverse:${movementId}`, key, dto),
      async (transaction) => {
        const investment = await this.requiredInvestment(transaction, userId, investmentId);
        const movement = await this.repository.movement(
          userId,
          investmentId,
          movementId,
          transaction,
        );
        if (!movement) throw notFound();
        if (movement.reversedByJournalEntryId !== null) {
          throw conflict('The investment movement was already reversed');
        }
        if (
          movement.direction === 'deposit' &&
          ExactDecimal.create(deriveInvestmentBalance(investment.movements)).compare(
            ExactDecimal.create(movement.investmentAmount),
          ) < 0
        ) {
          throw conflict('Reversing this deposit would make the investment balance negative');
        }
        const original = await this.ledger.findOwnedEntry(
          transaction,
          userId,
          movement.journalEntryId,
        );
        const now = this.clock.now().toDate();
        const reversal = await this.ledger.reverse(transaction, original, {
          userId,
          actorUserId: userId,
          postedOn: dto.postedOn,
          effectiveAt: now,
          createdAt: now,
          note: dto.note,
          idempotencyKeyHash: key.toHash(),
          sourceModule: 'investments',
          sourceReferenceId: movementId,
        });
        await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
        await this.repository.markReversed(transaction, userId, movementId, reversal.id);
        return investmentJson(await this.responseInTransaction(transaction, userId, investmentId));
      },
    );
    return {
      value: result.value.investment as unknown as Investment,
      replayed: result.replayed,
    };
  }

  async createRule(
    userId: string,
    role: UserRole,
    investmentId: string,
    dto: CreateInvestmentRecurringRuleDto,
  ): Promise<{ items: Investment[] }> {
    const values = normalizeRule(dto, investmentId);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        const investment = await this.requiredInvestment(transaction, userId, investmentId);
        if (!user || user.role !== role || role === 'admin') throw forbidden();
        if (investment.recurringRule !== null) {
          throw conflict('The investment already has a recurring contribution rule');
        }
        this.entitlements.assertWithinQuota(
          role,
          'activeScheduledItems',
          await this.repository.countRules(userId, transaction),
        );
        await this.assertCurrency(transaction, userId, dto.currency);
        await this.repository.createRule(transaction, userId, values, this.clock.now().toDate());
      });
      return this.investments(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  private async requiredInvestment(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
  ): Promise<InvestmentRecord> {
    const investment = await this.repository.lockInvestment(transaction, userId, investmentId);
    if (!investment) throw notFound();
    return investment;
  }

  private async responseInTransaction(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    investmentId: string,
  ): Promise<Investment> {
    const investment = await this.repository.investment(userId, investmentId, transaction);
    if (!investment) throw notFound();
    return (await this.decorate([investment], transaction))[0]!;
  }

  private async decorate(
    records: InvestmentRecord[],
    executor?: Transaction<DatabaseSchema>,
  ): Promise<Investment[]> {
    const today = this.clock.now().toString().slice(0, 10);
    return Promise.all(
      records.map(async (record) => {
        const policy = await this.repository.currencyPolicy(record.currency, executor);
        const balance = deriveInvestmentBalance(record.movements);
        const horizon = addYears(today, 5);
        const expansion = record.recurringRule
          ? expandRecurrence(
              record.recurringRule.startsOn,
              record.recurringRule.rrule,
              today,
              horizon,
            )
          : null;
        const sameCurrency = record.recurringRule?.currency === record.currency;
        const contributions =
          expansion && sameCurrency
            ? expansion.dates.map((occurredOn) => ({
                occurredOn,
                amount: record.recurringRule!.amount,
              }))
            : [];
        const scenarioEnabled = record.scenarioAnnualRate !== null;
        const milestones = scenarioEnabled
          ? ['1', '5'].map((years) => {
              const to = addYears(today, Number(years));
              const relevant = contributions.filter(({ occurredOn }) => occurredOn <= to);
              const projected = projectScenarioWithContributions({
                principal: balance,
                nominalAnnualRate: record.scenarioAnnualRate!,
                frequency: record.scenarioFrequency,
                from: today,
                to,
                contributions: relevant,
                scale: policy.minorUnit,
                roundingMode: policy.roundingMode,
              });
              return { horizonYears: years, ...projected };
            })
          : [];
        return {
          ...record,
          balance,
          scenario: {
            enabled: scenarioEnabled,
            version: 'nominal_compound_scenario_v1',
            label: 'User-authored nominal compound return scenario',
            nominalAnnualRate: record.scenarioAnnualRate,
            frequency: record.scenarioFrequency,
            guaranteed: false,
            expectedReturn: false,
            affectsPostedBalance: false,
            milestones,
          },
          recurringContributionForecast:
            record.recurringRule && expansion
              ? {
                  from: today,
                  to: horizon,
                  occurrences: expansion.dates,
                  amount: record.recurringRule.amount,
                  currency: record.recurringRule.currency,
                  investmentCurrencyContributionTotal: sameCurrency
                    ? ExactDecimal.create(record.recurringRule.amount)
                        .multiply(ExactDecimal.create(String(expansion.dates.length)))
                        .toString()
                    : null,
                  conversionStatus: sameCurrency
                    ? ('same_currency' as const)
                    : ('future_fx_unavailable' as const),
                  truncated: expansion.truncated,
                }
              : null,
        };
      }),
    );
  }

  private async assertCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    currency: string,
  ): Promise<void> {
    if (!(await this.repository.currencyOwned(userId, currency, transaction))) {
      throw semantic('Currency must be selected by the current user');
    }
  }
}

function normalizeInvestment(dto: CreateInvestmentDto): InvestmentWrite {
  return {
    type: dto.type,
    name: dto.name.trim(),
    provider: optionalText(dto.provider),
    identifier: optionalText(dto.identifier),
    notes: optionalText(dto.notes),
    currency: dto.currency,
    scenarioAnnualRate:
      dto.scenarioAnnualRate === undefined || dto.scenarioAnnualRate === null
        ? null
        : nonNegative(dto.scenarioAnnualRate, 'Scenario annual rate'),
    scenarioFrequency: dto.scenarioFrequency ?? 'monthly',
  };
}

function normalizeRule(
  dto: CreateInvestmentRecurringRuleDto,
  investmentId: string,
): RecurringRuleWrite {
  CalendarDate.create(dto.startsOn);
  const amount = exact(dto.amount, 'Recurring contribution amount');
  if (!amount.isPositive())
    throw semantic('Recurring contribution amount must be greater than zero');
  try {
    return {
      title: dto.title.trim(),
      amount: amount.toString(),
      currency: dto.currency,
      economicType: 'transfer' as const,
      startsOn: dto.startsOn,
      rrule: parseRecurrenceRule(dto.rrule).canonical,
      categoryId: null,
      goalId: null,
      loanId: null,
      investmentId,
    };
  } catch (error) {
    if (error instanceof InvalidRecurrenceRuleError) throw semantic(error.message);
    throw error;
  }
}

function optionalText(value: string | null | undefined): string | null {
  return value === undefined || value === null ? null : value.trim();
}

function nonNegative(value: string, label: string): string {
  const parsed = exact(value, label);
  if (parsed.isNegative()) throw semantic(`${label} cannot be negative`);
  return parsed.toString();
}

function exact(value: string, label: string): ExactDecimal {
  try {
    return ExactDecimal.create(value);
  } catch {
    throw semantic(`${label} must be an exact base-10 decimal string`);
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

function investmentJson(investment: Investment): Record<string, JsonValue> {
  return { investment: investment as unknown as JsonValue };
}

function addYears(date: string, years: number): string {
  const value = new Date(`${date}T00:00:00.000Z`);
  value.setUTCFullYear(value.getUTCFullYear() + years);
  return value.toISOString().slice(0, 10);
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function notFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'Investment was not found');
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
  if (code === '23505') return conflict('Investment or recurring contribution already exists');
  if (code === '23503' || code === '23514') {
    return semantic('Investment ownership or financial invariant failed');
  }
  if (code === '55000') return conflict('Posted investment history is immutable');
  return error instanceof Error ? error : new Error('Investment persistence failed');
}
