import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-category-chip',
  template: `<span class="category-chip"
    ><span class="category-swatch" [style.background]="color()" aria-hidden="true"></span
    >{{ label() }} <small>({{ kind() }})</small></span
  >`,
  styles: [
    `
      .category-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid var(--border-subtle);
        border-radius: var(--radius-full);
        padding: 0.35rem 0.7rem;
      }
      .category-swatch {
        width: 0.8rem;
        height: 0.8rem;
        border-radius: 50%;
        border: 1px solid var(--border-strong);
      }
      small {
        color: var(--text-secondary);
      }
    `,
  ],
})
export class CategoryChipComponent {
  readonly label = input.required<string>();
  readonly color = input.required<string>();
  readonly kind = input.required<string>();
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-entitlement-limit-banner',
  template: `<aside class="entitlement-banner" [attr.data-reached]="reached()">
    <strong>{{ title() }}</strong>
    <p>{{ description() }}</p>
  </aside>`,
  styles: [
    `
      .entitlement-banner {
        border: 1px solid var(--status-info-border);
        background: var(--status-info-container);
        border-radius: var(--radius-md);
        padding: 1rem;
      }
      .entitlement-banner[data-reached='true'] {
        border-color: var(--status-warning-border);
        background: var(--status-warning-container);
      }
      p {
        margin: 0.25rem 0 0;
      }
    `,
  ],
})
export class EntitlementLimitBannerComponent {
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly reached = input(false);
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-forecast-occurrences',
  template: `
    <section class="forecast-list" [attr.aria-label]="label()">
      <p>
        <strong>{{ forecastLabel() }}</strong> — {{ disclaimer() }}
      </p>
      @if (truncated()) {
        <p class="status-warning">{{ truncatedLabel() }}</p>
      }
      <ul>
        @for (occurrence of occurrences(); track occurrence) {
          <li>{{ occurrence }}</li>
        }
      </ul>
    </section>
  `,
})
export class ForecastOccurrencesComponent {
  readonly label = input.required<string>();
  readonly forecastLabel = input.required<string>();
  readonly disclaimer = input.required<string>();
  readonly truncatedLabel = input.required<string>();
  readonly truncated = input(false);
  readonly occurrences = input.required<readonly string[]>();
}
