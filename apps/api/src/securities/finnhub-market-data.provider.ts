import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type {
  MarketInstrumentIdentity,
  ProviderDailyPrice,
  ProviderInstrumentMetadata,
  ProviderQuote,
  SecuritiesMarketDataProvider,
} from './securities.types';

type JsonScalar = string | boolean | null;
type JsonValue = JsonScalar | JsonValue[] | { [key: string]: JsonValue };

@Injectable()
export class FinnhubMarketDataProvider implements SecuritiesMarketDataProvider {
  private readonly apiKey: string | undefined;
  private readonly baseUrl: string;
  private readonly timeoutMs: number;

  constructor(@Inject(ConfigService) config: ConfigService) {
    this.apiKey = config.get<string>('FINNHUB_API_KEY');
    this.baseUrl = config.get<string>('FINNHUB_BASE_URL') ?? 'https://finnhub.io/api/v1';
    this.timeoutMs = config.get<number>('SECURITIES_PROVIDER_TIMEOUT_MS') ?? 5_000;
  }

  async quote(identity: MarketInstrumentIdentity): Promise<ProviderQuote> {
    const retrievedAt = new Date().toISOString();
    const data = await this.get('/quote', { symbol: providerSymbol(identity) });
    if (!object(data) || !positiveText(data.c)) {
      return {
        identity,
        last: null,
        previousClose: null,
        dayHigh: null,
        dayLow: null,
        volume: null,
        currency: 'USD',
        provider: 'finnhub',
        quoteAt: null,
        retrievedAt,
        status: 'unavailable',
      };
    }
    const timestamp = integerText(data.t);
    return {
      identity,
      last: decimalText(data.c),
      previousClose: nullableDecimal(data.pc),
      dayHigh: nullableDecimal(data.h),
      dayLow: nullableDecimal(data.l),
      volume: nullableDecimal(data.v),
      currency: text(data.currency)?.toUpperCase() ?? 'USD',
      provider: 'finnhub',
      quoteAt: timestamp ? new Date(Number(timestamp) * 1_000).toISOString() : retrievedAt,
      retrievedAt,
      status: 'delayed',
    };
  }

  async history(
    identity: MarketInstrumentIdentity,
    from: string,
    to: string,
  ): Promise<ProviderDailyPrice[]> {
    const data = await this.get('/stock/candle', {
      symbol: providerSymbol(identity),
      resolution: 'D',
      from: String(Date.parse(`${from}T00:00:00.000Z`) / 1_000),
      to: String(Date.parse(`${to}T23:59:59.000Z`) / 1_000),
    });
    if (!object(data) || data.s !== 'ok') return [];
    const timestamps = array(data.t);
    const closes = array(data.c);
    const retrievedAt = new Date().toISOString();
    return timestamps.flatMap((timestamp, index) => {
      const close = positiveText(closes[index]);
      const seconds = integerText(timestamp);
      if (!close || !seconds) return [];
      const observedAt = new Date(Number(seconds) * 1_000).toISOString();
      return [
        {
          identity,
          tradingOn: observedAt.slice(0, 10),
          open: nullableDecimal(array(data.o)[index]),
          high: nullableDecimal(array(data.h)[index]),
          low: nullableDecimal(array(data.l)[index]),
          close,
          volume: nullableDecimal(array(data.v)[index]),
          currency: text(data.currency)?.toUpperCase() ?? 'USD',
          provider: 'finnhub',
          observedAt,
          retrievedAt,
        },
      ];
    });
  }

  async metadata(identity: MarketInstrumentIdentity): Promise<ProviderInstrumentMetadata | null> {
    const data = await this.get('/stock/profile2', { symbol: providerSymbol(identity) });
    if (!object(data) || !text(data.name)) return null;
    const industry = text(data.finnhubIndustry);
    return {
      identity,
      exchange: text(data.exchange),
      name: text(data.name),
      currency: text(data.currency)?.toUpperCase() ?? 'USD',
      sector: null,
      industry,
      beta: nullableDecimal(data.beta),
      provider: 'finnhub',
      observedAt: new Date().toISOString(),
    };
  }

  private async get(path: string, parameters: Record<string, string>): Promise<JsonValue> {
    if (!this.apiKey) throw new Error('Finnhub is not configured');
    const url = new URL(
      path.replace(/^\/+/, ''),
      this.baseUrl.endsWith('/') ? this.baseUrl : `${this.baseUrl}/`,
    );
    for (const [key, value] of Object.entries(parameters)) url.searchParams.set(key, value);
    url.searchParams.set('token', this.apiKey);
    const response = await fetch(url, {
      headers: { accept: 'application/json', 'user-agent': 'MyMoneyMap-Securities/2.0' },
      signal: AbortSignal.timeout(this.timeoutMs),
    });
    if (!response.ok) throw new Error(`Finnhub request failed with status ${response.status}`);
    return parseJsonPreservingNumbers(await response.text());
  }
}

export function parseJsonPreservingNumbers(source: string): JsonValue {
  let output = '';
  let inString = false;
  let escaped = false;
  for (let index = 0; index < source.length; index += 1) {
    const character = source[index]!;
    if (inString) {
      output += character;
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === '"') inString = false;
      continue;
    }
    if (character === '"') {
      inString = true;
      output += character;
      continue;
    }
    if (character === '-' || /\d/.test(character)) {
      let end = index + 1;
      while (end < source.length && /[\d.eE+-]/.test(source[end]!)) end += 1;
      output += JSON.stringify(source.slice(index, end));
      index = end - 1;
      continue;
    }
    output += character;
  }
  return JSON.parse(output) as JsonValue;
}

function providerSymbol(identity: MarketInstrumentIdentity): string {
  return identity.market === 'US' ? identity.symbol : `${identity.symbol}.${identity.market}`;
}

function object(value: JsonValue): value is { [key: string]: JsonValue } {
  return value !== null && !Array.isArray(value) && typeof value === 'object';
}

function array(value: JsonValue | undefined): JsonValue[] {
  return Array.isArray(value) ? value : [];
}

function text(value: JsonValue | undefined): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function decimalText(value: JsonValue | undefined): string {
  const valueText = text(value);
  if (!valueText || !/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(valueText)) {
    throw new Error('Provider returned an invalid decimal');
  }
  return valueText;
}

function nullableDecimal(value: JsonValue | undefined): string | null {
  const valueText = text(value);
  return valueText && /^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(valueText) ? valueText : null;
}

function positiveText(value: JsonValue | undefined): string | null {
  const valueText = nullableDecimal(value);
  return valueText && !/^0(?:\.0+)?$/.test(valueText) && !valueText.startsWith('-')
    ? valueText
    : null;
}

function integerText(value: JsonValue | undefined): string | null {
  const valueText = text(value);
  return valueText && /^\d+$/.test(valueText) ? valueText : null;
}
