import { ChangeDetectionStrategy, Component } from '@angular/core';
import { TranslocoPipe } from '@jsverse/transloco';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [TranslocoPipe],
  selector: 'mmm-shell-brand',
  template: `
    <div class="shell-brand">
      <span class="shell-brand-mark" aria-hidden="true">M</span>
      <span>{{ 'app.name' | transloco }}</span>
    </div>
  `,
})
export class ShellBrandComponent {}
