import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { MatProgressSpinner } from '@angular/material/progress-spinner';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatProgressSpinner],
  selector: 'mmm-loading-state',
  template: `
    <section class="state-panel" role="status" aria-live="polite">
      <mat-spinner aria-hidden="true" diameter="32" />
      <span>{{ label() }}</span>
    </section>
  `,
})
export class LoadingStateComponent {
  readonly label = input.required<string>();
}
