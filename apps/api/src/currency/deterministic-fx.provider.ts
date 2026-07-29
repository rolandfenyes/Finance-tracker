import type { FxProvider, ProviderFxQuote } from './currency.types';

export class DeterministicFxProvider implements FxProvider {
  readonly name = 'frankfurter' as const;
  private attempts = 0;

  constructor(
    private readonly quotes: Readonly<Record<string, string>>,
    private readonly fetchedAt = new Date('2026-07-29T12:00:00.000Z'),
    private readonly failuresBeforeSuccess = 0,
  ) {}

  fetchEurQuote(currency: string, asOf: string): Promise<ProviderFxQuote | null> {
    this.attempts += 1;
    if (this.attempts <= this.failuresBeforeSuccess) {
      return Promise.reject(new Error('Synthetic provider failure'));
    }
    const rate = this.quotes[`${asOf}:${currency}`];
    return Promise.resolve(
      rate
        ? {
            provider: 'frankfurter',
            baseCurrency: 'EUR',
            quoteCurrency: currency,
            rate,
            observedOn: asOf,
            observedAt: new Date(`${asOf}T00:00:00.000Z`),
            fetchedAt: this.fetchedAt,
          }
        : null,
    );
  }
}
