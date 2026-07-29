import { Injectable } from '@nestjs/common';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import type { ForecastSource, ReportConversionSummary, ReportSummary } from './reporting.types';

@Injectable()
export class ReportCalculator {
  forecastSummary(currency: string, sources: readonly ForecastSource[]): ReportSummary {
    let income = zero();
    let expense = zero();
    let transfer = zero();
    let net = zero();
    for (const source of sources) {
      if (source.convertedAmount === undefined) continue;
      const amount = ExactDecimal.create(source.convertedAmount);
      if (source.kind === 'income') {
        income = income.add(amount);
        net = net.add(amount);
      } else if (source.kind === 'expense') {
        expense = expense.add(amount);
        net = net.subtract(amount);
      } else {
        transfer = transfer.add(amount);
      }
    }
    return {
      currency,
      income: income.toString(),
      expense: expense.toString(),
      transfer: transfer.toString(),
      adjustmentNet: '0',
      tradeCashNet: '0',
      netCashFlow: net.toString(),
      conversion: conversionSummary(sources),
    };
  }

  combine(posted: ReportSummary, forecast: ReportSummary): ReportSummary {
    if (posted.currency !== forecast.currency) {
      throw new Error('Report summaries must use the same reporting currency');
    }
    return {
      currency: posted.currency,
      income: add(posted.income, forecast.income),
      expense: add(posted.expense, forecast.expense),
      transfer: add(posted.transfer, forecast.transfer),
      adjustmentNet: add(posted.adjustmentNet, forecast.adjustmentNet),
      tradeCashNet: add(posted.tradeCashNet, forecast.tradeCashNet),
      netCashFlow: add(posted.netCashFlow, forecast.netCashFlow),
      conversion: mergeConversion(posted.conversion, forecast.conversion),
    };
  }
}

function conversionSummary(sources: readonly ForecastSource[]): ReportConversionSummary {
  const unavailable = sources.filter(({ conversionStatus }) => conversionStatus === 'unavailable');
  const stale = sources.filter(({ conversionStatus }) => conversionStatus === 'stale');
  const included = sources.filter(({ convertedAmount }) => convertedAmount !== undefined);
  const rateDates = included.flatMap(({ rateAt }) => (rateAt ? [rateAt] : []));
  const fetchedDates = included.flatMap(({ fetchedAt }) => (fetchedAt ? [fetchedAt] : []));
  return {
    status: unavailable.length > 0 ? 'unavailable' : stale.length > 0 ? 'stale' : 'available',
    complete: unavailable.length === 0,
    includedSourceCount: included.length,
    unavailableSourceCount: unavailable.length,
    staleSourceCount: stale.length,
    providers: [
      ...new Set(included.flatMap(({ provider }) => (provider ? [provider] : []))),
    ].sort(),
    oldestRateAt: rateDates.sort().at(0) ?? null,
    newestFetchedAt: fetchedDates.sort().at(-1) ?? null,
  };
}

function mergeConversion(
  posted: ReportConversionSummary,
  forecast: ReportConversionSummary,
): ReportConversionSummary {
  const oldest = [posted.oldestRateAt, forecast.oldestRateAt]
    .filter((value): value is string => value !== null)
    .sort()
    .at(0);
  const newest = [posted.newestFetchedAt, forecast.newestFetchedAt]
    .filter((value): value is string => value !== null)
    .sort()
    .at(-1);
  return {
    status:
      posted.status === 'unavailable' || forecast.status === 'unavailable'
        ? 'unavailable'
        : posted.status === 'stale' || forecast.status === 'stale'
          ? 'stale'
          : 'available',
    complete: posted.complete && forecast.complete,
    includedSourceCount: posted.includedSourceCount + forecast.includedSourceCount,
    unavailableSourceCount: posted.unavailableSourceCount + forecast.unavailableSourceCount,
    staleSourceCount: posted.staleSourceCount + forecast.staleSourceCount,
    providers: [...new Set([...posted.providers, ...forecast.providers])].sort(),
    oldestRateAt: oldest ?? null,
    newestFetchedAt: newest ?? null,
  };
}

function add(left: string, right: string): string {
  return ExactDecimal.create(left).add(ExactDecimal.create(right)).toString();
}

function zero(): ExactDecimal {
  return ExactDecimal.create('0');
}
