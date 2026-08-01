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
  { path: 'more', component: MoreHubPageComponent },
];
