import { HttpErrorResponse } from '@angular/common/http';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, UrlTree } from '@angular/router';
import type { CurrentUserResponseDto } from '@mymoneymap/generated-api-client/models/current-user-response-dto';
import { UsersAndSettingsService } from '@mymoneymap/generated-api-client/services/users-and-settings.service';
import { AppLanguageService } from '@mymoneymap/web-shared';
import { of, throwError } from 'rxjs';
import {
  administrationGuard,
  authenticatedGuard,
  capabilityGuard,
  CONFIRM_PENDING_CHANGES,
  onboardingCompleteGuard,
  onboardingRequiredGuard,
  pendingChangesGuard,
  personalFinanceGuard,
  signedOutGuard,
  verifiedEmailGuard,
} from './guards';
import { OnboardingPolicy } from './onboarding-policy';
import { SessionState, type SessionStatus } from './session-state';
import { SessionStore } from './session.store';

describe('SessionStore', () => {
  const language = { setLanguage: vi.fn() };
  const users = { usersControllerCurrentUser: vi.fn() };

  beforeEach(() => {
    language.setLanguage.mockReset();
    users.usersControllerCurrentUser.mockReset();
    TestBed.configureTestingModule({
      providers: [
        SessionState,
        SessionStore,
        { provide: UsersAndSettingsService, useValue: users },
        { provide: AppLanguageService, useValue: language },
      ],
    });
  });

  it('bootstraps and refreshes exclusively through the generated current-user operation', async () => {
    users.usersControllerCurrentUser.mockReturnValue(of(syntheticUser('free')));
    const store = TestBed.inject(SessionStore);
    await store.bootstrap();
    await store.refresh();

    expect(users.usersControllerCurrentUser).toHaveBeenCalledTimes(2);
    expect(store.status()).toBe('authenticated');
    expect(store.currentUser()?.role).toBe('free');
    expect(language.setLanguage).toHaveBeenLastCalledWith('hu');
  });

  it('treats 401 as signed out and other failures as unavailable without retaining user state', async () => {
    const store = TestBed.inject(SessionStore);
    users.usersControllerCurrentUser.mockReturnValue(
      throwError(() => new HttpErrorResponse({ status: 401 })),
    );
    await store.bootstrap();
    expect(store.status()).toBe('anonymous');
    expect(store.currentUser()).toBeNull();

    users.usersControllerCurrentUser.mockReturnValue(
      throwError(() => new HttpErrorResponse({ status: 503 })),
    );
    await store.refresh();
    expect(store.status()).toBe('unavailable');
    expect(store.currentUser()).toBeNull();
  });
});

describe('route policy matrix', () => {
  const status = signal<SessionStatus>('authenticated');
  const currentUser = signal(syntheticUser('free'));
  const store = {
    status: status.asReadonly(),
    currentUser: currentUser.asReadonly(),
    authenticated: (): boolean => status() === 'authenticated',
  };
  const onboarding = { load: vi.fn() };

  beforeEach(() => {
    status.set('authenticated');
    currentUser.set(syntheticUser('free'));
    onboarding.load.mockReset();
    onboarding.load.mockResolvedValue({
      currentStep: 6,
      next: 'complete',
      onboardingComplete: true,
      tutorialCompleted: true,
      tutorialRequired: false,
    });
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: SessionStore, useValue: store },
        { provide: OnboardingPolicy, useValue: onboarding },
      ],
    });
  });

  it('routes anonymous, personal, unverified, and admin sessions by server-owned state', () => {
    status.set('anonymous');
    expect(url(run(authenticatedGuard))).toBe('/auth');
    expect(run(signedOutGuard)).toBe(true);

    status.set('authenticated');
    currentUser.set(syntheticUser('free'));
    expect(run(authenticatedGuard)).toBe(true);
    expect(run(verifiedEmailGuard)).toBe(true);
    expect(run(personalFinanceGuard)).toBe(true);
    expect(url(run(administrationGuard))).toBe('/forbidden?reason=administration');

    currentUser.set({ ...syntheticUser('free'), emailVerified: false });
    expect(url(run(verifiedEmailGuard))).toBe('/forbidden?reason=verification');

    currentUser.set(syntheticUser('admin'));
    expect(run(administrationGuard)).toBe(true);
    expect(url(run(personalFinanceGuard))).toBe('/forbidden?reason=personal');
    expect(url(run(signedOutGuard))).toBe('/admin');
  });

  it('enforces capability and onboarding gates from generated entitlement/read-model values', async () => {
    currentUser.set(syntheticUser('free'));
    expect(run(capabilityGuard, { data: { capability: 'currencies' } })).toBe(true);
    expect(url(run(capabilityGuard, { data: { capability: 'cashFlowRuleEditing' } }))).toBe(
      '/forbidden?reason=capability',
    );
    expect(await runAsync(onboardingCompleteGuard)).toBe(true);

    onboarding.load.mockResolvedValue({
      currentStep: 2,
      next: 'rules',
      onboardingComplete: false,
      tutorialCompleted: false,
      tutorialRequired: true,
    });
    expect(url(await runAsync(onboardingCompleteGuard))).toBe('/onboarding');
    expect(await runAsync(onboardingRequiredGuard)).toBe(true);
  });

  function run(guard: typeof authenticatedGuard, route: Parameters<typeof guard>[0] = {}): unknown {
    return TestBed.runInInjectionContext(() => guard(route, []));
  }

  async function runAsync(guard: typeof onboardingCompleteGuard): Promise<unknown> {
    return await TestBed.runInInjectionContext(() => guard({}, []));
  }

  function url(value: unknown): string {
    return value instanceof UrlTree ? value.toString() : '';
  }
});

describe('pending changes policy', () => {
  it('allows clean navigation and requires explicit confirmation for dirty state', () => {
    const confirm = vi.fn((): boolean => true);
    TestBed.configureTestingModule({
      providers: [{ provide: CONFIRM_PENDING_CHANGES, useValue: confirm }],
    });
    const clean = { hasPendingChanges: (): boolean => false };
    const dirty = { hasPendingChanges: (): boolean => true };
    expect(
      TestBed.runInInjectionContext(() =>
        pendingChangesGuard(clean, {} as never, {} as never, {} as never),
      ),
    ).toBe(true);
    expect(
      TestBed.runInInjectionContext(() =>
        pendingChangesGuard(dirty, {} as never, {} as never, {} as never),
      ),
    ).toBe(true);
    expect(confirm).toHaveBeenCalledWith('navigationConfirmDiscardChanges');
  });
});

function syntheticUser(role: 'free' | 'admin'): CurrentUserResponseDto {
  const admin = role === 'admin';
  return {
    id: '00000000-0000-4000-8000-000000000001',
    email: 'synthetic@example.test',
    fullName: 'Synthetic User',
    dateOfBirth: '1990-01-01',
    desiredLanguage: 'hu' as const,
    emailVerified: true,
    role,
    theme: 'verdant-horizon' as const,
    entitlements: {
      administration: admin,
      cashFlowRuleEditing: false,
      personalFinanceAccess: !admin,
      resources: {
        activeGoals: { allowed: !admin, limit: admin ? null : 2 },
        activeLoans: { allowed: !admin, limit: admin ? null : 2 },
        activeScheduledItems: { allowed: !admin, limit: admin ? null : 2 },
        categories: { allowed: !admin, limit: admin ? null : 10 },
        currencies: { allowed: !admin, limit: admin ? null : 1 },
      },
    },
  };
}
