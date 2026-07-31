import { ChangeDetectionStrategy, Component } from '@angular/core';
import { PageHeaderComponent } from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';
import { ShellBrandComponent } from './shell-brand';

const NAVIGATION_KEYS = ['home', 'activity', 'plan', 'goals', 'more'] as const;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, ShellBrandComponent, TranslocoPipe],
  selector: 'mmm-product-shell',
  template: `
    <main id="main-content" class="shell-page">
      <section class="shell-card product-shell">
        <nav
          class="shell-navigation"
          [attr.aria-label]="'shell.product.navigationLabel' | transloco"
        >
          @for (item of navigation; track item) {
            <span>{{ 'navigation.' + item | transloco }}</span>
          }
        </nav>
        <div class="shell-content">
          <mmm-shell-brand />
          <mmm-page-header
            [eyebrow]="'shell.product.eyebrow' | transloco"
            [title]="'shell.product.title' | transloco"
            [description]="'shell.product.description' | transloco"
          />
        </div>
      </section>
    </main>
  `,
})
export class ProductShellComponent {
  protected readonly navigation = NAVIGATION_KEYS;
}
