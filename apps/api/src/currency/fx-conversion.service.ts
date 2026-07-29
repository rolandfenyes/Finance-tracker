import { Inject, Injectable } from '@nestjs/common';
import type { Transaction } from 'kysely';
import type { DatabaseSchema } from '../platform/database/database.types';
import { CurrencyAmount } from '../platform/decimal/currency-amount';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { CurrencyRepository } from './currency.repository';
import type { FxConversionResult, ForecastFxAssumption } from './currency.types';

const EUR = 'EUR';
const CROSS_RATE_POLICY = RoundingPolicy.create(18, 'HALF_EVEN');
const DIVISION_POLICY = RoundingPolicy.create(36, 'HALF_EVEN');

@Injectable()
export class FxConversionService {
  constructor(
    @Inject(CurrencyRepository) private readonly repository: CurrencyRepository,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async convertObserved(
    sourceAmount: string,
    sourceCurrency: string,
    targetCurrency: string,
    asOf: string,
  ): Promise<FxConversionResult> {
    CurrencyCode.create(sourceCurrency);
    CurrencyCode.create(targetCurrency);
    CalendarDate.create(asOf);
    const amount = CurrencyAmount.create(sourceAmount, sourceCurrency);
    const target = await this.repository.currency(targetCurrency);
    if (!target) throw new Error('Target currency is not supported');
    if (!(await this.repository.currency(sourceCurrency))) {
      throw new Error('Source currency is not supported');
    }
    const rounding = RoundingPolicy.create(target.minorUnit, target.roundingMode);
    if (asOf > this.clock.now().toString().slice(0, 10)) {
      return unavailable(amount.amount.toString(), sourceCurrency, targetCurrency, target);
    }
    if (sourceCurrency === targetCurrency) {
      const rateAt = `${asOf}T00:00:00.000Z`;
      return {
        status: 'available',
        sourceAmount: amount.amount.toString(),
        sourceCurrency,
        targetCurrency,
        convertedAmount: amount.amount.round(rounding).toString(),
        sourceRate: '1',
        targetRate: '1',
        conversionRate: '1',
        provider: 'identity',
        rateAt,
        fetchedAt: rateAt,
        precision: target.minorUnit,
        roundingMode: target.roundingMode,
      };
    }

    const [sourceQuote, targetQuote] = await Promise.all([
      sourceCurrency === EUR ? null : this.repository.quoteAsOf(sourceCurrency, asOf),
      targetCurrency === EUR ? null : this.repository.quoteAsOf(targetCurrency, asOf),
    ]);
    if ((sourceCurrency !== EUR && !sourceQuote) || (targetCurrency !== EUR && !targetQuote)) {
      return unavailable(amount.amount.toString(), sourceCurrency, targetCurrency, target);
    }

    const sourceRate = ExactDecimal.create(sourceQuote?.rate ?? '1');
    const targetRate = ExactDecimal.create(targetQuote?.rate ?? '1');
    const conversionRate = targetRate.divide(sourceRate, CROSS_RATE_POLICY);
    const converted = amount.amount
      .divide(sourceRate, DIVISION_POLICY)
      .multiply(targetRate)
      .round(rounding);
    const observations = [sourceQuote, targetQuote].filter((quote) => quote !== null);
    const rateAt = new Date(
      Math.min(...observations.map((quote) => quote.observedAt.getTime())),
    ).toISOString();
    const fetchedAt = new Date(
      Math.max(...observations.map((quote) => quote.fetchedAt.getTime())),
    ).toISOString();
    const stale = observations.some((quote) => quote.observedOn !== asOf);
    return {
      status: stale ? 'stale' : 'available',
      sourceAmount: amount.amount.toString(),
      sourceCurrency,
      targetCurrency,
      convertedAmount: converted.toString(),
      sourceRate: sourceRate.toString(),
      targetRate: targetRate.toString(),
      conversionRate: conversionRate.toString(),
      provider: 'frankfurter',
      rateAt,
      fetchedAt,
      precision: target.minorUnit,
      roundingMode: target.roundingMode,
      sourceQuoteId: sourceQuote?.id,
      targetQuoteId: targetQuote?.id,
    };
  }

  convertForecast(
    sourceAmount: string,
    assumption: ForecastFxAssumption,
    targetMinorUnit: number,
    roundingMode: FxConversionResult['roundingMode'],
  ): { kind: 'forecast_assumption'; convertedAmount: string; assumption: ForecastFxAssumption } {
    if (assumption.kind !== 'forecast_assumption') {
      throw new Error('Forecast conversion requires an explicit forecast assumption');
    }
    const amount = CurrencyAmount.create(sourceAmount, assumption.sourceCurrency);
    const rate = ExactDecimal.create(assumption.assumedRate);
    if (!rate.isPositive()) throw new Error('Forecast FX assumption must be greater than zero');
    CalendarDate.create(assumption.effectiveOn);
    CurrencyCode.create(assumption.targetCurrency);
    return {
      kind: 'forecast_assumption',
      convertedAmount: amount.amount
        .multiply(rate)
        .round(RoundingPolicy.create(targetMinorUnit, roundingMode))
        .toString(),
      assumption,
    };
  }

  async snapshotPostedEntry(
    transaction: Transaction<DatabaseSchema>,
    entry: { id: string; legs: Array<{ amount: string; currency: string }> },
    userId: string,
    postedOn: string,
    createdAt: Date,
  ): Promise<void> {
    const nativeLeg = entry.legs[0];
    if (!nativeLeg) throw new Error('Posted journal entry has no financial leg');
    const main = await this.repository.mainCurrency(userId, transaction);
    if (!main) throw new Error('User main currency was not found');
    const result = await this.convertObservedWithExecutor(
      nativeLeg.amount,
      nativeLeg.currency,
      main,
      postedOn,
      transaction,
    );
    await this.repository.insertSnapshot(transaction, entry.id, userId, result, createdAt);
  }

  copyReversalSnapshot(
    transaction: Transaction<DatabaseSchema>,
    originalEntryId: string,
    reversalEntryId: string,
    userId: string,
    createdAt: Date,
  ): Promise<void> {
    return this.repository.copySnapshot(
      transaction,
      originalEntryId,
      reversalEntryId,
      userId,
      createdAt,
    );
  }

  private async convertObservedWithExecutor(
    sourceAmount: string,
    sourceCurrency: string,
    target: Awaited<ReturnType<CurrencyRepository['currency']>> & {},
    asOf: string,
    transaction: Transaction<DatabaseSchema>,
  ): Promise<FxConversionResult> {
    const amount = CurrencyAmount.create(sourceAmount, sourceCurrency);
    const rounding = RoundingPolicy.create(target.minorUnit, target.roundingMode);
    if (asOf > this.clock.now().toString().slice(0, 10)) {
      return unavailable(amount.amount.toString(), sourceCurrency, target.code, target);
    }
    if (sourceCurrency === target.code) {
      const rateAt = `${asOf}T00:00:00.000Z`;
      return {
        status: 'available',
        sourceAmount: amount.amount.toString(),
        sourceCurrency,
        targetCurrency: target.code,
        convertedAmount: amount.amount.round(rounding).toString(),
        sourceRate: '1',
        targetRate: '1',
        conversionRate: '1',
        provider: 'identity',
        rateAt,
        fetchedAt: rateAt,
        precision: target.minorUnit,
        roundingMode: target.roundingMode,
      };
    }
    const [sourceQuote, targetQuote] = await Promise.all([
      sourceCurrency === EUR
        ? null
        : this.repository.quoteAsOf(sourceCurrency, asOf, 'frankfurter', transaction),
      target.code === EUR
        ? null
        : this.repository.quoteAsOf(target.code, asOf, 'frankfurter', transaction),
    ]);
    if ((sourceCurrency !== EUR && !sourceQuote) || (target.code !== EUR && !targetQuote)) {
      return unavailable(amount.amount.toString(), sourceCurrency, target.code, target);
    }
    const sourceRate = ExactDecimal.create(sourceQuote?.rate ?? '1');
    const targetRate = ExactDecimal.create(targetQuote?.rate ?? '1');
    const observations = [sourceQuote, targetQuote].filter((quote) => quote !== null);
    return {
      status: observations.some((quote) => quote.observedOn !== asOf) ? 'stale' : 'available',
      sourceAmount: amount.amount.toString(),
      sourceCurrency,
      targetCurrency: target.code,
      convertedAmount: amount.amount
        .divide(sourceRate, DIVISION_POLICY)
        .multiply(targetRate)
        .round(rounding)
        .toString(),
      sourceRate: sourceRate.toString(),
      targetRate: targetRate.toString(),
      conversionRate: targetRate.divide(sourceRate, CROSS_RATE_POLICY).toString(),
      provider: 'frankfurter',
      rateAt: new Date(
        Math.min(...observations.map((quote) => quote.observedAt.getTime())),
      ).toISOString(),
      fetchedAt: new Date(
        Math.max(...observations.map((quote) => quote.fetchedAt.getTime())),
      ).toISOString(),
      precision: target.minorUnit,
      roundingMode: target.roundingMode,
      sourceQuoteId: sourceQuote?.id,
      targetQuoteId: targetQuote?.id,
    };
  }
}

function unavailable(
  sourceAmount: string,
  sourceCurrency: string,
  targetCurrency: string,
  target: { minorUnit: number; roundingMode: FxConversionResult['roundingMode'] },
): FxConversionResult {
  return {
    status: 'unavailable',
    sourceAmount,
    sourceCurrency,
    targetCurrency,
    precision: target.minorUnit,
    roundingMode: target.roundingMode,
  };
}
