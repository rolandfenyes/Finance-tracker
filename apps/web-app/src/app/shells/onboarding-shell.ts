import { ChangeDetectionStrategy, Component } from '@angular/core';
import { PageHeaderComponent } from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';
import { ShellBrandComponent } from './shell-brand';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, ShellBrandComponent, TranslocoPipe],
  selector: 'mmm-onboarding-shell',
  template: `
    <main id="main-content" class="shell-page grid place-items-center">
      <section class="shell-card max-w-4xl">
        <div class="shell-content">
          <mmm-shell-brand />
          <mmm-page-header
            [eyebrow]="'shell.onboarding.eyebrow' | transloco"
            [title]="'shell.onboarding.title' | transloco"
            [description]="'shell.onboarding.description' | transloco"
          />
        </div>
      </section>
    </main>
  `,
})
export class OnboardingShellComponent {}
