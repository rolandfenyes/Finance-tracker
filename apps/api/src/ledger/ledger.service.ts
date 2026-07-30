import { forwardRef, HttpStatus, Inject, Injectable, Optional } from '@nestjs/common';
import { createHash } from 'node:crypto';
import { NoResultError } from 'kysely';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { FxConversionService } from '../currency/fx-conversion.service';
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
import { UtcInstant } from '../platform/time/utc-instant';
import type {
  CorrectJournalEntryDto,
  CreateJournalEntryDto,
  ListJournalEntriesDto,
  ReverseJournalEntryDto,
} from './ledger.dto';
import { InvalidJournalCursorError, LedgerRepository } from './ledger.repository';
import type { JournalEntry, JournalListPage, PostJournalCommand } from './ledger.types';
import { CategoryPolicyService } from '../budgeting/category-policy.service';
import { BudgetingService } from '../budgeting/budgeting.service';
import {
  NOTIFICATION_TRIGGER,
  type BudgetOverspendingNotification,
  type NotificationTrigger,
} from '../notifications/notification-trigger.port';

export interface IdempotentValue<T> {
  value: T;
  replayed: boolean;
}

@Injectable()
export class LedgerService {
  constructor(
    @Inject(LedgerRepository) private readonly repository: LedgerRepository,
    @Inject(IdempotencyService) private readonly idempotency: IdempotencyService,
    @Inject(CLOCK) private readonly clock: Clock,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(forwardRef(() => CategoryPolicyService))
    private readonly categories: CategoryPolicyService,
    @Optional()
    @Inject(forwardRef(() => BudgetingService))
    private readonly budgeting?: BudgetingService,
    @Optional()
    @Inject(NOTIFICATION_TRIGGER)
    private readonly notificationTrigger?: NotificationTrigger,
  ) {}

  async createManualEntry(
    userId: string,
    rawKey: string | undefined,
    dto: CreateJournalEntryDto,
  ): Promise<IdempotentValue<JournalEntry>> {
    await this.validateManualCommand(userId, dto);
    const month = dto.postedOn.slice(0, 7);
    const before =
      dto.economicType === 'external_expense' && dto.categoryId
        ? await this.budgeting?.overspending(userId, month)
        : undefined;
    const key = this.key(rawKey);
    const now = this.clock.now();
    const command = this.toCommand(userId, dto, key.toHash(), now);
    try {
      const result = await this.idempotency.execute(
        {
          scopeId: EntityId.create(userId),
          operation: IdempotencyOperation.create('ledger.entries.create'),
          key,
          requestFingerprint: fingerprint(dto),
        },
        async (transaction) => {
          const entry = await this.repository.post(transaction, command);
          await this.fx.snapshotPostedEntry(
            transaction,
            entry,
            userId,
            command.postedOn,
            command.createdAt,
          );
          return asJsonObject({
            entry: await this.repository.findOwnedEntry(transaction, userId, entry.id),
          });
        },
      );
      const response = {
        value: result.value.entry as unknown as JournalEntry,
        replayed: result.replayed,
      };
      if (!response.replayed && before) {
        await this.notifyNewOverspending(
          userId,
          response.value.id,
          before,
          (await this.budgeting?.overspending(userId, month)) ?? [],
        );
      }
      return response;
    } catch (error) {
      throw this.translatePersistenceError(error);
    }
  }

  async reverseEntry(
    userId: string,
    entryId: string,
    rawKey: string | undefined,
    dto: ReverseJournalEntryDto,
  ): Promise<IdempotentValue<JournalEntry>> {
    CalendarDate.create(dto.postedOn);
    const key = this.key(rawKey);
    const now = this.clock.now();
    const effectiveAt = instant(dto.effectiveAt, now);
    try {
      const result = await this.idempotency.execute(
        {
          scopeId: EntityId.create(userId),
          operation: IdempotencyOperation.create(`ledger.entries.reverse:${entryId}`),
          key,
          requestFingerprint: fingerprint(dto),
        },
        async (transaction) => {
          const original = await this.repository.findOwnedEntry(transaction, userId, entryId);
          this.assertReversible(original);
          const reversal = await this.repository.reverse(transaction, original, {
            userId,
            actorUserId: userId,
            postedOn: dto.postedOn,
            effectiveAt,
            createdAt: now.toDate(),
            note: dto.note,
            idempotencyKeyHash: key.toHash(),
          });
          await this.fx.copyReversalSnapshot(
            transaction,
            original.id,
            reversal.id,
            userId,
            now.toDate(),
          );
          return asJsonObject({
            entry: await this.repository.findOwnedEntry(transaction, userId, reversal.id),
          });
        },
      );
      return { value: result.value.entry as unknown as JournalEntry, replayed: result.replayed };
    } catch (error) {
      throw this.translatePersistenceError(error);
    }
  }

  async correctEntry(
    userId: string,
    entryId: string,
    rawKey: string | undefined,
    dto: CorrectJournalEntryDto,
  ): Promise<IdempotentValue<{ reversal: JournalEntry; replacement: JournalEntry }>> {
    await this.validateManualCommand(userId, dto);
    const month = dto.postedOn.slice(0, 7);
    const before =
      dto.economicType === 'external_expense' && dto.categoryId
        ? await this.budgeting?.overspending(userId, month)
        : undefined;
    const key = this.key(rawKey);
    const now = this.clock.now();
    try {
      const result = await this.idempotency.execute(
        {
          scopeId: EntityId.create(userId),
          operation: IdempotencyOperation.create(`ledger.entries.correct:${entryId}`),
          key,
          requestFingerprint: fingerprint(dto),
        },
        async (transaction) => {
          const original = await this.repository.findOwnedEntry(transaction, userId, entryId);
          this.assertReversible(original);
          const reversal = await this.repository.reverse(transaction, original, {
            userId,
            actorUserId: userId,
            postedOn: dto.postedOn,
            effectiveAt: instant(dto.effectiveAt, now),
            createdAt: now.toDate(),
            idempotencyKeyHash: derivedHash(key.toHash(), 'reversal'),
          });
          await this.fx.copyReversalSnapshot(
            transaction,
            original.id,
            reversal.id,
            userId,
            now.toDate(),
          );
          const replacement = await this.repository.post(transaction, {
            ...this.toCommand(userId, dto, derivedHash(key.toHash(), 'replacement'), now),
            replacesEntryId: original.id,
          });
          await this.fx.snapshotPostedEntry(
            transaction,
            replacement,
            userId,
            dto.postedOn,
            now.toDate(),
          );
          const persistedReversal = await this.repository.findOwnedEntry(
            transaction,
            userId,
            reversal.id,
          );
          const persistedReplacement = await this.repository.findOwnedEntry(
            transaction,
            userId,
            replacement.id,
          );
          return asJsonObject({
            reversal: persistedReversal,
            replacement: persistedReplacement,
          });
        },
      );
      const response = {
        value: {
          reversal: result.value.reversal as unknown as JournalEntry,
          replacement: result.value.replacement as unknown as JournalEntry,
        },
        replayed: result.replayed,
      };
      if (!response.replayed && before) {
        await this.notifyNewOverspending(
          userId,
          response.value.replacement.id,
          before,
          (await this.budgeting?.overspending(userId, month)) ?? [],
        );
      }
      return response;
    } catch (error) {
      throw this.translatePersistenceError(error);
    }
  }

  private async notifyNewOverspending(
    userId: string,
    sourceEntryId: string,
    before: BudgetOverspendingNotification[],
    after: BudgetOverspendingNotification[],
  ): Promise<void> {
    const previouslyOverspent = new Set(before.map((snapshot) => snapshot.ruleId));
    for (const snapshot of after) {
      if (!previouslyOverspent.has(snapshot.ruleId)) {
        await this.notificationTrigger?.budgetOverspent(userId, sourceEntryId, snapshot);
      }
    }
  }

  async list(userId: string, dto: ListJournalEntriesDto): Promise<JournalListPage> {
    if (dto.dateFrom) CalendarDate.create(dto.dateFrom);
    if (dto.dateTo) CalendarDate.create(dto.dateTo);
    if (dto.dateFrom && dto.dateTo && dto.dateFrom > dto.dateTo) {
      throw new ApplicationError(
        HttpStatus.BAD_REQUEST,
        'BAD_REQUEST',
        'dateFrom must not be after dateTo',
      );
    }
    try {
      return await this.repository.list(userId, {
        dateFrom: dto.dateFrom,
        dateTo: dto.dateTo,
        limit: dto.limit ?? 25,
        cursor: dto.cursor,
      });
    } catch (error) {
      if (!(error instanceof InvalidJournalCursorError)) throw error;
      throw new ApplicationError(HttpStatus.BAD_REQUEST, 'BAD_REQUEST', 'Invalid journal cursor');
    }
  }

  private async validateManualCommand(userId: string, dto: CreateJournalEntryDto): Promise<void> {
    CalendarDate.create(dto.postedOn);
    const amount = ExactDecimal.create(dto.amount);
    if (!amount.isPositive()) {
      throw new ApplicationError(
        HttpStatus.UNPROCESSABLE_ENTITY,
        'UNPROCESSABLE_ENTITY',
        'Amount must be greater than zero',
      );
    }
    if (dto.categoryId) {
      await this.categories.assertJournalCategory(userId, dto.categoryId, dto.economicType);
    }
    if (dto.economicType === 'internal_transfer') {
      if (
        !dto.sourceAccountId ||
        !dto.destinationAccountId ||
        dto.sourceAccountId === dto.destinationAccountId ||
        dto.accountId ||
        dto.adjustmentDirection
      ) {
        throw semanticError('Transfers require distinct sourceAccountId and destinationAccountId');
      }
      return;
    }
    if (dto.sourceAccountId || dto.destinationAccountId) {
      throw semanticError('Source and destination accounts are only valid for transfers');
    }
    if (dto.economicType === 'adjustment') {
      if (!dto.adjustmentDirection) {
        throw semanticError('Adjustments require adjustmentDirection');
      }
    } else if (dto.adjustmentDirection) {
      throw semanticError('adjustmentDirection is only valid for adjustments');
    }
  }

  private toCommand(
    userId: string,
    dto: CreateJournalEntryDto,
    keyHash: string,
    now: UtcInstant,
  ): PostJournalCommand {
    return {
      userId,
      actorUserId: userId,
      economicType: dto.economicType,
      amount: ExactDecimal.create(dto.amount).toString(),
      currency: dto.currency,
      postedOn: CalendarDate.create(dto.postedOn).toString(),
      effectiveAt: instant(dto.effectiveAt, now),
      createdAt: now.toDate(),
      accountId: dto.accountId,
      sourceAccountId: dto.sourceAccountId,
      destinationAccountId: dto.destinationAccountId,
      adjustmentDirection: dto.adjustmentDirection,
      categoryId: dto.categoryId,
      note: dto.note,
      sourceModule: 'manual',
      idempotencyKeyHash: keyHash,
    };
  }

  private key(value: string | undefined): IdempotencyKey {
    if (!value) {
      throw new ApplicationError(
        HttpStatus.BAD_REQUEST,
        'BAD_REQUEST',
        'Idempotency-Key header is required',
      );
    }
    try {
      return IdempotencyKey.create(value);
    } catch {
      throw new ApplicationError(
        HttpStatus.BAD_REQUEST,
        'BAD_REQUEST',
        'Idempotency-Key header is invalid',
      );
    }
  }

  private assertReversible(entry: JournalEntry): void {
    if (entry.reversesEntryId) {
      throw new ApplicationError(
        HttpStatus.CONFLICT,
        'CONFLICT',
        'A reversal entry cannot itself be reversed or corrected',
      );
    }
  }

  private translatePersistenceError(error: unknown): Error {
    if (error instanceof ApplicationError) return error;
    if (error instanceof NoResultError) {
      return new ApplicationError(HttpStatus.NOT_FOUND, 'NOT_FOUND', 'Journal entry was not found');
    }
    const code =
      typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
    if (code === '23505') {
      return new ApplicationError(
        HttpStatus.CONFLICT,
        'CONFLICT',
        'The journal entry was already reversed, replaced, or posted',
      );
    }
    if (code === '23503' || code === '23514') {
      return semanticError('Journal ownership or balance invariant failed');
    }
    if (error instanceof Error && /owned account|transfer requires/i.test(error.message)) {
      return new ApplicationError(HttpStatus.NOT_FOUND, 'NOT_FOUND', 'Owned account was not found');
    }
    return error instanceof Error ? error : new Error('Journal persistence failed');
  }
}

function instant(value: string | undefined, fallback: UtcInstant): Date {
  try {
    return value ? UtcInstant.create(value).toDate() : fallback.toDate();
  } catch {
    throw semanticError('effectiveAt must be a valid UTC instant');
  }
}

function fingerprint(value: object): RequestFingerprint {
  return RequestFingerprint.fromCanonicalRequest(JSON.stringify(value));
}

function derivedHash(hash: string, purpose: string): string {
  return createHash('sha256').update(`${hash}:${purpose}`, 'utf8').digest('hex');
}

function asJsonObject(value: object): Record<string, JsonValue> {
  return value as Record<string, JsonValue>;
}

function semanticError(message: string): ApplicationError {
  return new ApplicationError(HttpStatus.UNPROCESSABLE_ENTITY, 'UNPROCESSABLE_ENTITY', message);
}
