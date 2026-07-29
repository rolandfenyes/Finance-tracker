import type { RoundingMode } from '../platform/decimal/rounding-policy';

export interface Currency {
  code: string;
  name: string;
  minorUnit: number;
  roundingMode: RoundingMode;
}

export interface UserCurrency extends Currency {
  isMain: boolean;
}

export type FxConversionStatus = 'available' | 'stale' | 'unavailable';

export interface FxConversionResult {
  status: FxConversionStatus;
  sourceAmount: string;
  sourceCurrency: string;
  targetCurrency: string;
  convertedAmount?: string;
  sourceRate?: string;
  targetRate?: string;
  conversionRate?: string;
  provider?: string;
  rateAt?: string;
  fetchedAt?: string;
  precision: number;
  roundingMode: RoundingMode;
  sourceQuoteId?: string;
  targetQuoteId?: string;
}

export interface ObservedFxQuote {
  id: string;
  provider: string;
  baseCurrency: 'EUR';
  quoteCurrency: string;
  rate: string;
  observedOn: string;
  observedAt: Date;
  fetchedAt: Date;
}

export interface ProviderFxQuote {
  provider: 'frankfurter';
  baseCurrency: 'EUR';
  quoteCurrency: string;
  rate: string;
  observedOn: string;
  observedAt: Date;
  fetchedAt: Date;
}

export interface FxProvider {
  readonly name: 'frankfurter';
  fetchEurQuote(currency: string, asOf: string): Promise<ProviderFxQuote | null>;
}

export interface ForecastFxAssumption {
  kind: 'forecast_assumption';
  sourceCurrency: string;
  targetCurrency: string;
  assumedRate: string;
  effectiveOn: string;
}
