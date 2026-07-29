import { ReportCalculator } from './report-calculator';
import type { ForecastSource, ReportSummary } from './reporting.types';

describe('ReportCalculator', () => {
  const calculator = new ReportCalculator();

  it('keeps exact decimals and transfers out of income, expense, and cash flow', () => {
    const sources: ForecastSource[] = [
      source('income', '9007199254740993.123456789012'),
      source('expense', '0.123456789011'),
      source('transfer', '9999999999999999.999999999999'),
    ];

    expect(calculator.forecastSummary('HUF', sources)).toMatchObject({
      income: '9007199254740993.123456789012',
      expense: '0.123456789011',
      transfer: '9999999999999999.999999999999',
      netCashFlow: '9007199254740993.000000000001',
    });
  });

  it('excludes unavailable sources and propagates explicit completeness and freshness', () => {
    const sources: ForecastSource[] = [
      source('income', '10', {
        conversionStatus: 'stale',
        provider: 'synthetic-provider',
        rateAt: '2026-07-01T00:00:00.000Z',
        fetchedAt: '2026-07-02T00:00:00.000Z',
      }),
      {
        ...source('expense', '20'),
        convertedAmount: undefined,
        conversionStatus: 'unavailable',
        provider: null,
        rateAt: null,
        fetchedAt: null,
      },
    ];

    expect(calculator.forecastSummary('HUF', sources)).toEqual({
      currency: 'HUF',
      income: '10',
      expense: '0',
      transfer: '0',
      adjustmentNet: '0',
      tradeCashNet: '0',
      netCashFlow: '10',
      conversion: {
        status: 'unavailable',
        complete: false,
        includedSourceCount: 1,
        unavailableSourceCount: 1,
        staleSourceCount: 1,
        providers: ['synthetic-provider'],
        oldestRateAt: '2026-07-01T00:00:00.000Z',
        newestFetchedAt: '2026-07-02T00:00:00.000Z',
      },
    });
  });

  it('combines posted and forecast projections without relabeling cash flow as balance', () => {
    const posted = summary('100', '40', '25', '5', '-2', '63');
    const forecast = summary('20', '10', '7', '0', '0', '10');
    expect(calculator.combine(posted, forecast)).toMatchObject({
      income: '120',
      expense: '50',
      transfer: '32',
      adjustmentNet: '5',
      tradeCashNet: '-2',
      netCashFlow: '73',
    });
  });
});

function source(
  kind: ForecastSource['kind'],
  convertedAmount: string,
  overrides: Partial<ForecastSource> = {},
): ForecastSource {
  return {
    sourceKind: 'recurring_rule',
    sourceId: '11111111-1111-4111-8111-111111111111',
    sourceEntryId: 'recurring_rule:11111111-1111-4111-8111-111111111111:2026-07-01',
    label: 'Synthetic source',
    occurrenceOn: '2026-07-01',
    kind,
    categoryId: null,
    amount: convertedAmount,
    currency: 'HUF',
    convertedAmount,
    reportingCurrency: 'HUF',
    conversionStatus: 'available',
    provider: 'identity',
    rateAt: '2026-07-01T00:00:00.000Z',
    fetchedAt: '2026-07-01T00:00:00.000Z',
    ...overrides,
  };
}

function summary(
  income: string,
  expense: string,
  transfer: string,
  adjustmentNet: string,
  tradeCashNet: string,
  netCashFlow: string,
): ReportSummary {
  return {
    currency: 'HUF',
    income,
    expense,
    transfer,
    adjustmentNet,
    tradeCashNet,
    netCashFlow,
    conversion: {
      status: 'available',
      complete: true,
      includedSourceCount: 1,
      unavailableSourceCount: 0,
      staleSourceCount: 0,
      providers: ['identity'],
      oldestRateAt: '2026-07-01T00:00:00.000Z',
      newestFetchedAt: '2026-07-01T00:00:00.000Z',
    },
  };
}
