import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import type { FxProvider, ProviderFxQuote } from './currency.types';

@Injectable()
export class FrankfurterFxProvider implements FxProvider {
  readonly name = 'frankfurter' as const;
  private readonly timeoutMs: number;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {
    this.timeoutMs = config.getOrThrow<number>('FX_PROVIDER_TIMEOUT_MS');
  }

  async fetchEurQuote(currency: string, asOf: string): Promise<ProviderFxQuote | null> {
    CurrencyCode.create(currency);
    CalendarDate.create(asOf);
    if (currency === 'EUR') return null;
    const response = await fetch(
      `https://api.frankfurter.dev/v2/rate/EUR/${encodeURIComponent(currency)}.csv?date=${encodeURIComponent(asOf)}`,
      {
        headers: { accept: 'text/csv', 'user-agent': 'MyMoneyMap-FX/2.0' },
        signal: AbortSignal.timeout(this.timeoutMs),
      },
    );
    if (response.status === 404) return null;
    if (!response.ok) throw new Error(`Frankfurter request failed with status ${response.status}`);
    const row = parseSingleRateCsv(await response.text());
    if (!row || row.base !== 'EUR' || row.quote !== currency || row.date > asOf) return null;
    const rate = ExactDecimal.create(row.rate);
    if (!rate.isPositive()) return null;
    const observedAt = new Date(`${row.date}T00:00:00.000Z`);
    const fetchedAt = this.clock.now().toDate();
    if (observedAt > fetchedAt) return null;
    return {
      provider: 'frankfurter',
      baseCurrency: 'EUR',
      quoteCurrency: currency,
      rate: rate.toString(),
      observedOn: row.date,
      observedAt,
      fetchedAt,
    };
  }
}

export function parseSingleRateCsv(
  body: string,
): { date: string; base: string; quote: string; rate: string } | null {
  const lines = body.trim().split(/\r?\n/);
  if (lines.length !== 2 || lines[0] !== 'date,base,quote,rate') return null;
  const [date, base, quote, rate, extra] = lines[1]!.split(',');
  if (
    extra !== undefined ||
    !date ||
    !base ||
    !quote ||
    !rate ||
    !/^\d{4}-\d{2}-\d{2}$/.test(date)
  ) {
    return null;
  }
  try {
    CalendarDate.create(date);
    CurrencyCode.create(base);
    CurrencyCode.create(quote);
    ExactDecimal.create(rate);
    return { date, base, quote, rate };
  } catch {
    return null;
  }
}
