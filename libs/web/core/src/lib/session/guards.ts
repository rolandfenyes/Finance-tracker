import { inject, InjectionToken } from '@angular/core';
import type { CanDeactivateFn, CanMatchFn, Route } from '@angular/router';
import { Router } from '@angular/router';
import type { ResourceEntitlementsDto } from '@mymoneymap/generated-api-client/models/resource-entitlements-dto';
import { SessionStore } from './session.store';
import { OnboardingPolicy } from './onboarding-policy';

export type Capability = 'cashFlowRuleEditing' | keyof ResourceEntitlementsDto;

export interface PendingChangesAware {
  hasPendingChanges(): boolean;
}

export const CONFIRM_PENDING_CHANGES = new InjectionToken<(messageKey: string) => boolean>(
  'CONFIRM_PENDING_CHANGES',
  { factory: (): ((messageKey: string) => boolean) => (): boolean => false },
);

export const signedOutGuard: CanMatchFn = () => {
  const session = inject(SessionStore);
  if (session.status() === 'unavailable') return inject(Router).parseUrl('/unavailable');
  return session.authenticated() ? destinationForUser(session) : true;
};

export const authenticatedGuard: CanMatchFn = () => {
  const session = inject(SessionStore);
  if (session.status() === 'unavailable') return inject(Router).parseUrl('/unavailable');
  return session.authenticated() ? true : inject(Router).parseUrl('/auth');
};

export const verifiedEmailGuard: CanMatchFn = () => {
  const user = inject(SessionStore).currentUser();
  return user?.emailVerified === true
    ? true
    : inject(Router).parseUrl('/forbidden?reason=verification');
};

export const personalFinanceGuard: CanMatchFn = () => {
  const user = inject(SessionStore).currentUser();
  return user?.entitlements.personalFinanceAccess === true
    ? true
    : inject(Router).parseUrl('/forbidden?reason=personal');
};

export const administrationGuard: CanMatchFn = () => {
  const user = inject(SessionStore).currentUser();
  return user?.entitlements.administration === true
    ? true
    : inject(Router).parseUrl('/forbidden?reason=administration');
};

export const capabilityGuard: CanMatchFn = (route: Route) => {
  const capability = route.data?.['capability'] as Capability | undefined;
  const user = inject(SessionStore).currentUser();
  if (!capability || !user) return inject(Router).parseUrl('/forbidden?reason=capability');
  const allowed =
    capability === 'cashFlowRuleEditing'
      ? user.entitlements.cashFlowRuleEditing
      : user.entitlements.resources[capability].allowed;
  return allowed ? true : inject(Router).parseUrl('/forbidden?reason=capability');
};

export const onboardingRequiredGuard: CanMatchFn = async () => {
  const onboarding = inject(OnboardingPolicy);
  const router = inject(Router);
  try {
    const state = await onboarding.load();
    return state.next === 'complete' ? router.parseUrl('/app') : true;
  } catch {
    return router.parseUrl('/unavailable');
  }
};

export const onboardingCompleteGuard: CanMatchFn = async () => {
  const onboarding = inject(OnboardingPolicy);
  const router = inject(Router);
  try {
    const state = await onboarding.load();
    return state.next === 'complete' ? true : router.parseUrl('/onboarding');
  } catch {
    return router.parseUrl('/unavailable');
  }
};

export const entryRouteGuard: CanMatchFn = async () => {
  const session = inject(SessionStore);
  const router = inject(Router);
  const onboardingPolicy = inject(OnboardingPolicy);
  if (!session.authenticated()) {
    return router.parseUrl(session.status() === 'unavailable' ? '/unavailable' : '/auth');
  }
  const user = session.currentUser();
  if (user?.entitlements.administration) return router.parseUrl('/admin');
  if (!user?.emailVerified) return router.parseUrl('/forbidden?reason=verification');
  if (!user.entitlements.personalFinanceAccess) {
    return router.parseUrl('/forbidden?reason=personal');
  }
  try {
    const onboarding = await onboardingPolicy.load();
    return router.parseUrl(onboarding.next === 'complete' ? '/app' : '/onboarding');
  } catch {
    return router.parseUrl('/unavailable');
  }
};

export const pendingChangesGuard: CanDeactivateFn<PendingChangesAware> = (component) =>
  !component.hasPendingChanges() ||
  inject(CONFIRM_PENDING_CHANGES)('navigationConfirmDiscardChanges');

function destinationForUser(session: SessionStore): ReturnType<Router['parseUrl']> {
  const router = inject(Router);
  const user = session.currentUser();
  if (user?.entitlements.administration) return router.parseUrl('/admin');
  if (!user?.emailVerified) return router.parseUrl('/forbidden?reason=verification');
  return router.parseUrl('/app');
}
