import type { Route } from '@angular/router';
import {
  administrationGuard,
  authenticatedGuard,
  entryRouteGuard,
  onboardingCompleteGuard,
  onboardingRequiredGuard,
  personalFinanceGuard,
  RouteStatusPageComponent,
  signedOutGuard,
  verifiedEmailGuard,
} from '@mymoneymap/web-core';

export const appRoutes: Route[] = [
  {
    path: '',
    pathMatch: 'full',
    canMatch: [entryRouteGuard],
    component: RouteStatusPageComponent,
  },
  {
    path: 'auth',
    canMatch: [signedOutGuard],
    loadComponent: () =>
      import('./shells/auth-shell').then((component) => component.AuthShellComponent),
  },
  {
    path: 'onboarding',
    canMatch: [
      authenticatedGuard,
      verifiedEmailGuard,
      personalFinanceGuard,
      onboardingRequiredGuard,
    ],
    loadComponent: () =>
      import('./shells/onboarding-shell').then((component) => component.OnboardingShellComponent),
  },
  {
    path: 'app',
    canMatch: [
      authenticatedGuard,
      verifiedEmailGuard,
      personalFinanceGuard,
      onboardingCompleteGuard,
    ],
    loadComponent: () =>
      import('./shells/product-shell').then((component) => component.ProductShellComponent),
  },
  {
    path: 'admin',
    canMatch: [authenticatedGuard, verifiedEmailGuard, administrationGuard],
    loadComponent: () =>
      import('./shells/admin-shell').then((component) => component.AdminShellComponent),
  },
  {
    path: 'unavailable',
    component: RouteStatusPageComponent,
    data: { status: 'unavailable' },
  },
  {
    path: 'forbidden',
    component: RouteStatusPageComponent,
    data: { status: 'forbidden' },
  },
  {
    path: 'not-found',
    component: RouteStatusPageComponent,
    data: { status: 'notFound' },
  },
  {
    path: '**',
    redirectTo: 'not-found',
  },
];
