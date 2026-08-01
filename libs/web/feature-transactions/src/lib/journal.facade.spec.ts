import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import type { CreateJournalEntryDto } from '@mymoneymap/generated-api-client/models/create-journal-entry-dto';
import type { JournalEntryResponseDto } from '@mymoneymap/generated-api-client/models/journal-entry-response-dto';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import { LedgerService } from '@mymoneymap/generated-api-client/services/ledger.service';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import { BrowserIdempotencyKeyFactory } from '@mymoneymap/web-core';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { JournalFacade } from './journal.facade';

describe('JournalFacade', () => {
  const list = vi.fn<LedgerService['ledgerControllerList']>();
  const create = vi.fn<LedgerService['ledgerControllerCreate']>();
  const correct = vi.fn<LedgerService['ledgerControllerCorrect']>();
  const reverse = vi.fn<LedgerService['ledgerControllerReverse']>();
  const reportMonth = vi.fn<ReportingService['reportingControllerMonth']>();
  const reportCurrent = vi.fn<ReportingService['reportingControllerCurrent']>();
  let facade: JournalFacade;
  beforeEach(() => {
    vi.clearAllMocks();
    list.mockReturnValue(of({ items: [entryFixture()], nextCursor: 'opaque-next' }));
    reportMonth.mockReturnValue(of(reportFixture()));
    reportCurrent.mockReturnValue(of(reportFixture()));
    TestBed.configureTestingModule({
      providers: [
        JournalFacade,
        {
          provide: LedgerService,
          useValue: {
            ledgerControllerList: list,
            ledgerControllerCreate: create,
            ledgerControllerCorrect: correct,
            ledgerControllerReverse: reverse,
          },
        },
        {
          provide: ReportingService,
          useValue: {
            reportingControllerMonth: reportMonth,
            reportingControllerCurrent: reportCurrent,
          },
        },
        {
          provide: BrowserIdempotencyKeyFactory,
          useValue: { create: (): string => 'stable-command-key' },
        },
      ],
    });
    facade = TestBed.inject(JournalFacade);
  });

  it('passes date filters and the opaque cursor unchanged to the generated list operation', async () => {
    await facade.load({ dateFrom: '2026-07-01', dateTo: '2026-07-31', cursor: 'opaque-input' });
    expect(list).toHaveBeenCalledWith({
      dateFrom: '2026-07-01',
      dateTo: '2026-07-31',
      cursor: 'opaque-input',
      limit: 25,
    });
    expect(facade.state().nextCursor).toBe('opaque-next');
  });

  it('posts the exact DTO and uses the same idempotency key when one intent is retried', async () => {
    const body: CreateJournalEntryDto = {
      economicType: 'external_expense',
      amount: '12345678901234567890.12345678',
      currency: 'EUR',
      postedOn: '2026-07-20',
      accountId: '00000000-0000-4000-8000-000000000002',
    };
    create
      .mockReturnValueOnce(throwError(() => new HttpErrorResponse({ status: 422 })))
      .mockReturnValueOnce(of(entryFixture()));
    await facade.create(body);
    await facade.create(body);
    expect(create).toHaveBeenCalledTimes(2);
    expect(create.mock.calls.map((call) => call[0]['Idempotency-Key'])).toEqual([
      'stable-command-key',
      'stable-command-key',
    ]);
    expect(create.mock.calls[0]?.[0].body).toEqual(body);
    expect(facade.entry(entryFixture().id)).toEqual(entryFixture());
    expect(reportMonth).toHaveBeenCalledWith({ year: 2026, month: 7, limit: 5 });
    expect(reportCurrent).toHaveBeenCalledWith({ limit: 5 });
  });

  it('refreshes the journal after an uncertain result and retains retry compatibility', async () => {
    const body: CreateJournalEntryDto = {
      economicType: 'fee',
      amount: '1.00',
      currency: 'EUR',
      postedOn: '2026-07-20',
      accountId: '00000000-0000-4000-8000-000000000002',
    };
    create.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 503 })));
    await facade.create(body);
    expect(facade.createCommand.state().phase).toBe('uncertain');
    expect(list).toHaveBeenCalled();
    expect(reportMonth).not.toHaveBeenCalled();
    expect(reportCurrent).not.toHaveBeenCalled();
  });
});

function entryFixture(): JournalEntryResponseDto {
  return {
    actorUserId: '00000000-0000-4000-8000-000000000001',
    categoryId: null,
    createdAt: '2026-07-20T10:00:00.000Z',
    economicType: 'external_expense',
    effectiveAt: '2026-07-20T10:00:00.000Z',
    id: '00000000-0000-4000-8000-000000000010',
    legs: [
      {
        id: '00000000-0000-4000-8000-000000000011',
        accountId: '00000000-0000-4000-8000-000000000002',
        amount: '12345678901234567890.12345678',
        currency: 'EUR',
        side: 'credit',
      },
      {
        id: '00000000-0000-4000-8000-000000000012',
        accountId: null,
        amount: '12345678901234567890.12345678',
        currency: 'EUR',
        side: 'debit',
      },
    ],
    note: null,
    postedOn: '2026-07-20',
    replacesEntryId: null,
    reversesEntryId: null,
    source: { module: 'manual', referenceId: null },
    conversion: {
      sourceAmount: '12345678901234567890.12345678',
      sourceCurrency: 'EUR',
      targetCurrency: 'EUR',
      convertedAmount: '12345678901234567890.12345678',
      precision: 8,
      roundingMode: 'HALF_EVEN',
      status: 'available',
    },
  };
}

function reportFixture(): MonthReportResponseDto {
  const conversion = {
    complete: true,
    includedSourceCount: 0,
    newestFetchedAt: null,
    oldestRateAt: null,
    providers: [],
    staleSourceCount: 0,
    status: 'available' as const,
    unavailableSourceCount: 0,
  };
  const summary = {
    adjustmentNet: '0',
    conversion,
    currency: 'EUR',
    expense: '0',
    income: '0',
    netCashFlow: '0',
    tradeCashNet: '0',
    transfer: '0',
  };
  return {
    period: { first: '2026-07-01', last: '2026-07-31', month: 7, timeZone: 'UTC', year: 2026 },
    posted: summary,
    forecast: { summary, sources: [] },
    combinedProjection: summary,
    budget: {
      allocation: { overAllocatedBy: '0', status: 'within_allocation', totalPercent: '0' },
      period: {
        currency: 'EUR',
        forecastIncome: '0',
        forecastIncomeStatus: 'available',
        month: '2026-07',
      },
      items: [],
    },
    activity: { items: [], nextCursor: null },
  };
}
