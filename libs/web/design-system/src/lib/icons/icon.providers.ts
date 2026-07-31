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
