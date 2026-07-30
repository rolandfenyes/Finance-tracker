import { Injectable } from '@nestjs/common';
import type {
  MarketInstrumentIdentity,
  ProviderDailyPrice,
  ProviderInstrumentMetadata,
  ProviderQuote,
  SecuritiesMarketDataProvider,
} from './securities.types';

@Injectable()
export class DisabledMarketDataProvider implements SecuritiesMarketDataProvider {
  quote(identity: MarketInstrumentIdentity): Promise<ProviderQuote> {
    const retrievedAt = new Date().toISOString();
    return Promise.resolve({
      identity,
      last: null,
      previousClose: null,
      dayHigh: null,
      dayLow: null,
      volume: null,
      currency: 'USD',
      provider: 'disabled',
      quoteAt: null,
      retrievedAt,
      status: 'unavailable',
    });
  }

  history(
    identity: MarketInstrumentIdentity,
    from: string,
    to: string,
  ): Promise<ProviderDailyPrice[]> {
    void identity;
    void from;
    void to;
    return Promise.resolve([]);
  }

  metadata(identity: MarketInstrumentIdentity): Promise<ProviderInstrumentMetadata | null> {
    void identity;
    return Promise.resolve(null);
  }
}
