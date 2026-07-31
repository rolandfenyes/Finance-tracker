import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-empty-state',
  template: `
    <section class="state-panel" data-state="empty">
      <p class="state-eyebrow">{{ eyebrow() }}</p>
      <h2>{{ title() }}</h2>
      <p>{{ description() }}</p>
      <ng-content />
    </section>
  `,
})
export class EmptyStateComponent {
  readonly eyebrow = input.required<string>();
  readonly title = input.required<string>();
  readonly description = input.required<string>();
}
