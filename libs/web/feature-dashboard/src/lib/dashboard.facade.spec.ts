import { TestBed } from '@angular/core/testing';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import { throwError, of } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { DashboardFacade } from './dashboard.facade';
import {
  filterMoreNavigation,
  filterProductNavigation,
  MORE_NAVIGATION,
  PRODUCT_NAVIGATION,
} from './product-navigation';

describe('DashboardFacade', () => {
  it('loads the frozen current-report operation and preserves backend-owned exact totals', async () => {
    const report = reportFixture({ nextCursor: 'opaque-next' });
    const current = vi.fn().mockReturnValue(of(report));
    const facade = configure(current);

    await facade.load();

    expect(current).toHaveBeenCalledTimes(1);
    expect(current.mock.calls[0]?.[0]).toEqual({ limit: 5 });
    expect(facade.state()).toMatchObject({
      status: 'ready',
      report: {
        posted: { income: '1000.00000000', expense: '125.75000000', netCashFlow: '874.25000000' },
        activity: { nextCursor: 'opaque-next' },
      },
    });
  });

  it('keeps authoritative totals unchanged when the opaque activity cursor changes', async () => {
    const first = reportFixture({ nextCursor: 'cursor-two' });
    const second = reportFixture({ nextCursor: null });
    second.activity.items = [{ ...first.activity.items[0]!, sourceEntryId: 'entry-page-two' }];
    const current = vi.fn().mockReturnValueOnce(of(first)).mockReturnValueOnce(of(second));
    const facade = configure(current);

    await facade.load();
    const firstTotals = facade.state().report?.posted;
    await facade.load();

    expect(facade.state().report?.posted).toEqual(firstTotals);
  });

  it('distinguishes entitlement gating and retryable errors without exposing response data', async () => {
    const forbidden = new HttpErrorResponse({
      status: 403,
      headers: new HttpHeaders({ 'x-request-id': 'request-safe-reference' }),
      error: { error: { code: 'FORBIDDEN' } },
    });
    const current = vi.fn().mockReturnValue(throwError(() => forbidden));
    const facade = configure(current);

    await facade.load();

    expect(facade.state()).toEqual({
      status: 'gated',
      report: null,
      requestId: 'request-safe-reference',
    });
  });

  it('uses the contract source counts for the empty state', async () => {
    const report = reportFixture({ nextCursor: null });
    report.activity.items = [];
    report.forecast.sources = [];
    report.budget.items = [];
    report.posted.conversion.includedSourceCount = 0;
    const facade = configure(vi.fn().mockReturnValue(of(report)));

    await facade.load();

    expect(facade.state().status).toBe('empty');
  });
});

describe('product navigation policy', () => {
  const entitlements = {
    administration: false,
    cashFlowRuleEditing: false,
    personalFinanceAccess: true,
    resources: {
      activeGoals: { allowed: false, limit: 0 },
      activeLoans: { allowed: false, limit: 0 },
      activeScheduledItems: { allowed: true, limit: 2 },
      categories: { allowed: true, limit: 2 },
      currencies: { allowed: true, limit: 2 },
    },
  } as const;

  it('filters navigation through typed resource entitlements rather than roles', () => {
    expect(
      filterProductNavigation(PRODUCT_NAVIGATION, entitlements).map((item) => item.id),
    ).toEqual(['home', 'activity', 'plan', 'more']);
    expect(
      filterMoreNavigation(MORE_NAVIGATION, entitlements).map((item) => item.id),
    ).not.toContain('loans');
  });

  it('returns no product routes without personal-finance access', () => {
    expect(
      filterProductNavigation(PRODUCT_NAVIGATION, {
        ...entitlements,
        personalFinanceAccess: false,
      }),
    ).toEqual([]);
  });
});

function configure(current: ReturnType<typeof vi.fn>): DashboardFacade {
  TestBed.configureTestingModule({
    providers: [
      DashboardFacade,
      { provide: ReportingService, useValue: { reportingControllerCurrent: current } },
    ],
  });
  return TestBed.inject(DashboardFacade);
}

function reportFixture(options: { nextCursor: string | null }): MonthReportResponseDto {
  const conversion = {
    complete: true,
    includedSourceCount: 2,
    newestFetchedAt: '2026-07-31T10:00:00.000Z',
    oldestRateAt: '2026-07-31T09:00:00.000Z',
    providers: ['synthetic-fx'],
    staleSourceCount: 0,
    status: 'available' as const,
    unavailableSourceCount: 0,
  };
  const summary = {
    adjustmentNet: '0.00000000',
    conversion: { ...conversion },
    currency: 'EUR',
    expense: '125.75000000',
    income: '1000.00000000',
    netCashFlow: '874.25000000',
    tradeCashNet: '0.00000000',
    transfer: '50.00000000',
  };
  return {
    period: {
      first: '2026-07-01',
      last: '2026-07-31',
      month: 7,
      timeZone: 'Europe/Budapest',
      year: 2026,
    },
    posted: { ...summary, conversion: { ...conversion } },
    forecast: {
      summary: { ...summary, conversion: { ...conversion } },
      sources: [
        {
          amount: '90.12500000',
          categoryId: null,
          conversionStatus: 'available',
          convertedAmount: '90.12500000',
          currency: 'EUR',
          fetchedAt: null,
          kind: 'expense',
          label: 'Synthetic forecast',
          occurrenceOn: '2026-07-20',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          sourceEntryId: 'forecast:2026-07-20',
          sourceId: 'forecast-source',
          sourceKind: 'recurring_rule',
        },
      ],
    },
    combinedProjection: { ...summary, conversion: { ...conversion } },
    budget: {
      allocation: { overAllocatedBy: '0', status: 'within_allocation', totalPercent: '75.5' },
      period: {
        currency: 'EUR',
        forecastIncome: '1000',
        forecastIncomeStatus: 'available',
        month: '2026-07',
      },
      items: [
        {
          assignedCategoryIds: [],
          createdAt: '2026-07-01T00:00:00.000Z',
          id: 'budget-rule',
          label: 'Needs',
          percent: '50',
          plan: {
            assignedCategorySpending: '125.75',
            currency: 'EUR',
            plannedAmount: '500',
            signedVariance: '374.25',
            status: 'available',
          },
          targetHint: null,
          updatedAt: '2026-07-01T00:00:00.000Z',
        },
      ],
    },
    activity: {
      nextCursor: options.nextCursor,
      items: [
        {
          amount: '125.75000000',
          categoryId: null,
          conversionStatus: 'available',
          convertedAmount: '125.75000000',
          currency: 'EUR',
          economicType: 'expense',
          effectiveAt: '2026-07-10T10:00:00.000Z',
          fetchedAt: null,
          kind: 'expense',
          note: null,
          postedOn: '2026-07-10',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          reversesEntryId: null,
          source: {},
          sourceEntryId: 'entry-page-one',
        },
      ],
    },
  };
}
