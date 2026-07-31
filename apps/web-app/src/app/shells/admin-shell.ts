import { ChangeDetectionStrategy, Component } from '@angular/core';
import { InlineAlertComponent, PageHeaderComponent } from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';
import { ShellBrandComponent } from './shell-brand';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [InlineAlertComponent, PageHeaderComponent, ShellBrandComponent, TranslocoPipe],
  selector: 'mmm-admin-shell',
  template: `
    <main id="main-content" class="shell-page">
      <section class="shell-card">
        <div class="shell-content">
          <mmm-shell-brand />
          <mmm-page-header
            [eyebrow]="'shell.admin.eyebrow' | transloco"
            [title]="'shell.admin.title' | transloco"
            [description]="'shell.admin.description' | transloco"
          />
          <mmm-inline-alert tone="information">
            {{ 'state.disabled' | transloco }}
          </mmm-inline-alert>
        </div>
      </section>
    </main>
  `,
})
export class AdminShellComponent {}
