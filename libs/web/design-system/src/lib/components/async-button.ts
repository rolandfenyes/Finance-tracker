import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { MatButton } from '@angular/material/button';
import { MatProgressSpinner } from '@angular/material/progress-spinner';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButton, MatProgressSpinner],
  selector: 'mmm-async-button',
  template: `
    <button
      mat-flat-button
      type="button"
      [attr.aria-busy]="pending()"
      [disabled]="disabled() || pending()"
      (click)="activated.emit()"
    >
      @if (pending()) {
        <mat-spinner aria-hidden="true" diameter="18" />
      }
      <span>{{ label() }}</span>
    </button>
  `,
})
export class AsyncButtonComponent {
  readonly label = input.required<string>();
  readonly pending = input(false);
  readonly disabled = input(false);
  readonly activated = output<void>();
}
