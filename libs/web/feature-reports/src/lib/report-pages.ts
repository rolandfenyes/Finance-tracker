import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject } from '@angular/core';
import type { OnInit } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslocoPipe, TranslocoService } from '@jsverse/transloco';
import type { ReportActivityItemDto } from '@mymoneymap/generated-api-client/models/report-activity-item-dto';
import type { ReportSummaryDto } from '@mymoneymap/generated-api-client/models/report-summary-dto';
import type { YearMonthReportDto } from '@mymoneymap/generated-api-client/models/year-month-report-dto';
import {
  CashFlowSummaryCardComponent,
  CursorPagerComponent,
  DataViewComponent,
  type DataViewState,
  type CashFlowMetric,
  FinanceChartComponent,
  type FinanceChartPoint,
  MobileActivityRowComponent,
  PageHeaderComponent,
  PartialDataBannerComponent,
  PeriodNavigatorComponent,
  ReportTableComponent,
  provideDashboardCharts,
} from '@mymoneymap/web-design-system';
import {
  calendarDate,
  currencyCode,
  ExactDecimalAdapter,
  formatCalendarDate,
  formatMoney,
  resolveLocale,
} from '@mymoneymap/web-core';
import { ReportsFacade, type MonthFilters } from './reports.facade';
import type { SupportedLanguage } from '@mymoneymap/web-shared';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DataViewComponent, PageHeaderComponent, RouterLink, TranslocoPipe],
  selector: 'mmm-report-years-page',
  template: `<mmm-page-header
      [eyebrow]="'reports.eyebrow' | transloco"
      [title]="'reports.title' | transloco"
      [description]="'reports.description' | transloco"
    /><mmm-data-view
      [state]="state()"
      [loadingTitle]="'reports.states.loading' | transloco"
      [loadingDescription]="'reports.states.loadingDescription' | transloco"
      [emptyTitle]="'reports.states.empty' | transloco"
      [emptyDescription]="'reports.states.emptyDescription' | transloco"
      [errorTitle]="'state.errorTitle' | transloco"
      [errorDescription]="'state.errorDescription' | transloco"
      [gatedTitle]="'state.disabled' | transloco"
      [gatedDescription]="'state.disabled' | transloco"
      [retryLabel]="'state.retry' | transloco"
      [requestIdLabel]="'dashboard.states.requestId' | transloco"
      [requestId]="facade.years().requestId"
      (retry)="reload()"
      ><div class="year-grid">
        @for (item of facade.years().data?.items ?? []; track item.year) {
          <a class="finance-card year-card" [routerLink]="[item.year]"
            ><span>{{ 'reports.year' | transloco }}</span
            ><strong>{{ item.year }}</strong></a
          >
        }
      </div></mmm-data-view
    >`,
  styles: [
    `
      .year-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
        gap: 1rem;
      }
      .year-card {
        display: grid;
        gap: 0.25rem;
        padding: 1.25rem;
        color: inherit;
        text-decoration: none;
      }
      .year-card span {
        color: var(--text-secondary);
      }
      .year-card strong {
        font-size: 2rem;
      }
    `,
  ],
})
export class ReportYearsPageComponent implements OnInit {
  protected readonly facade = inject(ReportsFacade);
  protected readonly state = computed<DataViewState>(() =>
    toDataViewState(this.facade.years().status),
  );
  ngOnInit(): void {
    void this.facade.loadYears();
  }
  protected reload(): void {
    void this.facade.loadYears();
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CashFlowSummaryCardComponent,
    DataViewComponent,
    FinanceChartComponent,
    PageHeaderComponent,
    ReportTableComponent,
    RouterLink,
    TranslocoPipe,
  ],
  providers: [provideDashboardCharts()],
  selector: 'mmm-year-report-page',
  template: `<mmm-page-header
      [eyebrow]="'reports.annual.eyebrow' | transloco"
      [title]="year + ''"
      [description]="'reports.annual.description' | transloco"
      ><a class="text-link" routerLink="/app/reports">{{
        'reports.back' | transloco
      }}</a></mmm-page-header
    ><mmm-data-view
      [state]="state()"
      [loadingTitle]="'reports.states.loading' | transloco"
      [loadingDescription]="'reports.states.loadingDescription' | transloco"
      [emptyTitle]="'reports.states.empty' | transloco"
      [emptyDescription]="'reports.states.emptyDescription' | transloco"
      [errorTitle]="'state.errorTitle' | transloco"
      [errorDescription]="'state.errorDescription' | transloco"
      [gatedTitle]="'state.disabled' | transloco"
      [gatedDescription]="'state.disabled' | transloco"
      [retryLabel]="'state.retry' | transloco"
      [requestIdLabel]="'dashboard.states.requestId' | transloco"
      [requestId]="facade.year().requestId"
      (retry)="reload()"
    >
      @if (facade.year().data; as report) {
        <mmm-cash-flow-summary-card
          [eyebrow]="'dashboard.badges.posted' | transloco"
          [title]="'reports.annual.totals' | transloco"
          badge=""
          [metrics]="metrics(report.posted)"
          [disclaimer]="'dashboard.cashFlowDisclaimer' | transloco"
        />
        <mmm-finance-chart
          [title]="'reports.annual.chart' | transloco"
          [description]="'reports.annual.description' | transloco"
          [tableCaption]="'reports.annual.table' | transloco"
          [categoryHeading]="'reports.month.eyebrow' | transloco"
          [valueHeading]="'dashboard.metrics.netCashFlow' | transloco"
          [points]="chartPoints(report.months)"
        />
        <mmm-report-table
          [caption]="'reports.annual.table' | transloco"
          [periodHeading]="'reports.month.eyebrow' | transloco"
          [valueHeading]="'dashboard.metrics.netCashFlow' | transloco"
          [rows]="tableRows(report.months)"
        />
        <div class="month-grid">
          @for (item of report.months; track item.period.month) {
            <a class="finance-card month-card" [routerLink]="[item.period.month]"
              ><strong>{{ monthLabel(item.period.month!) }}</strong
              ><span>{{ money(item.posted.netCashFlow, item.posted.currency) }}</span></a
            >
          }
        </div>
      }
    </mmm-data-view>`,
  styles: [
    `
      .month-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .month-card {
        display: grid;
        gap: 0.5rem;
        padding: 1rem;
        color: inherit;
        text-decoration: none;
      }
    `,
  ],
})
export class YearReportPageComponent implements OnInit {
  protected readonly facade = inject(ReportsFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly decimals = inject(ExactDecimalAdapter);
  private readonly transloco = inject(TranslocoService);
  protected readonly year = Number(this.route.snapshot.paramMap.get('year'));
  protected readonly state = computed<DataViewState>(() =>
    toDataViewState(this.facade.year().status),
  );
  ngOnInit(): void {
    void this.facade.loadYear(this.year);
  }
  protected reload(): void {
    void this.facade.loadYear(this.year);
  }
  protected metrics(summary: ReportSummaryDto): readonly CashFlowMetric[] {
    return summaryMetrics(summary, this.decimals, this.locale(), this.transloco);
  }
  protected money(value: string, currency: string): string {
    return formatMoney(this.decimals.parse(value), currencyCode(currency), this.locale());
  }
  protected monthLabel(month: number): string {
    return new Intl.DateTimeFormat(this.locale(), { month: 'long' }).format(
      new Date(Date.UTC(2020, month - 1, 1)),
    );
  }
  protected chartPoints(months: YearMonthReportDto[]): readonly FinanceChartPoint[] {
    return months.map((item) => ({
      label: this.monthLabel(item.period.month!),
      exactValue: item.posted.netCashFlow,
      displayValue: this.money(item.posted.netCashFlow, item.posted.currency),
    }));
  }
  protected tableRows(months: YearMonthReportDto[]): readonly { period: string; value: string }[] {
    return this.chartPoints(months).map((item) => ({
      period: item.label,
      value: item.displayValue,
    }));
  }
  private locale(): SupportedLanguage {
    return resolveLocale(this.transloco.getActiveLang());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CashFlowSummaryCardComponent,
    CursorPagerComponent,
    DataViewComponent,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MobileActivityRowComponent,
    PageHeaderComponent,
    PartialDataBannerComponent,
    PeriodNavigatorComponent,
    ReactiveFormsModule,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-month-report-page',
  template: `<mmm-page-header
      [eyebrow]="'reports.month.eyebrow' | transloco"
      [title]="periodTitle()"
      [description]="'reports.month.description' | transloco"
      ><a class="text-link" [routerLink]="['/app/reports', year]">{{
        'reports.back' | transloco
      }}</a></mmm-page-header
    >
    <mmm-period-navigator
      [label]="'reports.month.navigator' | transloco"
      [previousLabel]="'reports.month.previous' | transloco"
      [nextLabel]="'reports.month.next' | transloco"
      [period]="periodTitle()"
      (previous)="moveMonth(-1)"
      (next)="moveMonth(1)"
    />
    <details class="report-filter-sheet" open>
      <summary>{{ 'reports.month.filters' | transloco }}</summary>
      <form class="filter-panel" [formGroup]="filters" (ngSubmit)="applyFilters()">
        <mat-form-field
          ><mat-label>{{ 'reports.filters.kind' | transloco }}</mat-label
          ><mat-select formControlName="kind"
            ><mat-option value="">{{ 'reports.filters.all' | transloco }}</mat-option>
            @for (kind of kinds; track kind) {
              <mat-option [value]="kind">{{ kindLabel(kind) }}</mat-option>
            }
          </mat-select></mat-form-field
        ><mat-form-field
          ><mat-label>{{ 'reports.filters.query' | transloco }}</mat-label
          ><input matInput formControlName="query" /></mat-form-field
        ><mat-form-field
          ><mat-label>{{ 'reports.filters.currency' | transloco }}</mat-label
          ><input matInput maxlength="3" formControlName="currency" /></mat-form-field
        ><mat-form-field
          ><mat-label>{{ 'reports.filters.category' | transloco }}</mat-label
          ><input matInput formControlName="categoryId" /></mat-form-field
        ><mat-form-field
          ><mat-label>{{ 'reports.filters.minimum' | transloco }}</mat-label
          ><input matInput inputmode="decimal" formControlName="minAmount" /></mat-form-field
        ><mat-form-field
          ><mat-label>{{ 'reports.filters.maximum' | transloco }}</mat-label
          ><input matInput inputmode="decimal" formControlName="maxAmount" /></mat-form-field
        ><button class="secondary-button" type="submit">
          {{ 'reports.filters.apply' | transloco }}
        </button>
      </form>
    </details>
    <mmm-data-view
      [state]="state()"
      [loadingTitle]="'reports.states.loading' | transloco"
      [loadingDescription]="'reports.states.loadingDescription' | transloco"
      [emptyTitle]="'reports.states.empty' | transloco"
      [emptyDescription]="'reports.states.emptyDescription' | transloco"
      [errorTitle]="'state.errorTitle' | transloco"
      [errorDescription]="'state.errorDescription' | transloco"
      [gatedTitle]="'state.disabled' | transloco"
      [gatedDescription]="'state.disabled' | transloco"
      [retryLabel]="'state.retry' | transloco"
      [requestIdLabel]="'dashboard.states.requestId' | transloco"
      [requestId]="facade.month().requestId"
      (retry)="reload()"
    >
      @if (facade.month().data; as report) {
        @if (!report.posted.conversion.complete) {
          <mmm-partial-data-banner [message]="'dashboard.partialData' | transloco" />
        }
        <div class="report-summary-grid">
          <mmm-cash-flow-summary-card
            [eyebrow]="'dashboard.badges.posted' | transloco"
            [title]="'dashboard.views.postedTitle' | transloco"
            [badge]="'dashboard.badges.posted' | transloco"
            [metrics]="metrics(report.posted)"
            [disclaimer]="'dashboard.cashFlowDisclaimer' | transloco"
          /><mmm-cash-flow-summary-card
            [eyebrow]="'dashboard.badges.forecast' | transloco"
            [title]="'dashboard.views.forecastTitle' | transloco"
            [badge]="'dashboard.badges.forecast' | transloco"
            kind="forecast"
            [metrics]="metrics(report.forecast.summary)"
            [disclaimer]="'dashboard.cashFlowDisclaimer' | transloco"
          />
        </div>
        <section class="activity-section">
          <h2>{{ 'reports.month.activity' | transloco }}</h2>
          @for (item of report.activity.items; track item.sourceEntryId) {
            <mmm-mobile-activity-row
              [title]="kindLabel(item.kind)"
              [date]="date(item.postedOn)"
              [originalAmount]="money(item.amount, item.currency)"
              [reportingAmount]="reportingAmount(item)"
              [status]="item.conversionStatus"
              [statusLabel]="conversionLabel(item.conversionStatus)"
            />
          }
          <mmm-cursor-pager
            [nextCursor]="report.activity.nextCursor"
            [loading]="facade.month().status === 'loading'"
            [loadMoreLabel]="'journal.loadMore' | transloco"
            [loadingLabel]="'state.loading' | transloco"
            (loadMore)="loadMore()"
          />
        </section>
      }
    </mmm-data-view>`,
  styles: [
    `
      .filter-panel {
        display: grid;
        gap: 1rem;
        padding: 1rem;
        margin: 1rem 0;
        background: var(--surface-raised);
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-lg);
      }
      .report-summary-grid {
        display: grid;
        gap: 1rem;
      }
      .activity-section {
        margin-top: 1rem;
      }
      .activity-section mmm-mobile-activity-row {
        display: block;
        margin-bottom: 0.5rem;
      }
      @media (min-width: 48rem) {
        .filter-panel {
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }
      }
      @media (min-width: 64rem) {
        .report-summary-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }
    `,
  ],
})
export class MonthReportPageComponent implements OnInit {
  protected readonly facade = inject(ReportsFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder).nonNullable;
  private readonly decimals = inject(ExactDecimalAdapter);
  private readonly transloco = inject(TranslocoService);
  protected readonly year = Number(this.route.snapshot.paramMap.get('year'));
  protected readonly month = Number(this.route.snapshot.paramMap.get('month'));
  protected readonly kinds = ['income', 'expense', 'transfer', 'adjustment', 'trade_cash'] as const;
  protected readonly filters = this.fb.group({
    kind: this.fb.control<'' | MonthFilters['kind']>(''),
    query: '',
    currency: ['', Validators.pattern(/^[A-Za-z]{3}$|^$/)],
    categoryId: '',
    minAmount: '',
    maxAmount: '',
  });
  protected readonly state = computed<DataViewState>(() =>
    toDataViewState(this.facade.month().status),
  );
  ngOnInit(): void {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const values = {
        kind: (params.get('kind') ?? '') as '' | MonthFilters['kind'],
        query: params.get('query') ?? '',
        currency: params.get('currency') ?? '',
        categoryId: params.get('categoryId') ?? '',
        minAmount: params.get('minAmount') ?? '',
        maxAmount: params.get('maxAmount') ?? '',
      };
      this.filters.patchValue(values);
      void this.facade.loadMonth(this.year, this.month, {
        ...this.clean(values),
        cursor: params.get('cursor') ?? undefined,
      });
    });
  }
  protected applyFilters(): void {
    const query = this.clean(this.filters.getRawValue());
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { ...query, cursor: null },
    });
  }
  protected reload(): void {
    void this.facade.loadMonth(this.year, this.month, this.currentFilters());
  }
  protected loadMore(): void {
    const cursor = this.facade.month().data?.activity.nextCursor;
    if (cursor)
      void this.facade.loadMonth(this.year, this.month, { ...this.currentFilters(), cursor }, true);
  }
  protected moveMonth(offset: number): void {
    const date = new Date(Date.UTC(this.year, this.month - 1 + offset, 1));
    void this.router.navigate(['/app/reports', date.getUTCFullYear(), date.getUTCMonth() + 1]);
  }
  protected metrics(summary: ReportSummaryDto): readonly CashFlowMetric[] {
    return summaryMetrics(summary, this.decimals, this.locale(), this.transloco);
  }
  protected kindLabel(kind: string): string {
    return this.transloco.translate(`dashboard.kinds.${kind}`);
  }
  protected periodTitle(): string {
    return new Intl.DateTimeFormat(this.locale(), { year: 'numeric', month: 'long' }).format(
      new Date(Date.UTC(this.year, this.month - 1, 1)),
    );
  }
  protected money(value: string, currency: string): string {
    return formatMoney(this.decimals.parse(value), currencyCode(currency), this.locale());
  }
  protected date(value: string): string {
    return formatCalendarDate(calendarDate(value), this.locale());
  }
  protected reportingAmount(item: ReportActivityItemDto): string {
    return item.convertedAmount === undefined
      ? this.transloco.translate('dashboard.unavailable')
      : this.money(item.convertedAmount, item.reportingCurrency);
  }
  protected conversionLabel(status: string): string {
    return this.transloco.translate(`dashboard.conversion.${status}.short`);
  }
  private currentFilters(): MonthFilters {
    return this.clean(this.filters.getRawValue());
  }
  private clean(value: Record<string, string | undefined>): MonthFilters {
    return Object.fromEntries(Object.entries(value).filter(([, item]) => item));
  }
  private locale(): SupportedLanguage {
    return resolveLocale(this.transloco.getActiveLang());
  }
}

function summaryMetrics(
  summary: ReportSummaryDto,
  decimals: ExactDecimalAdapter,
  locale: SupportedLanguage,
  transloco: TranslocoService,
): readonly CashFlowMetric[] {
  const money = (value: string): string =>
    formatMoney(decimals.parse(value), currencyCode(summary.currency), locale);
  return [
    {
      label: transloco.translate('dashboard.metrics.income'),
      value: money(summary.income),
      tone: 'positive',
    },
    {
      label: transloco.translate('dashboard.metrics.expense'),
      value: money(summary.expense),
      tone: 'negative',
    },
    {
      label: transloco.translate('dashboard.metrics.netCashFlow'),
      value: money(summary.netCashFlow),
      tone: 'neutral',
    },
    {
      label: transloco.translate('dashboard.metrics.transfer'),
      value: money(summary.transfer),
      tone: 'neutral',
    },
  ];
}

function toDataViewState(status: ReportStateName): DataViewState {
  if (status === 'idle') return 'loading';
  if (status === 'ready') return 'success';
  return status;
}

type ReportStateName = 'idle' | 'loading' | 'ready' | 'empty' | 'error';
