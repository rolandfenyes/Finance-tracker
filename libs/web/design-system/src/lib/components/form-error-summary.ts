import { ChangeDetectionStrategy, Component, input } from '@angular/core';

export interface FormErrorItem {
  readonly field: string;
  readonly message: string;
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  selector: 'mmm-form-error-summary',
  template: `
    @if (items().length > 0) {
      <section class="form-error-summary" role="alert" tabindex="-1">
        <p>{{ title() }}</p>
        <ul>
          @for (item of items(); track item.field) {
            <li>
              <a [attr.href]="'#' + item.field">{{ item.message }}</a>
            </li>
          }
        </ul>
      </section>
    }
  `,
})
export class FormErrorSummaryComponent {
  readonly title = input.required<string>();
  readonly items = input<readonly FormErrorItem[]>([]);
}
