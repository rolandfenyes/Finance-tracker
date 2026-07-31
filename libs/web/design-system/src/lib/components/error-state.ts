import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { MatButton } from '@angular/material/button';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButton],
  selector: 'mmm-error-state',
  template: `
    <section class="state-panel" role="alert" data-state="error">
      <p class="state-eyebrow">{{ eyebrow() }}</p>
      <h2>{{ title() }}</h2>
      <p>{{ description() }}</p>
      <button mat-flat-button type="button" (click)="retry.emit()">{{ retryLabel() }}</button>
    </section>
  `,
})
export class ErrorStateComponent {
  readonly eyebrow = input.required<string>();
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly retryLabel = input.required<string>();
  readonly retry = output<void>();
}
