import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import type { UserRole } from '../identity/identity.types';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { ApplicationError } from '../platform/http/application-error';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { EntitlementsService } from '../users/entitlements.service';
import type {
  CreateRecurringRuleDto,
  RecurringRulesQueryDto,
  RecurringRulesResponseDto,
  UpdateRecurringRuleDto,
} from './recurrence.dto';
import {
  expandRecurrence,
  InvalidRecurrenceRuleError,
  parseRecurrenceRule,
  RECURRENCE_ITERATION_LIMIT,
} from './recurrence-rule';
import { RecurrenceRepository, type RecurringRuleWrite } from './recurrence.repository';
import type { RecurrenceEconomicType, RecurringRule } from './recurrence.types';

@Injectable()
export class RecurrenceService {
  constructor(
    @Inject(RecurrenceRepository) private readonly repository: RecurrenceRepository,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async rules(userId: string, query: RecurringRulesQueryDto): Promise<RecurringRulesResponseDto> {
    const range = validateRange(query);
    const rules = await this.repository.listRules(userId);
    return {
      items: rules.map((rule) => withForecast(rule, range)),
    };
  }

  async create(
    userId: string,
    role: UserRole,
    dto: CreateRecurringRuleDto,
  ): Promise<RecurringRulesResponseDto> {
    const values = normalize(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role !== role || user.role === 'admin') throw forbidden();
        this.entitlements.assertWithinQuota(
          user.role,
          'activeScheduledItems',
          await this.repository.countRules(userId, transaction),
        );
        await this.assertReferences(transaction, userId, values);
        await this.repository.createRule(transaction, userId, values, this.clock.now().toDate());
      });
      return this.rules(userId, {});
    } catch (error) {
      throw translate(error);
    }
  }

  async update(
    userId: string,
    ruleId: string,
    dto: UpdateRecurringRuleDto,
  ): Promise<RecurringRulesResponseDto> {
    assertNonEmptyPatch(dto);
    const existing = await this.repository.rule(userId, ruleId);
    if (!existing || existing.goalId !== null) throw notFound();
    const values = normalize({
      title: dto.title ?? existing.title,
      amount: dto.amount ?? existing.amount,
      currency: dto.currency ?? existing.currency,
      economicType: dto.economicType ?? existing.economicType,
      startsOn: dto.startsOn ?? existing.startsOn,
      rrule: dto.rrule ?? existing.rrule,
      categoryId: dto.categoryId === undefined ? existing.categoryId : dto.categoryId,
    });
    try {
      await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.lockUser(transaction, userId))) throw forbidden();
        await this.assertReferences(transaction, userId, values);
        if (
          !(await this.repository.updateRule(
            transaction,
            userId,
            ruleId,
            values,
            this.clock.now().toDate(),
          ))
        ) {
          throw notFound();
        }
      });
      return this.rules(userId, {});
    } catch (error) {
      throw translate(error);
    }
  }

  async delete(userId: string, ruleId: string): Promise<void> {
    const existing = await this.repository.rule(userId, ruleId);
    if (!existing || existing.goalId !== null) throw notFound();
    if (!(await this.repository.deleteRule(userId, ruleId))) throw notFound();
  }

  private async assertReferences(
    transaction: Parameters<RecurrenceRepository['currencyMembershipExists']>[2],
    userId: string,
    values: RecurringRuleWrite,
  ): Promise<void> {
    if (!(await this.repository.currencyMembershipExists(userId, values.currency, transaction))) {
      throw semanticError('Recurring-rule currency must be selected by the current user');
    }
    if (values.economicType === 'transfer' && values.categoryId !== null) {
      throw semanticError('Transfer forecasts cannot use an income or spending category');
    }
    if (
      values.categoryId !== null &&
      !(await this.repository.categoryMatches(
        userId,
        values.categoryId,
        values.economicType,
        transaction,
      ))
    ) {
      throw semanticError('Recurring-rule category must be owned and match its economic type');
    }
  }
}

function withForecast(
  rule: RecurringRule,
  range: { from: string; to: string } | null,
): RecurringRule & {
  forecast: {
    from: string;
    to: string;
    occurrences: string[];
    truncated: boolean;
    iterationLimit: number;
  } | null;
} {
  if (range === null) return { ...rule, forecast: null };
  const expansion = expandRecurrence(rule.startsOn, rule.rrule, range.from, range.to);
  return {
    ...rule,
    forecast: {
      from: range.from,
      to: range.to,
      occurrences: expansion.dates,
      truncated: expansion.truncated,
      iterationLimit: RECURRENCE_ITERATION_LIMIT,
    },
  };
}

function normalize(dto: {
  title: string;
  amount: string;
  currency: string;
  economicType: RecurrenceEconomicType;
  startsOn: string;
  rrule: string;
  categoryId?: string | null;
}): RecurringRuleWrite {
  CalendarDate.create(dto.startsOn);
  const amount = ExactDecimal.create(dto.amount);
  if (!amount.isPositive()) throw semanticError('Amount must be greater than zero');
  let parsed;
  try {
    parsed = parseRecurrenceRule(dto.rrule);
  } catch (error) {
    if (error instanceof InvalidRecurrenceRuleError) {
      throw semanticError(error.message);
    }
    throw error;
  }
  return {
    title: dto.title.trim(),
    amount: amount.toString(),
    currency: dto.currency,
    economicType: dto.economicType,
    startsOn: dto.startsOn,
    rrule: parsed.canonical,
    categoryId: dto.categoryId ?? null,
    goalId: null,
  };
}

function validateRange(query: RecurringRulesQueryDto): { from: string; to: string } | null {
  if ((query.from === undefined) !== (query.to === undefined)) {
    throw new ApplicationError(
      HttpStatus.BAD_REQUEST,
      'BAD_REQUEST',
      'from and to must be supplied together',
    );
  }
  if (query.from === undefined || query.to === undefined) return null;
  CalendarDate.create(query.from);
  CalendarDate.create(query.to);
  if (query.from > query.to) {
    throw new ApplicationError(HttpStatus.BAD_REQUEST, 'BAD_REQUEST', 'from must not be after to');
  }
  return { from: query.from, to: query.to };
}

function assertNonEmptyPatch(value: object): void {
  if (Object.values(value).every((item) => item === undefined)) {
    throw new ApplicationError(
      HttpStatus.BAD_REQUEST,
      'BAD_REQUEST',
      'At least one field is required',
    );
  }
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function notFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'Recurring rule was not found');
}

function semanticError(message: string): ApplicationError {
  return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', message);
}

function translate(error: unknown): Error {
  if (error instanceof ApplicationError) return error;
  const code =
    typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
  if (code === '23503' || code === '23514') {
    return semanticError('Recurring-rule ownership or financial invariant failed');
  }
  return error instanceof Error ? error : new Error('Recurrence persistence failed');
}
