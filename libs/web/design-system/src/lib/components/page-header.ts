import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-page-header',
  template: `
    <header class="page-header">
      <div>
        @if (eyebrow()) {
          <p class="page-eyebrow">{{ eyebrow() }}</p>
        }
        <h1>{{ title() }}</h1>
        @if (description()) {
          <p class="page-description">{{ description() }}</p>
        }
      </div>
      <div class="page-actions"><ng-content /></div>
    </header>
  `,
})
export class PageHeaderComponent {
  readonly eyebrow = input<string>('');
  readonly title = input.required<string>();
  readonly description = input<string>('');
}
