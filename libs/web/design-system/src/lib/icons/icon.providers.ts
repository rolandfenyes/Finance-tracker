import {
  inject,
  makeEnvironmentProviders,
  provideEnvironmentInitializer,
  type EnvironmentProviders,
} from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { MatIconRegistry } from '@angular/material/icon';
import { ThemeService } from '../theme/theme.service';

const ICONS = {
  activity:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h3l2-6 4 12 2-6h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  goals:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
  home: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.5 12 5l8 6.5V20h-5v-5H9v5H4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
  more: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></svg>',
  plan: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 9h8M8 13h8M8 17h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  'chevron-left':
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  'chevron-right':
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  reserve:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 10h14v9H5zM8 10V7h8v3M9 14h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  loans:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  investments:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 17 5-5 4 3 7-8M15 7h5v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  securities:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4v16M4 8h4M12 4v16M10 14h4M18 4v16M16 10h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  reports:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V9h4v11M10 20V4h4v16M15 20v-7h4v7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
  feedback:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v12H9l-5 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
  settings:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
} as const;

export function provideMymoneyMapIcons(): EnvironmentProviders {
  return makeEnvironmentProviders([
    provideEnvironmentInitializer(() => {
      inject(ThemeService);
      const registry = inject(MatIconRegistry);
      const sanitizer = inject(DomSanitizer);

      for (const [name, svg] of Object.entries(ICONS)) {
        registry.addSvgIconLiteral(name, sanitizer.bypassSecurityTrustHtml(svg));
      }
    }),
  ]);
}
