import { Inject, Injectable, Optional } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type { Transaction } from 'kysely';
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
import { UserTimeZone } from '../platform/time/user-time-zone';
import {
  deriveEmergencyReserveBalance,
  nextFullCalendarMonth,
  rawScheduledActivityTotals,
} from './emergency-reserve-calculator';
import type {
  CreateEmergencyReserveMovementDto,
  ReverseEmergencyReserveMovementDto,
  UpdateEmergencyReserveTargetDto,
} from './emergency-reserve.dto';
import { EmergencyReserveRepository } from './emergency-reserve.repository';
import type {
  EmergencyReserve,
  EmergencyReserveMovementDirection,
  LockedEmergencyReserve,
} from './emergency-reserve.types';
import {
  NOTIFICATION_TRIGGER,
  type NotificationTrigger,
} from '../notifications/notification-trigger.port';

const PRODUCT_TIME_ZONE = UserTimeZone.create('Europe/Budapest');

@Injectable()
export class EmergencyReserveService {
  constructor(
    @Inject(EmergencyReserveRepository) private readonly repository: EmergencyReserveRepository,
    @Inject(LedgerRepository) private readonly ledger: LedgerRepository,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(IdempotencyService) private readonly idempotency: IdempotencyService,
    @Inject(CLOCK) private readonly clock: Clock,
    @Optional()
    @Inject(NOTIFICATION_TRIGGER)
    private readonly notificationTrigger?: NotificationTrigger,
  ) {}

  async reserve(userId: string): Promise<EmergencyReserve> {
    const [configuration, movements, rules, mainCurrency] = await Promise.all([
      this.repository.configuration(userId),
      this.repository.movements(userId),
      this.repository.recurringRules(userId),
      this.repository.mainCurrency(userId),
    ]);
    if (!mainCurrency) throw semantic('A main currency is required for the emergency reserve');
    const current = PRODUCT_TIME_ZONE.calendarDateAt(this.clock.now()).toString();
    const period = nextFullCalendarMonth(current);
    return {
      configured: configuration !== null,
      targetAmount: configuration?.targetAmount ?? '0',
      currentAmount: deriveEmergencyReserveBalance(movements),
      currency: configuration?.currency ?? mainCurrency,
      reserveAccountId: configuration?.reserveAccountId ?? null,
      linkedInvestmentAccountId: configuration?.linkedInvestmentAccountId ?? null,
      targetMethodology: {
        code: 'manual_user_defined',
        label: 'User-defined reserve target',
        educationalOnly: true,
      },
      scheduledActivity: {
        classification: 'raw_unclassified_scheduled_activity',
        label: 'Raw scheduled activity totals',
        periodFrom: period.from,
        periodTo: period.to,
        totals: rawScheduledActivityTotals(rules, period.from, period.to),
      },
      movements,
      createdAt: configuration?.createdAt.toISOString() ?? null,
      updatedAt: configuration?.updatedAt.toISOString() ?? null,
    };
  }

  async updateTarget(
    userId: string,
    dto: UpdateEmergencyReserveTargetDto,
  ): Promise<EmergencyReserve> {
    const target = exact(dto.targetAmount, 'Target amount');
    if (target.isNegative()) throw semantic('Target amount cannot be negative');
    try {
      await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.currencyOwned(userId, dto.currency, transaction))) {
          throw semantic('Reserve currency must be selected by the current user');
        }
        const linked = dto.linkedInvestmentAccountId ?? null;
        if (
          linked !== null &&
          !(await this.repository.investmentAccountOwned(userId, linked, transaction))
        ) {
          throw notFound('Linked investment account was not found');
        }
        let reserve = await this.repository.lockConfiguration(transaction, userId);
        if (!reserve) {
          const now = this.clock.now().toDate();
          const accountId = await this.ledger.createModuleAccount(
            transaction,
            userId,
            'emergency_reserve',
            userId,
            now,
          );
          await this.repository.createConfiguration(transaction, {
            userId,
            targetAmount: target.toString(),
            currency: dto.currency,
            reserveAccountId: accountId,
            linkedInvestmentAccountId: linked,
            now,
          });
          reserve = await this.repository.lockConfiguration(transaction, userId);
        }
        if (!reserve) throw new Error('Emergency reserve initialization failed');
        if (
          (reserve.currency !== dto.currency || reserve.linkedInvestmentAccountId !== linked) &&
          (await this.repository.hasMovementHistory(userId, transaction))
        ) {
          throw conflict(
            'Reserve currency or holding account cannot change after movement history',
          );
        }
        await this.repository.updateConfiguration(transaction, userId, {
          targetAmount: target.toString(),
          currency: dto.currency,
          linkedInvestmentAccountId: linked,
          now: this.clock.now().toDate(),
        });
      });
      return this.reserve(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  contribution(
    userId: string,
    key: string | undefined,
    dto: CreateEmergencyReserveMovementDto,
  ): Promise<{ value: EmergencyReserve; replayed: boolean }> {
    return this.postMovement(userId, 'contribution', key, dto);
  }

  withdrawal(
    userId: string,
    key: string | undefined,
    dto: CreateEmergencyReserveMovementDto,
  ): Promise<{ value: EmergencyReserve; replayed: boolean }> {
    return this.postMovement(userId, 'withdrawal', key, dto);
  }

  async reverse(
    userId: string,
    movementId: string,
    rawKey: string | undefined,
    dto: ReverseEmergencyReserveMovementDto,
  ): Promise<{ value: EmergencyReserve; replayed: boolean }> {
    CalendarDate.create(dto.postedOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `emergency-reserve.movements.reverse:${movementId}`, key, dto),
      async (transaction) => {
        const reserve = await this.requiredReserve(transaction, userId);
        const movement = await this.repository.movement(transaction, userId, movementId);
        if (!movement) throw notFound('Emergency reserve movement was not found');
        if (movement.reversedByJournalEntryId !== null) {
          throw conflict('The emergency reserve movement was already reversed');
        }
        if (
          movement.direction === 'contribution' &&
          ExactDecimal.create(reserve.currentAmount).compare(
            ExactDecimal.create(movement.reserveAmount),
          ) < 0
        ) {
          throw conflict('Reversing this contribution would make the reserve allocation negative');
        }
        const now = this.clock.now().toDate();
        const original = await this.ledger.findOwnedEntry(
          transaction,
          userId,
          movement.journalEntryId,
        );
        const reversal = await this.ledger.reverse(transaction, original, {
          userId,
          actorUserId: userId,
          postedOn: dto.postedOn,
          effectiveAt: now,
          createdAt: now,
          note: dto.note,
          idempotencyKeyHash: key.toHash(),
          sourceModule: 'emergency_fund',
          sourceReferenceId: movement.id,
        });
        await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
        await this.repository.markReversed(transaction, userId, movement.id, reversal.id);
        return reserveJson(await this.responseInTransaction(transaction, userId));
      },
    );
    return {
      value: result.value.reserve as unknown as EmergencyReserve,
      replayed: result.replayed,
    };
  }

  private async postMovement(
    userId: string,
    direction: EmergencyReserveMovementDirection,
    rawKey: string | undefined,
    dto: CreateEmergencyReserveMovementDto,
  ): Promise<{ value: EmergencyReserve; replayed: boolean }> {
    CalendarDate.create(dto.occurredOn);
    const amount = exact(dto.amount, 'Movement amount');
    if (!amount.isPositive()) throw semantic('Movement amount must be greater than zero');
    const key = requiredKey(rawKey);
    let createdMovementId: string | null = null;
    const result = await this.idempotency.execute(
      execution(userId, `emergency-reserve.${direction}`, key, dto),
      async (transaction) => {
        if (!(await this.repository.currencyOwned(userId, dto.currency, transaction))) {
          throw semantic('Movement currency must be selected by the current user');
        }
        const reserve = await this.ensureReserve(transaction, userId);
        const conversion = await this.fx.convertObserved(
          amount.toString(),
          dto.currency,
          reserve.currency,
          dto.occurredOn,
        );
        if (conversion.status === 'unavailable' || conversion.convertedAmount === undefined) {
          throw semantic('An observed FX conversion is required for the movement date');
        }
        const reserveAmount = ExactDecimal.create(conversion.convertedAmount);
        if (!reserveAmount.isPositive())
          throw semantic('Converted movement amount must be positive');
        if (
          direction === 'withdrawal' &&
          ExactDecimal.create(reserve.currentAmount).compare(reserveAmount) < 0
        ) {
          throw semantic('Withdrawal exceeds the available emergency reserve allocation');
        }
        const movementId = randomUUID();
        createdMovementId = movementId;
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
          sourceAccountId: direction === 'contribution' ? cashAccountId : reserve.holdingAccountId,
          destinationAccountId:
            direction === 'contribution' ? reserve.holdingAccountId : cashAccountId,
          note: dto.note,
          sourceModule: 'emergency_fund',
          sourceReferenceId: movementId,
          idempotencyKeyHash: key.toHash(),
        });
        await this.fx.snapshotPostedEntry(transaction, entry, userId, dto.occurredOn, now);
        await this.repository.insertMovement(transaction, {
          id: movementId,
          userId,
          journalEntryId: entry.id,
          holdingAccountId: reserve.holdingAccountId,
          direction,
          amount: amount.toString(),
          currency: dto.currency,
          reserveAmount: reserveAmount.toString(),
          reserveCurrency: reserve.currency,
          occurredOn: dto.occurredOn,
          note: dto.note?.trim() ?? null,
          createdAt: now,
        });
        return reserveJson(await this.responseInTransaction(transaction, userId));
      },
    );
    const response = {
      value: result.value.reserve as unknown as EmergencyReserve,
      replayed: result.replayed,
    };
    if (direction === 'withdrawal' && !response.replayed) {
      const movement = response.value.movements.find(
        (candidate) => candidate.id === createdMovementId,
      );
      if (movement) {
        await this.notificationTrigger?.emergencyWithdrawal(userId, response.value, movement);
      }
    }
    return response;
  }

  private async ensureReserve(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<LockedEmergencyReserve> {
    let reserve = await this.repository.lockConfiguration(transaction, userId);
    if (reserve) return reserve;
    const currency = await this.repository.mainCurrency(userId, transaction);
    if (!currency) throw semantic('A main currency is required for the emergency reserve');
    const now = this.clock.now().toDate();
    const accountId = await this.ledger.createModuleAccount(
      transaction,
      userId,
      'emergency_reserve',
      userId,
      now,
    );
    await this.repository.createConfiguration(transaction, {
      userId,
      targetAmount: '0',
      currency,
      reserveAccountId: accountId,
      linkedInvestmentAccountId: null,
      now,
    });
    reserve = await this.repository.lockConfiguration(transaction, userId);
    if (!reserve) throw new Error('Emergency reserve initialization failed');
    return reserve;
  }

  private async requiredReserve(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<LockedEmergencyReserve> {
    const reserve = await this.repository.lockConfiguration(transaction, userId);
    if (!reserve) throw notFound('Emergency reserve was not configured');
    return reserve;
  }

  private async responseInTransaction(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<EmergencyReserve> {
    const reserve = await this.requiredReserve(transaction, userId);
    const movements = await this.repository.movements(userId, transaction);
    const rules = await this.repository.recurringRules(userId, transaction);
    const current = PRODUCT_TIME_ZONE.calendarDateAt(this.clock.now()).toString();
    const period = nextFullCalendarMonth(current);
    return {
      configured: true,
      targetAmount: reserve.targetAmount,
      currentAmount: deriveEmergencyReserveBalance(movements),
      currency: reserve.currency,
      reserveAccountId: reserve.reserveAccountId,
      linkedInvestmentAccountId: reserve.linkedInvestmentAccountId,
      targetMethodology: {
        code: 'manual_user_defined',
        label: 'User-defined reserve target',
        educationalOnly: true,
      },
      scheduledActivity: {
        classification: 'raw_unclassified_scheduled_activity',
        label: 'Raw scheduled activity totals',
        periodFrom: period.from,
        periodTo: period.to,
        totals: rawScheduledActivityTotals(rules, period.from, period.to),
      },
      movements,
      createdAt: reserve.createdAt.toISOString(),
      updatedAt: reserve.updatedAt.toISOString(),
    };
  }
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

function reserveJson(reserve: EmergencyReserve): Record<string, JsonValue> {
  return { reserve: reserve as unknown as JsonValue };
}

function notFound(message: string): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', message);
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
  if (code === '23505') return conflict('Emergency reserve state already exists');
  if (code === '23503' || code === '23514') {
    return semantic('Emergency reserve ownership or financial invariant failed');
  }
  if (code === '55000') return conflict('Posted financial history is immutable');
  return error instanceof Error ? error : new Error('Emergency reserve persistence failed');
}
