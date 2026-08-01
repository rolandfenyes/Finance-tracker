import { HttpContext } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import { ReportingService } from '@mymoneymap/generated-api-client/services/reporting.service';
import { API_ROUTE_TEMPLATE, parseApiError } from '@mymoneymap/web-core';
import { firstValueFrom } from 'rxjs';

export type DashboardState =
  | { readonly status: 'loading'; readonly report: null; readonly requestId: null }
  | { readonly status: 'ready'; readonly report: MonthReportResponseDto; readonly requestId: null }
  | { readonly status: 'empty'; readonly report: MonthReportResponseDto; readonly requestId: null }
  | { readonly status: 'gated'; readonly report: null; readonly requestId: string | null }
  | { readonly status: 'error'; readonly report: null; readonly requestId: string | null };

@Injectable()
export class DashboardFacade {
  private readonly reporting = inject(ReportingService);
  private readonly stateSignal = signal<DashboardState>({
    status: 'loading',
    report: null,
    requestId: null,
  });

  readonly state = this.stateSignal.asReadonly();

  async load(): Promise<void> {
    this.stateSignal.set({ status: 'loading', report: null, requestId: null });
    const context = new HttpContext().set(API_ROUTE_TEMPLATE, '/api/v1/reports/months/current');
    try {
      const report = await firstValueFrom(
        this.reporting.reportingControllerCurrent({ limit: 5 }, context),
      );
      this.stateSignal.set({
        status: reportIsEmpty(report) ? 'empty' : 'ready',
        report,
        requestId: null,
      });
    } catch (error: unknown) {
      const parsed = parseApiError(error);
      this.stateSignal.set({
        status: parsed.status === 403 ? 'gated' : 'error',
        report: null,
        requestId: parsed.requestId,
      });
    }
  }
}

function reportIsEmpty(report: MonthReportResponseDto): boolean {
  return (
    report.activity.items.length === 0 &&
    report.forecast.sources.length === 0 &&
    report.budget.items.length === 0 &&
    report.posted.conversion.includedSourceCount === 0
  );
}
