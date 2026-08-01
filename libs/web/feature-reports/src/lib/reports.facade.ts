import { Injectable, inject, signal } from '@angular/core';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import type { ReportYearsResponseDto } from '@mymoneymap/generated-api-client/models/report-years-response-dto';
import type { YearReportResponseDto } from '@mymoneymap/generated-api-client/models/year-report-response-dto';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import { parseApiError } from '@mymoneymap/web-core';
import { firstValueFrom, type Observable } from 'rxjs';

type LoadState<T> = {
  readonly status: 'idle' | 'loading' | 'ready' | 'empty' | 'error';
  readonly data: T | null;
  readonly requestId: string | null;
};
export interface MonthFilters {
  readonly kind?: 'income' | 'expense' | 'transfer' | 'adjustment' | 'trade_cash';
  readonly categoryId?: string;
  readonly currency?: string;
  readonly query?: string;
  readonly minAmount?: string;
  readonly maxAmount?: string;
  readonly cursor?: string;
}

@Injectable()
export class ReportsFacade {
  private readonly api = inject(ReportingService);
  private readonly yearsSignal = signal<LoadState<ReportYearsResponseDto>>({
    status: 'idle',
    data: null,
    requestId: null,
  });
  private readonly yearSignal = signal<LoadState<YearReportResponseDto>>({
    status: 'idle',
    data: null,
    requestId: null,
  });
  private readonly monthSignal = signal<LoadState<MonthReportResponseDto>>({
    status: 'idle',
    data: null,
    requestId: null,
  });
  readonly years = this.yearsSignal.asReadonly();
  readonly year = this.yearSignal.asReadonly();
  readonly month = this.monthSignal.asReadonly();

  async loadYears(): Promise<void> {
    await this.load(
      this.yearsSignal,
      () => this.api.reportingControllerYears(),
      (value) => value.items.length === 0,
    );
  }
  async loadYear(year: number): Promise<void> {
    await this.load(
      this.yearSignal,
      () => this.api.reportingControllerYear({ year }),
      (value) => value.months.length === 0,
    );
  }
  async loadMonth(
    year: number,
    month: number,
    filters: MonthFilters = {},
    append = false,
  ): Promise<void> {
    const previous = this.monthSignal().data;
    this.monthSignal.update((state) => ({ ...state, status: 'loading', requestId: null }));
    try {
      const page = await firstValueFrom(
        this.api.reportingControllerMonth({ year, month, ...filters, limit: 25 }),
      );
      const data =
        append && previous
          ? {
              ...previous,
              activity: {
                items: [...previous.activity.items, ...page.activity.items],
                nextCursor: page.activity.nextCursor,
              },
            }
          : page;
      this.monthSignal.set({
        status: this.isMonthEmpty(data) ? 'empty' : 'ready',
        data,
        requestId: null,
      });
    } catch (error) {
      const parsed = parseApiError(error);
      this.monthSignal.update((state) => ({
        ...state,
        status: 'error',
        requestId: parsed.requestId,
      }));
    }
  }
  private async load<T>(
    target: {
      set(value: LoadState<T>): void;
      update(updater: (value: LoadState<T>) => LoadState<T>): void;
    },
    request: () => Observable<T>,
    empty: (value: T) => boolean,
  ): Promise<void> {
    target.update((state) => ({ ...state, status: 'loading', requestId: null }));
    try {
      const value = await firstValueFrom(request());
      target.set({ status: empty(value) ? 'empty' : 'ready', data: value, requestId: null });
    } catch (error) {
      const parsed = parseApiError(error);
      target.update((state) => ({ ...state, status: 'error', requestId: parsed.requestId }));
    }
  }
  private isMonthEmpty(report: MonthReportResponseDto): boolean {
    return (
      report.activity.items.length === 0 &&
      report.forecast.sources.length === 0 &&
      report.budget.items.length === 0 &&
      report.posted.conversion.includedSourceCount === 0
    );
  }
}
