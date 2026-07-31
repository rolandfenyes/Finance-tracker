import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { MatButtonHarness } from '@angular/material/button/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { AsyncButtonComponent } from './async-button';
import { EmptyStateComponent } from './empty-state';
import { ErrorStateComponent } from './error-state';
import { InlineAlertComponent } from './inline-alert';
import { LoadingStateComponent } from './loading-state';
import { PageHeaderComponent } from './page-header';
import { SectionComponent } from './section';

@Component({
  imports: [
    AsyncButtonComponent,
    EmptyStateComponent,
    ErrorStateComponent,
    InlineAlertComponent,
    LoadingStateComponent,
    PageHeaderComponent,
    SectionComponent,
  ],
  template: `
    <mmm-page-header
      eyebrow="Synthetic foundation"
      title="Synthetic page"
      description="No personal or financial data."
    />
    <mmm-section title="Synthetic states">
      <mmm-loading-state label="Loading synthetic content" />
      <mmm-empty-state
        eyebrow="Empty"
        title="No synthetic records"
        description="The synthetic fixture is empty."
      />
      <mmm-error-state
        eyebrow="Error"
        title="Synthetic error"
        description="The synthetic fixture failed."
        retryLabel="Retry"
        (retry)="retried.set(true)"
      />
      <mmm-inline-alert tone="warning">Synthetic warning</mmm-inline-alert>
      <mmm-async-button label="Continue" [pending]="pending()" (activated)="activated.set(true)" />
    </mmm-section>
  `,
})
class ComponentHarness {
  readonly pending = signal(false);
  readonly retried = signal(false);
  readonly activated = signal(false);
}

describe('design-system state primitives', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ComponentHarness],
    }).compileComponents();
  });

  it('exposes semantic loading, error, and alert states', () => {
    const fixture = TestBed.createComponent(ComponentHarness);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector('[role="status"]')?.textContent).toContain(
      'Loading synthetic content',
    );
    expect(element.querySelector('[role="alert"]')?.textContent).toContain('Synthetic error');
    expect(element.querySelector('mmm-inline-alert')?.getAttribute('data-tone')).toBe('warning');
    expect(element.querySelector('h1')?.textContent).toContain('Synthetic page');
  });

  it('uses a Material button harness and prevents duplicate pending actions', async () => {
    const fixture = TestBed.createComponent(ComponentHarness);
    fixture.detectChanges();
    const loader = TestbedHarnessEnvironment.loader(fixture);
    const button = await loader.getHarness(MatButtonHarness.with({ text: 'Continue' }));

    await button.click();
    expect(fixture.componentInstance.activated()).toBe(true);

    fixture.componentInstance.pending.set(true);
    fixture.detectChanges();
    expect(await button.isDisabled()).toBe(true);
  });

  it('emits retry from an explicit accessible button', () => {
    const fixture = TestBed.createComponent(ComponentHarness);
    fixture.detectChanges();
    const element = fixture.nativeElement as HTMLElement;
    const retry = element.querySelector('mmm-error-state button') as HTMLButtonElement;

    retry.click();

    expect(fixture.componentInstance.retried()).toBe(true);
  });
});
