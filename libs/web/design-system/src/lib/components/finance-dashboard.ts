import { ChangeDetectionStrategy, Component, input, output, type Provider } from '@angular/core';
import { NgxEchartsDirective, provideEchartsCore } from 'ngx-echarts';
import type { EChartsCoreOption } from 'echarts/core';
import * as echarts from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([BarChart, GridComponent, TooltipComponent, SVGRenderer]);

export type DataViewState = 'loading' | 'empty' | 'error' | 'gated' | 'success';
export type FinancialTone = 'positive' | 'negative' | 'neutral' | 'forecast' | 'projection';
export type ReportView = 'posted' | 'forecast' | 'projection';

export interface CashFlowMetric {
  readonly label: string;
  readonly value: string;
  readonly tone: FinancialTone;
}

export interface FinanceChartPoint {
  readonly label: string;
  readonly exactValue: string;
  readonly displayValue: string;
}

export function provideDashboardCharts(): Provider {
  return provideEchartsCore({ echarts });
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-data-view',
  template: `
    @switch (state()) {
      @case ('loading') {
        <section class="state-panel" aria-live="polite" data-state="loading">
          <span class="loading-indicator" aria-hidden="true"></span>
          <h2>{{ loadingTitle() }}</h2>
          <p>{{ loadingDescription() }}</p>
        </section>
      }
      @case ('empty') {
        <section class="state-panel" data-state="empty">
          <h2>{{ emptyTitle() }}</h2>
          <p>{{ emptyDescription() }}</p>
        </section>
      }
      @case ('error') {
        <section class="state-panel" role="alert" data-state="error">
          <h2>{{ errorTitle() }}</h2>
          <p>{{ errorDescription() }}</p>
          @if (requestId()) {
            <small>{{ requestIdLabel() }}: {{ requestId() }}</small>
          }
          <button class="action-button" type="button" (click)="retry.emit()">
            {{ retryLabel() }}
          </button>
        </section>
      }
      @case ('gated') {
        <section class="state-panel" data-state="gated">
          <h2>{{ gatedTitle() }}</h2>
          <p>{{ gatedDescription() }}</p>
        </section>
      }
      @default {
        <ng-content />
      }
    }
  `,
})
export class DataViewComponent {
  readonly state = input.required<DataViewState>();
  readonly loadingTitle = input.required<string>();
  readonly loadingDescription = input.required<string>();
  readonly emptyTitle = input.required<string>();
  readonly emptyDescription = input.required<string>();
  readonly errorTitle = input.required<string>();
  readonly errorDescription = input.required<string>();
  readonly gatedTitle = input.required<string>();
  readonly gatedDescription = input.required<string>();
  readonly retryLabel = input.required<string>();
  readonly requestIdLabel = input.required<string>();
  readonly requestId = input<string | null>(null);
  readonly retry = output<void>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-metric-card',
  host: { '[attr.data-tone]': 'tone()' },
  template: `
    <article class="metric-card">
      <p>{{ label() }}</p>
      <strong>{{ value() }}</strong>
      @if (description()) {
        <small>{{ description() }}</small>
      }
    </article>
  `,
})
export class MetricCardComponent {
  readonly label = input.required<string>();
  readonly value = input.required<string>();
  readonly description = input<string>('');
  readonly tone = input<FinancialTone>('neutral');
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-cash-flow-summary-card',
  template: `
    <section class="finance-card cash-flow-summary" [attr.aria-label]="title()">
      <div class="finance-card-heading">
        <div>
          <p class="page-eyebrow">{{ eyebrow() }}</p>
          <h2>{{ title() }}</h2>
        </div>
        <span class="data-badge" [attr.data-kind]="kind()">{{ badge() }}</span>
      </div>
      <div class="metric-grid">
        @for (metric of metrics(); track metric.label) {
          <mmm-metric-card [label]="metric.label" [value]="metric.value" [tone]="metric.tone" />
        }
      </div>
      <p class="finance-disclaimer">{{ disclaimer() }}</p>
    </section>
  `,
  imports: [MetricCardComponent],
})
export class CashFlowSummaryCardComponent {
  readonly eyebrow = input.required<string>();
  readonly title = input.required<string>();
  readonly badge = input.required<string>();
  readonly kind = input<ReportView>('posted');
  readonly metrics = input.required<readonly CashFlowMetric[]>();
  readonly disclaimer = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-report-view-selector',
  template: `
    <div class="view-selector" role="group" [attr.aria-label]="label()">
      @for (option of options(); track option.value) {
        <button
          type="button"
          [attr.aria-pressed]="value() === option.value"
          (click)="valueChange.emit(option.value)"
        >
          {{ option.label }}
        </button>
      }
    </div>
  `,
})
export class ReportViewSelectorComponent {
  readonly label = input.required<string>();
  readonly value = input.required<ReportView>();
  readonly options = input.required<readonly { value: ReportView; label: string }[]>();
  readonly valueChange = output<ReportView>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-conversion-status',
  host: { '[attr.data-status]': 'status()' },
  template: `
    <section class="conversion-status" [attr.aria-label]="label()">
      <span class="status-dot" aria-hidden="true"></span>
      <div>
        <strong>{{ label() }}</strong>
        <p>{{ description() }}</p>
        @if (provenance()) {
          <small>{{ provenance() }}</small>
        }
      </div>
    </section>
  `,
})
export class ConversionStatusComponent {
  readonly status = input.required<'available' | 'stale' | 'unavailable'>();
  readonly label = input.required<string>();
  readonly description = input.required<string>();
  readonly provenance = input<string>('');
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-partial-data-banner',
  template: `<div class="partial-data-banner" role="status">{{ message() }}</div>`,
})
export class PartialDataBannerComponent {
  readonly message = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-signed-variance',
  host: { '[attr.data-sign]': 'sign()' },
  template: `
    <span class="signed-variance">
      <small>{{ label() }}</small>
      <strong>{{ value() }}</strong>
    </span>
  `,
})
export class SignedVarianceComponent {
  readonly label = input.required<string>();
  readonly value = input.required<string>();
  readonly sign = input.required<'positive' | 'negative' | 'zero' | 'unavailable'>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-finance-chart',
  imports: [NgxEchartsDirective],
  template: `
    <section class="finance-card finance-chart-card">
      <div class="finance-card-heading">
        <h2>{{ title() }}</h2>
        <span class="sr-only">{{ description() }}</span>
      </div>
      <div
        echarts
        class="finance-chart"
        [initOpts]="chartInitOptions"
        [options]="options()"
        aria-hidden="true"
      ></div>
      <table class="finance-table chart-table">
        <caption>
          {{
            tableCaption()
          }}
        </caption>
        <thead>
          <tr>
            <th scope="col">{{ categoryHeading() }}</th>
            <th scope="col">{{ valueHeading() }}</th>
          </tr>
        </thead>
        <tbody>
          @for (point of points(); track point.label) {
            <tr>
              <th scope="row">{{ point.label }}</th>
              <td>{{ point.displayValue }}</td>
            </tr>
          }
        </tbody>
      </table>
    </section>
  `,
})
export class FinanceChartComponent {
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly tableCaption = input.required<string>();
  readonly categoryHeading = input.required<string>();
  readonly valueHeading = input.required<string>();
  readonly points = input.required<readonly FinanceChartPoint[]>();
  protected readonly chartInitOptions = { renderer: 'svg' as const };

  protected options(): EChartsCoreOption {
    return {
      animation: false,
      grid: { left: 8, right: 8, top: 12, bottom: 8, containLabel: true },
      tooltip: { trigger: 'axis' },
      xAxis: { type: 'category', data: this.points().map((point) => point.label) },
      yAxis: { type: 'value' },
      series: [
        {
          type: 'bar',
          data: this.points().map((point) => renderingCoordinate(point.exactValue)),
          itemStyle: { color: 'var(--chart-series-1)', borderRadius: [6, 6, 0, 0] },
        },
      ],
    };
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-mobile-activity-row',
  host: { '[attr.data-status]': 'status()' },
  template: `
    <article class="mobile-activity-row">
      <div>
        <strong>{{ title() }}</strong>
        <small>{{ date() }} · {{ originalAmount() }}</small>
      </div>
      <div class="activity-amount">
        <strong>{{ reportingAmount() }}</strong>
        <small>{{ statusLabel() }}</small>
      </div>
    </article>
  `,
})
export class MobileActivityRowComponent {
  readonly title = input.required<string>();
  readonly date = input.required<string>();
  readonly originalAmount = input.required<string>();
  readonly reportingAmount = input.required<string>();
  readonly status = input.required<'available' | 'stale' | 'unavailable'>();
  readonly statusLabel = input.required<string>();
}

function renderingCoordinate(value: string): number {
  if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(value)) return 0;
  const coordinate = Number(value);
  return Number.isFinite(coordinate) ? coordinate : 0;
}
