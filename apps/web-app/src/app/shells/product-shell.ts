import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import type { EntitlementsDto } from '@mymoneymap/generated-api-client/models/entitlements-dto';
import { SessionStore } from '@mymoneymap/web-core';
import { ShellBrandComponent } from './shell-brand';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    MatIconModule,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
    ShellBrandComponent,
    TranslocoPipe,
  ],
  selector: 'mmm-product-shell',
  template: `
    <div class="product-app-shell" [class.sidebar-collapsed]="collapsed()">
      <aside class="product-sidebar">
        <div class="product-sidebar-header">
          <mmm-shell-brand />
          <button
            class="icon-button sidebar-toggle"
            type="button"
            [attr.aria-label]="
              (collapsed() ? 'shell.product.expandNavigation' : 'shell.product.collapseNavigation')
                | transloco
            "
            [attr.aria-expanded]="!collapsed()"
            (click)="collapsed.set(!collapsed())"
          >
            <mat-icon
              aria-hidden="true"
              [svgIcon]="collapsed() ? 'chevron-right' : 'chevron-left'"
            />
          </button>
        </div>
        <nav
          class="product-navigation"
          [attr.aria-label]="'shell.product.navigationLabel' | transloco"
        >
          @for (item of navigation(); track item.id) {
            <a
              [routerLink]="item.route"
              routerLinkActive="active"
              [routerLinkActiveOptions]="{ exact: item.id === 'home' }"
            >
              <mat-icon aria-hidden="true" [svgIcon]="item.icon" />
              <span>{{ item.labelKey | transloco }}</span>
            </a>
          }
        </nav>
      </aside>

      <main id="main-content" class="product-content">
        <router-outlet />
      </main>

      <nav
        class="product-bottom-navigation"
        [attr.aria-label]="'shell.product.navigationLabel' | transloco"
      >
        @for (item of navigation(); track item.id) {
          <a
            [routerLink]="item.route"
            routerLinkActive="active"
            [routerLinkActiveOptions]="{ exact: item.id === 'home' }"
          >
            <mat-icon aria-hidden="true" [svgIcon]="item.icon" />
            <span>{{ item.labelKey | transloco }}</span>
          </a>
        }
      </nav>
    </div>
  `,
})
export class ProductShellComponent {
  private readonly session = inject(SessionStore);
  protected readonly collapsed = signal(false);
  protected readonly navigation = computed(() =>
    filterProductNavigation(this.session.currentUser()?.entitlements),
  );
}

const PRODUCT_NAVIGATION = [
  { id: 'home', labelKey: 'navigation.home', icon: 'home', route: '/app/home' },
  { id: 'activity', labelKey: 'navigation.activity', icon: 'activity', route: '/app/activity' },
  { id: 'plan', labelKey: 'navigation.plan', icon: 'plan', route: '/app/plan' },
  {
    id: 'goals',
    labelKey: 'navigation.goals',
    icon: 'goals',
    route: '/app/goals',
    requiredResource: 'activeGoals' as const,
  },
  { id: 'more', labelKey: 'navigation.more', icon: 'more', route: '/app/more' },
] as const;

type ProductNavigationItem = (typeof PRODUCT_NAVIGATION)[number];

function filterProductNavigation(
  entitlements: EntitlementsDto | null | undefined,
): readonly ProductNavigationItem[] {
  if (!entitlements?.personalFinanceAccess) return [];
  return PRODUCT_NAVIGATION.filter(
    (item) =>
      !('requiredResource' in item) || entitlements.resources[item.requiredResource].allowed,
  );
}
