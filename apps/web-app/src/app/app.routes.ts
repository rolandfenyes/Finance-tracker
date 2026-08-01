import type { Route } from '@angular/router';
import {
  administrationGuard,
  authenticatedGuard,
  entryRouteGuard,
  onboardingCompleteGuard,
  onboardingRequiredGuard,
  personalFinanceGuard,
  RouteStatusPageComponent,
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
    loadChildren: () => import('@mymoneymap/feature-auth').then((feature) => feature.AUTH_ROUTES),
  },
  {
    path: 'onboarding',
    canMatch: [
      authenticatedGuard,
      verifiedEmailGuard,
      personalFinanceGuard,
      onboardingRequiredGuard,
    ],
    loadChildren: () =>
      import('@mymoneymap/feature-onboarding').then((feature) => feature.ONBOARDING_ROUTES),
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
    loadChildren: () =>
      import('@mymoneymap/feature-dashboard').then((feature) => feature.DASHBOARD_ROUTES),
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
