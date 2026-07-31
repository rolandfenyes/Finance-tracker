import { ChangeDetectionStrategy, Component } from '@angular/core';
import { PageHeaderComponent } from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';
import { ShellBrandComponent } from './shell-brand';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, ShellBrandComponent, TranslocoPipe],
  selector: 'mmm-auth-shell',
  template: `
    <main id="main-content" class="shell-page grid place-items-center">
      <section class="shell-card max-w-3xl">
        <div class="shell-content">
          <mmm-shell-brand />
          <mmm-page-header
            [eyebrow]="'shell.auth.eyebrow' | transloco"
            [title]="'shell.auth.title' | transloco"
            [description]="'shell.auth.description' | transloco"
          />
        </div>
      </section>
    </main>
  `,
})
export class AuthShellComponent {}
