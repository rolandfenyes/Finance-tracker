import type { Route } from '@angular/router';

export const appRoutes: Route[] = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'auth',
  },
  {
    path: 'auth',
    loadComponent: () =>
      import('./shells/auth-shell').then((component) => component.AuthShellComponent),
  },
  {
    path: 'onboarding',
    loadComponent: () =>
      import('./shells/onboarding-shell').then((component) => component.OnboardingShellComponent),
  },
  {
    path: 'app',
    loadComponent: () =>
      import('./shells/product-shell').then((component) => component.ProductShellComponent),
  },
  {
    path: 'admin',
    loadComponent: () =>
      import('./shells/admin-shell').then((component) => component.AdminShellComponent),
  },
  {
    path: '**',
    loadComponent: () =>
      import('./shells/error-shell').then((component) => component.ErrorShellComponent),
  },
];
