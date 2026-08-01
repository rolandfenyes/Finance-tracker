import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';
import { TranslocoPipe, TranslocoService } from '@jsverse/transloco';
import type { ForecastSourceDto } from '@mymoneymap/generated-api-client/models/forecast-source-dto';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import type { ReportActivityItemDto } from '@mymoneymap/generated-api-client/models/report-activity-item-dto';
import type { ReportConversionSummaryDto } from '@mymoneymap/generated-api-client/models/report-conversion-summary-dto';
import type { ReportSummaryDto } from '@mymoneymap/generated-api-client/models/report-summary-dto';
import {
  CashFlowSummaryCardComponent,
  ConversionStatusComponent,
  DataViewComponent,
  FinanceChartComponent,
  MobileActivityRowComponent,
  PageHeaderComponent,
  PartialDataBannerComponent,
  provideDashboardCharts,
  ReportViewSelectorComponent,
  SignedVarianceComponent,
  type CashFlowMetric,
  type DataViewState,
  type FinanceChartPoint,
  type ReportView,
} from '@mymoneymap/web-design-system';
import {
  calendarDate,
  currencyCode,
  ExactDecimalAdapter,
  formatCalendarDate,
  formatMoney,
  formatPercent,
  resolveLocale,
  SessionStore,
} from '@mymoneymap/web-core';
import type { SupportedLanguage } from '@mymoneymap/web-shared';
import { DashboardFacade } from './dashboard.facade';
import { filterMoreNavigation, MORE_NAVIGATION } from './product-navigation';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CashFlowSummaryCardComponent,
    ConversionStatusComponent,
    DataViewComponent,
    FinanceChartComponent,
    MobileActivityRowComponent,
    PageHeaderComponent,
    PartialDataBannerComponent,
    ReportViewSelectorComponent,
    RouterLink,
    SignedVarianceComponent,
    TranslocoPipe,
  ],
  providers: [DashboardFacade, provideDashboardCharts()],
  selector: 'mmm-dashboard-page',
  template: `
    <mmm-page-header
      [eyebrow]="'dashboard.eyebrow' | transloco"
      [title]="'dashboard.title' | transloco"
      [description]="'dashboard.description' | transloco"
    />

    <mmm-data-view
      [state]="dataViewState()"
      [loadingTitle]="'dashboard.states.loadingTitle' | transloco"
      [loadingDescription]="'dashboard.states.loadingDescription' | transloco"
      [emptyTitle]="'dashboard.states.emptyTitle' | transloco"
      [emptyDescription]="'dashboard.states.emptyDescription' | transloco"
      [errorTitle]="'state.errorTitle' | transloco"
      [errorDescription]="'state.errorDescription' | transloco"
      [gatedTitle]="'dashboard.states.gatedTitle' | transloco"
      [gatedDescription]="'dashboard.states.gatedDescription' | transloco"
      [retryLabel]="'state.retry' | transloco"
      [requestIdLabel]="'dashboard.states.requestId' | transloco"
      [requestId]="facade.state().requestId"
      (retry)="reload()"
    >
      @if (facade.state().report; as report) {
        <div class="dashboard-period">
          <div>
            <span>{{ 'dashboard.period' | transloco }}</span>
            <strong>{{ periodLabel(report) }}</strong>
          </div>
          <div>
            <span>{{ 'dashboard.reportingCurrency' | transloco }}</span>
            <strong>{{ report.posted.currency }}</strong>
          </div>
        </div>

        @if (isPartial(report)) {
          <mmm-partial-data-banner [message]="'dashboard.partialData' | transloco" />
        }

        <mmm-report-view-selector
          [label]="'dashboard.viewSelector.label' | transloco"
          [value]="selectedView()"
          [options]="viewOptions()"
          (valueChange)="selectedView.set($event)"
        />

        <div class="dashboard-grid dashboard-grid-primary">
          <mmm-cash-flow-summary-card
            [eyebrow]="viewEyebrow()"
            [title]="viewTitle()"
            [badge]="viewBadge()"
            [kind]="selectedView()"
            [metrics]="summaryMetrics(selectedSummary(report))"
            [disclaimer]="'dashboard.cashFlowDisclaimer' | transloco"
          />

          <mmm-conversion-status
            [status]="selectedSummary(report).conversion.status"
            [label]="conversionLabel(selectedSummary(report).conversion)"
            [description]="conversionDescription(selectedSummary(report).conversion)"
            [provenance]="conversionProvenance(selectedSummary(report).conversion)"
          />
        </div>

        <mmm-finance-chart
          [title]="'dashboard.chart.title' | transloco"
          [description]="'dashboard.chart.description' | transloco"
          [tableCaption]="'dashboard.chart.caption' | transloco"
          [categoryHeading]="'dashboard.chart.category' | transloco"
          [valueHeading]="'dashboard.chart.value' | transloco"
          [points]="chartPoints(selectedSummary(report))"
        />

        <section class="dashboard-section" aria-labelledby="forecast-heading">
          <div class="section-heading">
            <div>
              <p class="page-eyebrow">{{ 'dashboard.forecast.eyebrow' | transloco }}</p>
              <h2 id="forecast-heading">{{ 'dashboard.forecast.title' | transloco }}</h2>
            </div>
            <span class="data-badge" data-kind="forecast">{{
              'dashboard.badges.forecast' | transloco
            }}</span>
          </div>
          @if (report.forecast.sources.length === 0) {
            <p class="section-empty">{{ 'dashboard.forecast.empty' | transloco }}</p>
          } @else {
            <ul class="forecast-list">
              @for (source of report.forecast.sources; track source.sourceEntryId) {
                <li>
                  <div>
                    <strong>{{ source.label }}</strong>
                    <small
                      >{{ formatDate(source.occurrenceOn) }} · {{ forecastKind(source) }}</small
                    >
                  </div>
                  <div class="activity-amount">
                    <strong>{{ forecastReportingAmount(source) }}</strong>
                    <small>{{ conversionStatusText(source.conversionStatus) }}</small>
                  </div>
                </li>
              }
            </ul>
          }
        </section>

        <section class="dashboard-section" aria-labelledby="budget-heading">
          <div class="section-heading">
            <div>
              <p class="page-eyebrow">{{ 'dashboard.budget.eyebrow' | transloco }}</p>
              <h2 id="budget-heading">{{ 'dashboard.budget.title' | transloco }}</h2>
            </div>
            <span class="allocation-badge" [attr.data-status]="report.budget.allocation.status">
              {{ allocationLabel(report) }}
            </span>
          </div>
          @if (report.budget.items.length === 0) {
            <p class="section-empty">{{ 'dashboard.budget.empty' | transloco }}</p>
          } @else {
            <div class="budget-grid">
              @for (rule of report.budget.items; track rule.id) {
                <article class="budget-card">
                  <div>
                    <h3>{{ rule.label }}</h3>
                    <span>{{ formatExactPercent(rule.percent) }}</span>
                  </div>
                  @if (rule.plan?.status === 'unavailable') {
                    <p>{{ 'dashboard.unavailable' | transloco }}</p>
                  } @else if (rule.plan; as plan) {
                    <dl>
                      <div>
                        <dt>{{ 'dashboard.budget.planned' | transloco }}</dt>
                        <dd>{{ optionalMoney(plan.plannedAmount, plan.currency) }}</dd>
                      </div>
                      <div>
                        <dt>{{ 'dashboard.budget.spent' | transloco }}</dt>
                        <dd>{{ optionalMoney(plan.assignedCategorySpending, plan.currency) }}</dd>
                      </div>
                    </dl>
                    <mmm-signed-variance
                      [label]="'dashboard.budget.variance' | transloco"
                      [value]="optionalMoney(plan.signedVariance, plan.currency)"
                      [sign]="varianceSign(plan.signedVariance)"
                    />
                  }
                </article>
              }
            </div>
          }
        </section>

        <section class="dashboard-section" aria-labelledby="activity-heading">
          <div class="section-heading">
            <div>
              <p class="page-eyebrow">{{ 'dashboard.activity.eyebrow' | transloco }}</p>
              <h2 id="activity-heading">{{ 'dashboard.activity.title' | transloco }}</h2>
            </div>
            <a class="text-link" routerLink="/app/activity" [queryParams]="activityQuery(report)">
              {{ 'dashboard.activity.viewAll' | transloco }}
            </a>
          </div>
          @if (report.activity.items.length === 0) {
            <p class="section-empty">{{ 'dashboard.activity.empty' | transloco }}</p>
          } @else {
            <div class="activity-list">
              @for (item of report.activity.items; track item.sourceEntryId) {
                <mmm-mobile-activity-row
                  [title]="activityKind(item)"
                  [date]="formatDate(item.postedOn)"
                  [originalAmount]="money(item.amount, item.currency)"
                  [reportingAmount]="activityReportingAmount(item)"
                  [status]="item.conversionStatus"
                  [statusLabel]="conversionStatusText(item.conversionStatus)"
                />
              }
            </div>
          }
        </section>
      }
    </mmm-data-view>
  `,
})
export class DashboardPageComponent implements OnInit {
  protected readonly facade = inject(DashboardFacade);
  private readonly decimals = inject(ExactDecimalAdapter);
  private readonly transloco = inject(TranslocoService);
  private readonly activeLanguage = toSignal(this.transloco.langChanges$, {
    initialValue: this.transloco.getActiveLang(),
  });

  protected readonly selectedView = signal<ReportView>('posted');
  protected readonly viewOptions = computed(() => [
    { value: 'posted' as const, label: this.text('dashboard.views.posted') },
    { value: 'forecast' as const, label: this.text('dashboard.views.forecast') },
    { value: 'projection' as const, label: this.text('dashboard.views.projection') },
  ]);
  protected readonly dataViewState = computed<DataViewState>(() => {
    const status = this.facade.state().status;
    return status === 'ready' ? 'success' : status;
  });

  ngOnInit(): void {
    void this.facade.load();
  }

  protected reload(): void {
    void this.facade.load();
  }

  protected selectedSummary(report: MonthReportResponseDto): ReportSummaryDto {
    if (this.selectedView() === 'forecast') return report.forecast.summary;
    if (this.selectedView() === 'projection') return report.combinedProjection;
    return report.posted;
  }

  protected summaryMetrics(summary: ReportSummaryDto): readonly CashFlowMetric[] {
    return [
      {
        label: this.text('dashboard.metrics.income'),
        value: this.money(summary.income, summary.currency),
        tone: 'positive',
      },
      {
        label: this.text('dashboard.metrics.expense'),
        value: this.money(summary.expense, summary.currency),
        tone: 'negative',
      },
      {
        label: this.text('dashboard.metrics.netCashFlow'),
        value: this.money(summary.netCashFlow, summary.currency),
        tone: 'neutral',
      },
      {
        label: this.text('dashboard.metrics.transfer'),
        value: this.money(summary.transfer, summary.currency),
        tone: 'neutral',
      },
      {
        label: this.text('dashboard.metrics.adjustment'),
        value: this.money(summary.adjustmentNet, summary.currency),
        tone: 'neutral',
      },
      {
        label: this.text('dashboard.metrics.tradeCash'),
        value: this.money(summary.tradeCashNet, summary.currency),
        tone: 'neutral',
      },
    ];
  }

  protected chartPoints(summary: ReportSummaryDto): readonly FinanceChartPoint[] {
    return [
      {
        label: this.text('dashboard.metrics.income'),
        exactValue: summary.income,
        displayValue: this.money(summary.income, summary.currency),
      },
      {
        label: this.text('dashboard.metrics.expense'),
        exactValue: summary.expense,
        displayValue: this.money(summary.expense, summary.currency),
      },
      {
        label: this.text('dashboard.metrics.netCashFlow'),
        exactValue: summary.netCashFlow,
        displayValue: this.money(summary.netCashFlow, summary.currency),
      },
    ];
  }

  protected money(value: string, currency: string): string {
    return formatMoney(this.decimals.parse(value), currencyCode(currency), this.locale());
  }

  protected optionalMoney(value: string | undefined, currency: string): string {
    return value === undefined ? this.text('dashboard.unavailable') : this.money(value, currency);
  }

  protected formatExactPercent(value: string): string {
    return formatPercent(this.decimals.parse(value), this.locale());
  }

  protected formatDate(value: string): string {
    return formatCalendarDate(calendarDate(value), this.locale());
  }

  protected periodLabel(report: MonthReportResponseDto): string {
    return `${this.formatDate(report.period.first)} – ${this.formatDate(report.period.last)}`;
  }

  protected isPartial(report: MonthReportResponseDto): boolean {
    return [report.posted, report.forecast.summary, report.combinedProjection].some(
      (summary) => !summary.conversion.complete,
    );
  }

  protected conversionLabel(conversion: ReportConversionSummaryDto): string {
    return this.text(`dashboard.conversion.${conversion.status}.label`);
  }

  protected conversionDescription(conversion: ReportConversionSummaryDto): string {
    return this.text(`dashboard.conversion.${conversion.status}.description`, {
      included: conversion.includedSourceCount,
      unavailable: conversion.unavailableSourceCount,
      stale: conversion.staleSourceCount,
    });
  }

  protected conversionProvenance(conversion: ReportConversionSummaryDto): string {
    if (conversion.providers.length === 0) return '';
    return this.text('dashboard.conversion.provenance', {
      providers: conversion.providers.join(', '),
      date: conversion.oldestRateAt ?? this.text('dashboard.unavailable'),
    });
  }

  protected conversionStatusText(status: 'available' | 'stale' | 'unavailable'): string {
    return this.text(`dashboard.conversion.${status}.short`);
  }

  protected forecastReportingAmount(source: ForecastSourceDto): string {
    return source.convertedAmount === undefined
      ? this.text('dashboard.unavailable')
      : this.money(source.convertedAmount, source.reportingCurrency);
  }

  protected activityReportingAmount(item: ReportActivityItemDto): string {
    return item.convertedAmount === undefined
      ? this.text('dashboard.unavailable')
      : this.money(item.convertedAmount, item.reportingCurrency);
  }

  protected forecastKind(source: ForecastSourceDto): string {
    return this.text(`dashboard.kinds.${source.kind}`);
  }

  protected activityKind(item: ReportActivityItemDto): string {
    return this.text(`dashboard.kinds.${item.kind}`);
  }

  protected varianceSign(
    value: string | undefined,
  ): 'positive' | 'negative' | 'zero' | 'unavailable' {
    if (value === undefined) return 'unavailable';
    const exact = this.decimals.parse(value);
    const comparison = this.decimals.compare(exact, this.decimals.parse('0'));
    return comparison > 0 ? 'positive' : comparison < 0 ? 'negative' : 'zero';
  }

  protected allocationLabel(report: MonthReportResponseDto): string {
    const allocation = report.budget.allocation;
    if (allocation.status === 'over_allocated') {
      return this.text('dashboard.budget.overAllocated', {
        amount: this.formatExactPercent(allocation.overAllocatedBy),
      });
    }
    return this.text('dashboard.budget.allocated', {
      amount: this.formatExactPercent(allocation.totalPercent),
    });
  }

  protected activityQuery(report: MonthReportResponseDto): Readonly<Record<string, string>> | null {
    return report.activity.nextCursor ? { cursor: report.activity.nextCursor } : null;
  }

  protected viewEyebrow(): string {
    return this.text(`dashboard.views.${this.selectedView()}Eyebrow`);
  }

  protected viewTitle(): string {
    return this.text(`dashboard.views.${this.selectedView()}Title`);
  }

  protected viewBadge(): string {
    return this.text(`dashboard.badges.${this.selectedView()}`);
  }

  private locale(): SupportedLanguage {
    return resolveLocale(this.activeLanguage());
  }

  private text(key: string, params?: Readonly<Record<string, unknown>>): string {
    return this.transloco.translate(key, params, this.activeLanguage());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatIconModule, PageHeaderComponent, RouterLink, TranslocoPipe],
  selector: 'mmm-more-hub-page',
  template: `
    <mmm-page-header
      [eyebrow]="'more.eyebrow' | transloco"
      [title]="'more.title' | transloco"
      [description]="'more.description' | transloco"
    />
    <nav class="more-grid" [attr.aria-label]="'more.navigationLabel' | transloco">
      @for (item of navigation(); track item.id) {
        <a [routerLink]="item.route">
          <mat-icon aria-hidden="true" [svgIcon]="item.icon" />
          <span>{{ item.labelKey | transloco }}</span>
          <mat-icon aria-hidden="true" svgIcon="chevron-right" />
        </a>
      }
    </nav>
  `,
})
export class MoreHubPageComponent {
  private readonly session = inject(SessionStore);
  protected readonly navigation = computed(() =>
    filterMoreNavigation(MORE_NAVIGATION, this.session.currentUser()?.entitlements),
  );
}
