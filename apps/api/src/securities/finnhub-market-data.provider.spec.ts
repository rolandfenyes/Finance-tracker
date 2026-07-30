import { ConfigService } from '@nestjs/config';
import {
  FinnhubMarketDataProvider,
  parseJsonPreservingNumbers,
} from './finnhub-market-data.provider';

describe('Finnhub provider boundary', () => {
  it('preserves provider decimals and unsafe integers as text', () => {
    expect(
      parseJsonPreservingNumbers(
        '{"price":0.100000000000000001,"volume":9007199254740993,"nested":[1.25]}',
      ),
    ).toEqual({
      price: '0.100000000000000001',
      volume: '9007199254740993',
      nested: ['1.25'],
    });
  });

  it('maps Finnhub industry metadata correctly and never substitutes the IPO date', async () => {
    const fetchSpy = jest
      .spyOn(global, 'fetch')
      .mockResolvedValue(
        new Response(
          '{"name":"Synthetic Corp","exchange":"NASDAQ","currency":"USD",' +
            '"finnhubIndustry":"Software","ipo":"1999-01-01"}',
          { status: 200 },
        ),
      );
    const provider = new FinnhubMarketDataProvider(
      new ConfigService({
        FINNHUB_API_KEY: 'synthetic-test-key',
        FINNHUB_BASE_URL: 'https://example.test/api/v1',
        SECURITIES_PROVIDER_TIMEOUT_MS: 1000,
      }),
    );
    await expect(provider.metadata({ symbol: 'ACME', market: 'US' })).resolves.toMatchObject({
      industry: 'Software',
      sector: null,
    });
    const requested = fetchSpy.mock.calls[0]?.[0];
    const requestedUrl =
      typeof requested === 'string'
        ? requested
        : requested instanceof URL
          ? requested.href
          : requested?.url;
    expect(requestedUrl).toContain('/api/v1/stock/profile2');
    fetchSpy.mockRestore();
  });

  it('surfaces provider failures for queued retry instead of returning synthetic prices', async () => {
    const fetchSpy = jest
      .spyOn(global, 'fetch')
      .mockResolvedValue(new Response('{}', { status: 429 }));
    const provider = new FinnhubMarketDataProvider(
      new ConfigService({
        FINNHUB_API_KEY: 'synthetic-test-key',
        FINNHUB_BASE_URL: 'https://example.test/api/v1',
        SECURITIES_PROVIDER_TIMEOUT_MS: 1000,
      }),
    );
    await expect(provider.quote({ symbol: 'ACME', market: 'US' })).rejects.toThrow('status 429');
    fetchSpy.mockRestore();
  });
});
