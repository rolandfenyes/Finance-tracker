import { ChangeDetectionStrategy, Component, input } from '@angular/core';

let nextSectionId = 0;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'content-section' },
  selector: 'mmm-section',
  template: `
    <section [attr.aria-labelledby]="headingId">
      <header class="section-header">
        <h2 [id]="headingId">{{ title() }}</h2>
        @if (description()) {
          <p>{{ description() }}</p>
        }
      </header>
      <ng-content />
    </section>
  `,
})
export class SectionComponent {
  readonly headingId = `mmm-section-${nextSectionId++}`;
  readonly title = input.required<string>();
  readonly description = input<string>('');
}
