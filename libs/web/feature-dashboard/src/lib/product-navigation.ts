import type { EntitlementsDto } from '@mymoneymap/generated-api-client/models/entitlements-dto';
import type { ResourceEntitlementsDto } from '@mymoneymap/generated-api-client/models/resource-entitlements-dto';

type ResourceName = keyof ResourceEntitlementsDto;

export interface ProductNavigationItem {
  readonly id: string;
  readonly labelKey: string;
  readonly icon: string;
  readonly route: string;
  readonly requiredResource?: ResourceName;
}

export const PRODUCT_NAVIGATION: readonly ProductNavigationItem[] = [
  { id: 'home', labelKey: 'navigation.home', icon: 'home', route: '/app/home' },
  { id: 'activity', labelKey: 'navigation.activity', icon: 'activity', route: '/app/activity' },
  { id: 'plan', labelKey: 'navigation.plan', icon: 'plan', route: '/app/plan' },
  {
    id: 'goals',
    labelKey: 'navigation.goals',
    icon: 'goals',
    route: '/app/goals',
    requiredResource: 'activeGoals',
  },
  { id: 'more', labelKey: 'navigation.more', icon: 'more', route: '/app/more' },
] as const;

export const MORE_NAVIGATION: readonly ProductNavigationItem[] = [
  { id: 'reserve', labelKey: 'more.reserve', icon: 'reserve', route: '/app/reserve' },
  {
    id: 'loans',
    labelKey: 'more.loans',
    icon: 'loans',
    route: '/app/loans',
    requiredResource: 'activeLoans',
  },
  {
    id: 'investments',
    labelKey: 'more.investments',
    icon: 'investments',
    route: '/app/investments',
  },
  {
    id: 'securities',
    labelKey: 'more.securities',
    icon: 'securities',
    route: '/app/securities',
  },
  { id: 'reports', labelKey: 'more.reports', icon: 'reports', route: '/app/reports' },
  { id: 'feedback', labelKey: 'more.feedback', icon: 'feedback', route: '/app/feedback' },
  { id: 'settings', labelKey: 'more.settings', icon: 'settings', route: '/app/settings' },
] as const;

export function filterProductNavigation(
  items: readonly ProductNavigationItem[],
  entitlements: EntitlementsDto | null | undefined,
): readonly ProductNavigationItem[] {
  if (!entitlements?.personalFinanceAccess) return [];
  return items.filter(
    (item) => !item.requiredResource || entitlements.resources[item.requiredResource].allowed,
  );
}

export const filterMoreNavigation = filterProductNavigation;
