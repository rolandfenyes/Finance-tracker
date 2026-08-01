import type { Routes } from '@angular/router';
import { DashboardPageComponent, MoreHubPageComponent } from './lib/dashboard-pages';

export { DashboardFacade } from './lib/dashboard.facade';
export {
  filterProductNavigation,
  filterMoreNavigation,
  PRODUCT_NAVIGATION,
  MORE_NAVIGATION,
} from './lib/product-navigation';
export type { ProductNavigationItem } from './lib/product-navigation';

export const DASHBOARD_ROUTES: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'home' },
  { path: 'home', component: DashboardPageComponent },
  {
    path: 'activity',
    loadChildren: () =>
      import('@mymoneymap/feature-transactions').then((feature) => feature.JOURNAL_ROUTES),
  },
  {
    path: 'reports',
    loadChildren: () =>
      import('@mymoneymap/feature-reports').then((feature) => feature.REPORT_ROUTES),
  },
  {
    path: 'plan',
    loadChildren: () =>
      import('@mymoneymap/feature-planning').then((feature) => feature.PLANNING_ROUTES),
  },
  {
    path: 'settings/categories',
    loadComponent: () =>
      import('@mymoneymap/feature-planning').then((feature) => feature.CategoriesPageComponent),
  },
  {
    path: 'settings/income',
    loadComponent: () =>
      import('@mymoneymap/feature-planning').then((feature) => feature.IncomePageComponent),
  },
  { path: 'more', component: MoreHubPageComponent },
];
