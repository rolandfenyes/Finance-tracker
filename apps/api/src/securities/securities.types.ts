export type TradeSide = 'buy' | 'sell';
export type QuoteStatus = 'available' | 'delayed' | 'stale' | 'unavailable';

export interface MarketInstrumentIdentity {
  symbol: string;
  market: string;
}

export interface ProviderQuote {
  identity: MarketInstrumentIdentity;
  last: string | null;
  previousClose: string | null;
  dayHigh: string | null;
  dayLow: string | null;
  volume: string | null;
  currency: string;
  provider: string;
  quoteAt: string | null;
  retrievedAt: string;
  status: QuoteStatus;
}

export interface ProviderDailyPrice {
  identity: MarketInstrumentIdentity;
  tradingOn: string;
  open: string | null;
  high: string | null;
  low: string | null;
  close: string;
  volume: string | null;
  currency: string;
  provider: string;
  observedAt: string;
  retrievedAt: string;
}

export interface ProviderInstrumentMetadata {
  identity: MarketInstrumentIdentity;
  exchange: string | null;
  name: string | null;
  currency: string;
  sector: string | null;
  industry: string | null;
  beta: string | null;
  provider: string;
  observedAt: string;
}

export interface SecuritiesMarketDataProvider {
  quote(identity: MarketInstrumentIdentity): Promise<ProviderQuote>;
  history(
    identity: MarketInstrumentIdentity,
    from: string,
    to: string,
  ): Promise<ProviderDailyPrice[]>;
  metadata(identity: MarketInstrumentIdentity): Promise<ProviderInstrumentMetadata | null>;
}

export const SECURITIES_MARKET_DATA_PROVIDER = Symbol('SECURITIES_MARKET_DATA_PROVIDER');

export interface SecuritiesInstrument {
  id: string;
  symbol: string;
  market: string;
  exchange: string | null;
  name: string | null;
  currency: string;
  sector: string | null;
  industry: string | null;
  beta: string | null;
  metadata: {
    provider: string | null;
    observedAt: string | null;
  };
  active: boolean;
}

export interface SecuritiesQuote {
  status: QuoteStatus;
  last: string | null;
  previousClose: string | null;
  dayHigh: string | null;
  dayLow: string | null;
  volume: string | null;
  currency: string;
  provider: string;
  quoteAt: string | null;
  retrievedAt: string;
}

export interface SecuritiesTrade {
  id: string;
  instrumentId: string;
  side: TradeSide;
  quantity: string;
  unitPrice: string;
  fee: string;
  currency: string;
  notional: string;
  notionalBase: string;
  feeBase: string;
  baseCurrency: string;
  conversion: {
    status: 'available' | 'stale';
    rate: string;
    provider: string;
    rateAt: string;
    fetchedAt: string;
  };
  executedAt: string;
  tradedOn: string;
  note: string | null;
  cashJournalEntryId: string;
  feeJournalEntryId: string | null;
  reversedByCashJournalEntryId: string | null;
  reversedByFeeJournalEntryId: string | null;
}

export interface ImportPreviewRow {
  row: number;
  status: 'valid' | 'error' | 'ignored';
  kind: 'trade' | 'cash' | 'ignored';
  errors: string[];
  trade?: {
    symbol: string;
    market: string;
    side: TradeSide;
    quantity: string;
    unitPrice: string;
    fee: string;
    currency: string;
    executedAt: string;
    note: string | null;
  };
  cash?: {
    direction: 'deposit' | 'withdrawal';
    amount: string;
    currency: string;
    occurredOn: string;
    note: string | null;
  };
}
