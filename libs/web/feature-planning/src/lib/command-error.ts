import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslocoPipe } from '@jsverse/transloco';
import { InlineAlertComponent } from '@mymoneymap/web-design-system';
import { PlanningFacade } from './planning.facade';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [InlineAlertComponent, TranslocoPipe],
  selector: 'mmm-command-error',
  template: `@if (facade().commandError(); as error) {
    <mmm-inline-alert tone="danger"
      >{{ messageKey(error.status) | transloco }}
      @if (error.requestId) {
        <small>{{ error.requestId }}</small>
      }
    </mmm-inline-alert>
  }`,
})
export class CommandErrorComponent {
  readonly facade = input.required<PlanningFacade>();

  protected messageKey(status: number): string {
    if (status === 409) return 'planning.errors.conflict';
    if (status === 422) return 'planning.errors.validation';
    if (status === 403) return 'planning.errors.forbidden';
    if (status === 429) return 'planning.errors.rateLimit';
    return 'planning.errors.generic';
  }
}
