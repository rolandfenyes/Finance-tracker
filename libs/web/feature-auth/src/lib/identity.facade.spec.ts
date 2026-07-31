import { HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import { OnboardingPolicy, SessionStore } from '@mymoneymap/web-core';
import { of, throwError } from 'rxjs';
import { IdentityFacade } from './identity.facade';
import { PasskeyBrowserAdapter } from './passkey-browser.adapter';

describe('IdentityFacade', () => {
  const api = {
    identityControllerLogin: vi.fn(),
    identityControllerRegister: vi.fn(),
    identityControllerRequestVerification: vi.fn(),
    identityControllerVerify: vi.fn(),
    identityControllerLogout: vi.fn(),
  };
  const browser = { supported: vi.fn(() => true) };
  const currentUser = vi.fn();
  const session = { clear: vi.fn(), currentUser, refresh: vi.fn() };
  const onboarding = { load: vi.fn() };
  const router = { navigateByUrl: vi.fn() };

  beforeEach(() => {
    vi.clearAllMocks();
    router.navigateByUrl.mockResolvedValue(true);
    TestBed.configureTestingModule({
      providers: [
        IdentityFacade,
        { provide: IdentityService, useValue: api },
        { provide: PasskeyBrowserAdapter, useValue: browser },
        { provide: SessionStore, useValue: session },
        { provide: OnboardingPolicy, useValue: onboarding },
        { provide: Router, useValue: router },
      ],
    });
  });

  it('keeps registration success non-enumerating and forwards the exact generated DTO', async () => {
    api.identityControllerRegister.mockReturnValue(of(undefined));
    const facade = TestBed.inject(IdentityFacade);
    const body = {
      dateOfBirth: '1990-01-01',
      email: 'synthetic@example.test',
      fullName: 'Synthetic User',
      password: 'synthetic-password',
    };

    await facade.register(body);

    expect(api.identityControllerRegister).toHaveBeenCalledWith({ body }, expect.anything());
    expect(facade.state()).toEqual({ phase: 'accepted' });
    expect(router.navigateByUrl).toHaveBeenCalledWith('/auth/verification-sent');
  });

  it('preserves remember on password login and follows the server-owned onboarding destination', async () => {
    api.identityControllerLogin.mockReturnValue(of(undefined));
    session.refresh.mockResolvedValue(undefined);
    currentUser.mockReturnValue(user());
    onboarding.load.mockResolvedValue({
      currentStep: 3,
      next: 'currencies',
      onboardingComplete: false,
      tutorialCompleted: false,
      tutorialRequired: true,
    });
    const facade = TestBed.inject(IdentityFacade);

    await facade.login({ email: 'synthetic@example.test', password: 'secret', remember: true });

    expect(api.identityControllerLogin).toHaveBeenCalledWith(
      { body: { email: 'synthetic@example.test', password: 'secret', remember: true } },
      expect.anything(),
    );
    expect(router.navigateByUrl).toHaveBeenLastCalledWith('/onboarding/currencies');
  });

  it('surfaces throttling generically with retry metadata and never changes route', async () => {
    api.identityControllerLogin.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 429,
            headers: new HttpHeaders({ 'retry-after': '17', 'x-request-id': 'synthetic-id' }),
            error: { error: { code: 'RATE_LIMITED' } },
          }),
      ),
    );
    const facade = TestBed.inject(IdentityFacade);

    await facade.login({ email: 'synthetic@example.test', password: 'secret', remember: false });

    expect(facade.state()).toMatchObject({
      phase: 'failed',
      error: { kind: 'rate-limit', retryAfterSeconds: 17 },
    });
    expect(router.navigateByUrl).not.toHaveBeenCalled();
  });

  it('uses the same accepted state for every verification resend response', async () => {
    api.identityControllerRequestVerification.mockReturnValue(of(undefined));
    const facade = TestBed.inject(IdentityFacade);

    await facade.requestVerification({ email: 'unknown@example.test' });

    expect(facade.state()).toEqual({ phase: 'accepted' });
  });

  it('keeps invalid or expired verification failures generic and does not navigate', async () => {
    api.identityControllerVerify.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 410,
            error: { error: { code: 'EMAIL_VERIFICATION_TOKEN_INVALID' } },
          }),
      ),
    );
    const facade = TestBed.inject(IdentityFacade);

    await facade.verify('synthetic-expired-token');

    expect(facade.state()).toMatchObject({ phase: 'failed', error: { status: 410 } });
    expect(router.navigateByUrl).not.toHaveBeenCalled();
  });

  it('logs out through the server, clears session state, and returns to sign in', async () => {
    api.identityControllerLogout.mockReturnValue(of(undefined));
    const facade = TestBed.inject(IdentityFacade);

    await facade.logout();

    expect(api.identityControllerLogout).toHaveBeenCalledWith(undefined, expect.anything());
    expect(session.clear).toHaveBeenCalledOnce();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/auth/login');
  });
});

function user(): ReturnType<SessionStore['currentUser']> {
  return {
    id: '00000000-0000-4000-8000-000000000001',
    email: 'synthetic@example.test',
    fullName: 'Synthetic User',
    dateOfBirth: '1990-01-01',
    desiredLanguage: 'en' as const,
    emailVerified: true,
    role: 'free' as const,
    theme: 'verdant-horizon' as const,
    entitlements: {
      administration: false,
      cashFlowRuleEditing: false,
      personalFinanceAccess: true,
      resources: {
        activeGoals: { allowed: true, limit: 2 },
        activeLoans: { allowed: true, limit: 2 },
        activeScheduledItems: { allowed: true, limit: 2 },
        categories: { allowed: true, limit: 10 },
        currencies: { allowed: true, limit: 1 },
      },
    },
  };
}
