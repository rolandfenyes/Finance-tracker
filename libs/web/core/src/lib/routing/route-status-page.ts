import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { PageHeaderComponent } from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';

export type RouteStatus = 'unavailable' | 'forbidden' | 'notFound';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageHeaderComponent, RouterLink, TranslocoPipe],
  selector: 'mmm-route-status-page',
  template: `
    <main id="main-content" class="shell-page grid place-items-center">
      <section class="shell-card max-w-3xl">
        <div class="shell-content">
          <mmm-page-header
            [eyebrow]="'routeStatus.' + status() + '.eyebrow' | transloco"
            [title]="'routeStatus.' + status() + '.title' | transloco"
            [description]="'routeStatus.' + status() + '.description' | transloco"
          />
          <a class="route-status-link" routerLink="/">
            {{ 'routeStatus.return' | transloco }}
          </a>
        </div>
      </section>
    </main>
  `,
})
export class RouteStatusPageComponent {
  readonly status = input.required<RouteStatus>();
}
