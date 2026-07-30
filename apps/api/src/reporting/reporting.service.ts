import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import { BudgetingService } from '../budgeting/budgeting.service';
import { CurrencyService } from '../currency/currency.service';
import { FxConversionService } from '../currency/fx-conversion.service';
import type { FxConversionResult } from '../currency/currency.types';
import { expandRecurrence } from '../recurrence/recurrence-rule';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import { ApplicationError } from '../platform/http/application-error';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { UserTimeZone } from '../platform/time/user-time-zone';
import type {
  MonthReportQueryDto,
  MonthReportResponseDto,
  ReportYearsResponseDto,
  YearReportResponseDto,
} from './reporting.dto';
import { ReportCalculator } from './report-calculator';
import { InvalidReportCursorError, ReportingRepository } from './reporting.repository';
import type {
  ForecastSource,
  PostedAggregateRow,
  ReportFilters,
  ReportPeriod,
  ReportPeriodResult,
  ReportSummary,
} from './reporting.types';

const REPORT_TIME_ZONE = UserTimeZone.create('Europe/Budapest');

@Injectable()
export class ReportingService {
  constructor(
    @Inject(ReportingRepository) private readonly repository: ReportingRepository,
    @Inject(ReportCalculator) private readonly calculator: ReportCalculator,
    @Inject(CurrencyService) private readonly currencies: CurrencyService,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(BudgetingService) private readonly budgeting: BudgetingService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async currentMonth(userId: string, query: MonthReportQueryDto): Promise<MonthReportResponseDto> {
    const current = REPORT_TIME_ZONE.calendarDateAt(this.clock.now()).toString();
    return this.month(userId, Number(current.slice(0, 4)), Number(current.slice(5, 7)), query);
  }

  async month(
    userId: string,
    year: number,
    month: number,
    query: MonthReportQueryDto,
  ): Promise<MonthReportResponseDto> {
    const period = monthPeriod(year, month);
    const filters = normalizeFilters(query);
    assertAmountRange(filters);
    try {
      const [result, budget, activity] = await Promise.all([
        this.period(userId, period, filters),
        this.budgeting.rules(userId, `${year}-${pad(month)}`),
        this.repository.activity(
          userId,
          period.first,
          period.last,
          filters,
          query.limit ?? 25,
          query.cursor,
        ),
      ]);
      return { ...result, budget, activity };
    } catch (error) {
      if (error instanceof InvalidReportCursorError) {
        throw new ApplicationError(HttpStatus.BAD_REQUEST, 'BAD_REQUEST', error.message);
      }
      throw error;
    }
  }

  async years(userId: string): Promise<ReportYearsResponseDto> {
    const current = REPORT_TIME_ZONE.calendarDateAt(this.clock.now()).toString();
    const years = await this.repository.years(userId, Number(current.slice(0, 4)));
    return { items: years.map((year) => ({ year })) };
  }

  async year(userId: string, year: number): Promise<YearReportResponseDto> {
    assertYear(year);
    const months = await Promise.all(
      Array.from({ length: 12 }, (_, index) =>
        this.period(userId, monthPeriod(year, index + 1), {}),
      ),
    );
    const main = await this.currencies.mainCurrency(userId);
    let posted = emptySummary(main.code);
    let forecast = emptySummary(main.code);
    for (const month of months) {
      posted = this.calculator.combine(posted, month.posted);
      forecast = this.calculator.combine(forecast, month.forecast.summary);
    }
    return {
      period: yearPeriod(year),
      months: months.map((month) => ({
        period: month.period,
        posted: month.posted,
        forecast: month.forecast.summary,
        combinedProjection: month.combinedProjection,
      })),
      posted,
      forecast,
      combinedProjection: this.calculator.combine(posted, forecast),
    };
  }

  async notificationPeriod(
    userId: string,
    first: string,
    last: string,
  ): Promise<{
    period: ReportPeriod;
    currency: string;
    expense: string;
    income: string;
    netCashFlow: string;
    calculatedAt: string;
  }> {
    CalendarDate.create(first);
    CalendarDate.create(last);
    const result = await this.period(
      userId,
      {
        first,
        last,
        year: Number(first.slice(0, 4)),
        timeZone: REPORT_TIME_ZONE.toString(),
      },
      {},
    );
    return {
      period: result.period,
      currency: result.posted.currency,
      expense: result.posted.expense,
      income: result.posted.income,
      netCashFlow: result.posted.netCashFlow,
      calculatedAt: this.clock.now().toDate().toISOString(),
    };
  }

  private async period(
    userId: string,
    period: ReportPeriod,
    filters: ReportFilters,
  ): Promise<ReportPeriodResult> {
    const main = await this.currencies.mainCurrency(userId);
    const [postedRow, forecastSources] = await Promise.all([
      this.repository.postedSummary(userId, period.first, period.last, filters),
      this.forecastSources(userId, period, filters, main),
    ]);
    const posted = mapPosted(main.code, postedRow);
    const forecast = {
      sources: forecastSources,
      summary: this.calculator.forecastSummary(main.code, forecastSources),
    };
    return {
      period,
      posted,
      forecast,
      combinedProjection: this.calculator.combine(posted, forecast.summary),
    };
  }

  private async forecastSources(
    userId: string,
    period: ReportPeriod,
    filters: ReportFilters,
    main: Awaited<ReturnType<CurrencyService['mainCurrency']>>,
  ): Promise<ForecastSource[]> {
    const [incomes, rules] = await Promise.all([
      this.repository.basicIncomes(userId, period.first, period.last),
      this.repository.recurringRules(userId, period.last),
    ]);
    const raw = [
      ...incomes.flatMap((income) =>
        incomeOccurrences(income.validFrom, income.validTo, period.first, period.last).map(
          (occurrenceOn) => ({
            sourceKind: 'basic_income' as const,
            sourceId: income.id,
            label: income.label,
            occurrenceOn,
            kind: 'income' as const,
            categoryId: income.categoryId,
            amount: income.amount,
            currency: income.currency,
          }),
        ),
      ),
      ...rules.flatMap((rule) => {
        const expansion = expandRecurrence(rule.startsOn, rule.rrule, period.first, period.last);
        if (expansion.truncated) {
          throw new ApplicationError(
            HttpStatus.UNPROCESSABLE_ENTITY,
            'UNPROCESSABLE_ENTITY',
            `Recurring rule ${rule.id} exceeds the approved expansion limit`,
          );
        }
        return expansion.dates.map((occurrenceOn) => ({
          sourceKind: 'recurring_rule' as const,
          sourceId: rule.id,
          label: rule.title,
          occurrenceOn,
          kind: rule.economicType,
          categoryId: rule.categoryId,
          amount: rule.amount,
          currency: rule.currency,
        }));
      }),
    ].filter((source) => forecastMatches(source, filters));

    return Promise.all(
      raw.map(async (source): Promise<ForecastSource> => {
        const conversion = await this.forecastConversion(
          source.amount,
          source.currency,
          main,
          source.occurrenceOn,
        );
        return {
          ...source,
          sourceEntryId: `${source.sourceKind}:${source.sourceId}:${source.occurrenceOn}`,
          ...(conversion.convertedAmount === undefined
            ? {}
            : { convertedAmount: conversion.convertedAmount }),
          reportingCurrency: main.code,
          conversionStatus: conversion.status,
          provider: conversion.provider ?? null,
          rateAt: conversion.rateAt ?? null,
          fetchedAt: conversion.fetchedAt ?? null,
        };
      }),
    );
  }

  private forecastConversion(
    amount: string,
    currency: string,
    main: Awaited<ReturnType<CurrencyService['mainCurrency']>>,
    occurrenceOn: string,
  ): Promise<FxConversionResult> {
    if (currency !== main.code) {
      return this.fx.convertObserved(amount, currency, main.code, occurrenceOn);
    }
    const rounded = ExactDecimal.create(amount)
      .round(RoundingPolicy.create(main.minorUnit, main.roundingMode))
      .toString();
    const rateAt = `${occurrenceOn}T00:00:00.000Z`;
    return Promise.resolve({
      status: 'available',
      sourceAmount: amount,
      sourceCurrency: currency,
      targetCurrency: main.code,
      convertedAmount: rounded,
      sourceRate: '1',
      targetRate: '1',
      conversionRate: '1',
      provider: 'identity',
      rateAt,
      fetchedAt: rateAt,
      precision: main.minorUnit,
      roundingMode: main.roundingMode,
    });
  }
}

function mapPosted(currency: string, row: PostedAggregateRow): ReportSummary {
  return {
    currency,
    income: ExactDecimal.create(row.income).toString(),
    expense: ExactDecimal.create(row.expense).toString(),
    transfer: ExactDecimal.create(row.transfer).toString(),
    adjustmentNet: ExactDecimal.create(row.adjustmentNet).toString(),
    tradeCashNet: ExactDecimal.create(row.tradeCashNet).toString(),
    netCashFlow: ExactDecimal.create(row.netCashFlow).toString(),
    conversion: {
      status:
        row.unavailableSourceCount > 0
          ? 'unavailable'
          : row.staleSourceCount > 0
            ? 'stale'
            : 'available',
      complete: row.unavailableSourceCount === 0,
      includedSourceCount: row.includedSourceCount,
      unavailableSourceCount: row.unavailableSourceCount,
      staleSourceCount: row.staleSourceCount,
      providers: [...row.providers].sort(),
      oldestRateAt: row.oldestRateAt?.toISOString() ?? null,
      newestFetchedAt: row.newestFetchedAt?.toISOString() ?? null,
    },
  };
}

function emptySummary(currency: string): ReportSummary {
  return {
    currency,
    income: '0',
    expense: '0',
    transfer: '0',
    adjustmentNet: '0',
    tradeCashNet: '0',
    netCashFlow: '0',
    conversion: {
      status: 'available',
      complete: true,
      includedSourceCount: 0,
      unavailableSourceCount: 0,
      staleSourceCount: 0,
      providers: [],
      oldestRateAt: null,
      newestFetchedAt: null,
    },
  };
}

function normalizeFilters(query: MonthReportQueryDto): ReportFilters {
  return {
    ...(query.kind ? { kind: query.kind } : {}),
    ...(query.categoryId ? { categoryId: query.categoryId } : {}),
    ...(query.currency ? { currency: query.currency } : {}),
    ...(query.query ? { query: query.query.trim() } : {}),
    ...(query.minAmount ? { minAmount: ExactDecimal.create(query.minAmount).toString() } : {}),
    ...(query.maxAmount ? { maxAmount: ExactDecimal.create(query.maxAmount).toString() } : {}),
  };
}

function assertAmountRange(filters: ReportFilters): void {
  if (
    filters.minAmount &&
    filters.maxAmount &&
    ExactDecimal.create(filters.minAmount).compare(ExactDecimal.create(filters.maxAmount)) > 0
  ) {
    throw new ApplicationError(
      HttpStatus.UNPROCESSABLE_ENTITY,
      'UNPROCESSABLE_ENTITY',
      'minAmount must not exceed maxAmount',
    );
  }
}

function forecastMatches(
  source: {
    kind: 'income' | 'expense' | 'transfer';
    categoryId: string | null;
    currency: string;
    label: string;
    amount: string;
  },
  filters: ReportFilters,
): boolean {
  if (filters.kind && filters.kind !== source.kind) return false;
  if (filters.categoryId && filters.categoryId !== source.categoryId) return false;
  if (filters.currency && filters.currency !== source.currency) return false;
  if (
    filters.query &&
    !source.label.toLocaleLowerCase().includes(filters.query.toLocaleLowerCase())
  )
    return false;
  const amount = ExactDecimal.create(source.amount);
  if (filters.minAmount && amount.compare(ExactDecimal.create(filters.minAmount)) < 0) return false;
  if (filters.maxAmount && amount.compare(ExactDecimal.create(filters.maxAmount)) > 0) return false;
  return true;
}

function incomeOccurrences(
  validFrom: string,
  validTo: string | null,
  first: string,
  last: string,
): string[] {
  const occurrences: string[] = [];
  let current = `${first.slice(0, 7)}-01`;
  while (current <= last) {
    const monthLast = lastDay(Number(current.slice(0, 4)), Number(current.slice(5, 7)));
    const occurrence = validFrom > current ? validFrom : current;
    if (
      occurrence <= monthLast &&
      occurrence <= last &&
      (validTo === null || occurrence <= validTo)
    ) {
      occurrences.push(occurrence);
    }
    current = nextMonth(current);
  }
  return occurrences;
}

function monthPeriod(year: number, month: number): ReportPeriod {
  assertYear(year);
  if (!Number.isSafeInteger(month) || month < 1 || month > 12) {
    throw new ApplicationError(
      HttpStatus.BAD_REQUEST,
      'BAD_REQUEST',
      'Month must be between 1 and 12',
    );
  }
  return {
    first: `${year}-${pad(month)}-01`,
    last: lastDay(year, month),
    year,
    month,
    timeZone: REPORT_TIME_ZONE.toString(),
  };
}

function yearPeriod(year: number): ReportPeriod {
  assertYear(year);
  return {
    first: `${year}-01-01`,
    last: `${year}-12-31`,
    year,
    timeZone: REPORT_TIME_ZONE.toString(),
  };
}

function assertYear(year: number): void {
  if (!Number.isSafeInteger(year) || year < 1 || year > 9999) {
    throw new ApplicationError(
      HttpStatus.BAD_REQUEST,
      'BAD_REQUEST',
      'Year must be between 1 and 9999',
    );
  }
}

function lastDay(year: number, month: number): string {
  const day = new Date(Date.UTC(year, month, 0)).getUTCDate();
  return `${year}-${pad(month)}-${pad(day)}`;
}

function nextMonth(value: string): string {
  const year = Number(value.slice(0, 4));
  const month = Number(value.slice(5, 7));
  return month === 12 ? `${year + 1}-01-01` : `${year}-${pad(month + 1)}-01`;
}

function pad(value: number): string {
  return value.toString().padStart(2, '0');
}
