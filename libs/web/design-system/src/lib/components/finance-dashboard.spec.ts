import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import {
  DataViewComponent,
  MetricCardComponent,
  PartialDataBannerComponent,
  ReportViewSelectorComponent,
  SignedVarianceComponent,
  type ReportView,
} from './finance-dashboard';

@Component({
  imports: [
    DataViewComponent,
    MetricCardComponent,
    PartialDataBannerComponent,
    ReportViewSelectorComponent,
    SignedVarianceComponent,
  ],
  template: `
    <mmm-data-view
      [state]="state()"
      loadingTitle="Loading report"
      loadingDescription="Waiting for the server"
      emptyTitle="No report"
      emptyDescription="Nothing is available"
      errorTitle="Report failed"
      errorDescription="Try safely"
      gatedTitle="Access gated"
      gatedDescription="Entitlement required"
      retryLabel="Retry"
      requestIdLabel="Request reference"
      requestId="synthetic-safe-reference"
      (retry)="retried.set(true)"
    >
      <mmm-metric-card label="Exact value" value="123456789.123456789 EUR" tone="positive" />
    </mmm-data-view>
    <mmm-report-view-selector
      label="Data view"
      [value]="view()"
      [options]="options"
      (valueChange)="view.set($event)"
    />
    <mmm-partial-data-banner message="A source is excluded, never zero." />
    <mmm-signed-variance label="Variance" value="-0.00000001 EUR" sign="negative" />
  `,
})
class FinanceHarness {
  readonly state = signal<'success' | 'loading' | 'empty' | 'error' | 'gated'>('success');
  readonly retried = signal(false);
  readonly view = signal<ReportView>('posted');
  readonly options = [
    { value: 'posted' as const, label: 'Posted' },
    { value: 'forecast' as const, label: 'Forecast' },
    { value: 'projection' as const, label: 'Projection' },
  ];
}

describe('finance dashboard primitives', () => {
  it('preserves exact display strings and exposes partial and signed semantics', async () => {
    await TestBed.configureTestingModule({ imports: [FinanceHarness] }).compileComponents();
    const fixture = TestBed.createComponent(FinanceHarness);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('123456789.123456789 EUR');
    expect(element.querySelector('mmm-signed-variance')?.getAttribute('data-sign')).toBe(
      'negative',
    );
    expect(element.querySelector('[role="status"]')?.textContent).toContain('never zero');

    const forecast = [...element.querySelectorAll('.view-selector button')].find((button) =>
      button.textContent?.includes('Forecast'),
    ) as HTMLButtonElement;
    forecast.click();
    expect(fixture.componentInstance.view()).toBe('forecast');
  });

  it('announces errors and emits retry without financial payload details', async () => {
    await TestBed.configureTestingModule({ imports: [FinanceHarness] }).compileComponents();
    const fixture = TestBed.createComponent(FinanceHarness);
    fixture.componentInstance.state.set('error');
    fixture.detectChanges();
    const alert = (fixture.nativeElement as HTMLElement).querySelector('[role="alert"]');

    expect(alert?.textContent).toContain('synthetic-safe-reference');
    (alert?.querySelector('button') as HTMLButtonElement).click();
    expect(fixture.componentInstance.retried()).toBe(true);
  });

  it.each([
    ['loading', 'Loading report'],
    ['empty', 'No report'],
    ['gated', 'Access gated'],
  ] as const)('renders the %s composition state', async (state, expected) => {
    await TestBed.configureTestingModule({ imports: [FinanceHarness] }).compileComponents();
    const fixture = TestBed.createComponent(FinanceHarness);
    fixture.componentInstance.state.set(state);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain(expected);
  });
});
