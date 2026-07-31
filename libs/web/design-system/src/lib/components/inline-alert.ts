import { ChangeDetectionStrategy, Component, input } from '@angular/core';

export type AlertTone = 'information' | 'success' | 'warning' | 'danger';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '[attr.data-tone]': 'tone()',
    '[attr.role]': "tone() === 'danger' ? 'alert' : 'status'",
  },
  selector: 'mmm-inline-alert',
  template: `
    <span class="alert-marker" aria-hidden="true"></span>
    <span class="alert-content"><ng-content /></span>
  `,
})
export class InlineAlertComponent {
  readonly tone = input<AlertTone>('information');
}
