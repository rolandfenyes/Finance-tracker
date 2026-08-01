import { TestBed } from '@angular/core/testing';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ReportsFacade } from './reports.facade';

describe('ReportsFacade', () => {
  const month = vi.fn();
  let facade: ReportsFacade;
  beforeEach(() => {
    vi.clearAllMocks();
    TestBed.configureTestingModule({
      providers: [
        ReportsFacade,
        {
          provide: ReportingService,
          useValue: {
            reportingControllerMonth: month,
            reportingControllerYears: vi.fn().mockReturnValue(of({ items: [{ year: 2026 }] })),
            reportingControllerYear: vi.fn().mockReturnValue(of({ ...fixture(), months: [] })),
          },
        },
      ],
    });
    facade = TestBed.inject(ReportsFacade);
  });
  it('passes exact filters and opaque cursor to the generated reporting operation', async () => {
    month.mockReturnValue(of(fixture()));
    await facade.loadMonth(2026, 7, {
      kind: 'expense',
      currency: 'EUR',
      minAmount: '0.00000001',
      maxAmount: '999999999999.99999999',
      cursor: 'opaque-page',
    });
    expect(month).toHaveBeenCalledWith({
      year: 2026,
      month: 7,
      kind: 'expense',
      currency: 'EUR',
      minAmount: '0.00000001',
      maxAmount: '999999999999.99999999',
      cursor: 'opaque-page',
      limit: 25,
    });
  });
  it('appends only server activity while keeping authoritative totals stable across pages', async () => {
    const first = fixture();
    const second = fixture();
    second.posted.income = '999999.00';
    second.activity = {
      items: [{ ...first.activity.items[0]!, sourceEntryId: 'entry-two' }],
      nextCursor: null,
    };
    month.mockReturnValueOnce(of(first)).mockReturnValueOnce(of(second));
    await facade.loadMonth(2026, 7);
    await facade.loadMonth(2026, 7, { cursor: 'opaque-next' }, true);
    expect(facade.month().data?.posted.income).toBe('1000.00000000');
    expect(facade.month().data?.activity.items.map((item) => item.sourceEntryId)).toEqual([
      'entry-one',
      'entry-two',
    ]);
  });

  it('preserves the server-owned transfer and net cash-flow values without local aggregation', async () => {
    month.mockReturnValue(of(fixture()));
    await facade.loadMonth(2026, 7);
    expect(facade.month().data?.posted.transfer).toBe('500.00000000');
    expect(facade.month().data?.posted.netCashFlow).toBe('750.00000000');
  });
});

function fixture(): MonthReportResponseDto {
  const conversion = {
    complete: false,
    includedSourceCount: 1,
    newestFetchedAt: '2026-07-20T00:00:00.000Z',
    oldestRateAt: '2026-07-19T00:00:00.000Z',
    providers: ['synthetic'],
    staleSourceCount: 1,
    status: 'stale' as const,
    unavailableSourceCount: 1,
  };
  const summary = {
    adjustmentNet: '0.00000000',
    conversion: { ...conversion },
    currency: 'EUR',
    expense: '250.00000000',
    income: '1000.00000000',
    netCashFlow: '750.00000000',
    tradeCashNet: '0.00000000',
    transfer: '500.00000000',
  };
  return {
    period: {
      first: '2026-07-01',
      last: '2026-07-31',
      month: 7,
      timeZone: 'Europe/Budapest',
      year: 2026,
    },
    posted: { ...summary },
    forecast: { summary: { ...summary }, sources: [] },
    combinedProjection: { ...summary },
    budget: {
      allocation: { overAllocatedBy: '0', status: 'within_allocation', totalPercent: '100' },
      period: {
        currency: 'EUR',
        forecastIncome: '1000',
        forecastIncomeStatus: 'available',
        month: '2026-07',
      },
      items: [],
    },
    activity: {
      items: [
        {
          amount: '500.00000000',
          categoryId: null,
          conversionStatus: 'unavailable',
          currency: 'USD',
          economicType: 'internal_transfer',
          effectiveAt: '2026-07-20T00:00:00.000Z',
          fetchedAt: null,
          kind: 'transfer',
          note: null,
          postedOn: '2026-07-20',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          reversesEntryId: null,
          source: { module: 'manual', referenceId: null },
          sourceEntryId: 'entry-one',
        },
      ],
      nextCursor: 'opaque-next',
    },
  };
}
