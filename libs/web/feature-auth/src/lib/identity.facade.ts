import { HttpContext, HttpErrorResponse } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import type { EmailVerificationRequestDto } from '@mymoneymap/generated-api-client/models/email-verification-request-dto';
import type { PasswordChangeDto } from '@mymoneymap/generated-api-client/models/password-change-dto';
import type { PasswordSessionDto } from '@mymoneymap/generated-api-client/models/password-session-dto';
import type { RegistrationDto } from '@mymoneymap/generated-api-client/models/registration-dto';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import {
  API_ROUTE_TEMPLATE,
  ApiClientError,
  OnboardingPolicy,
  parseApiError,
  SessionStore,
} from '@mymoneymap/web-core';
import { firstValueFrom } from 'rxjs';
import {
  PasskeyBrowserAdapter,
  PasskeyCancelledError,
  PasskeyUnsupportedError,
} from './passkey-browser.adapter';

export type IdentityCommandState =
  | { readonly phase: 'idle' }
  | { readonly phase: 'submitting' }
  | { readonly phase: 'accepted' }
  | { readonly phase: 'failed'; readonly error: ApiClientError | Error };

@Injectable({ providedIn: 'root' })
export class IdentityFacade {
  private readonly api = inject(IdentityService);
  private readonly browser = inject(PasskeyBrowserAdapter);
  private readonly session = inject(SessionStore);
  private readonly onboarding = inject(OnboardingPolicy);
  private readonly router = inject(Router);
  private readonly commandState = signal<IdentityCommandState>({ phase: 'idle' });

  readonly state = this.commandState.asReadonly();
  readonly passkeySupported = this.browser.supported();

  async register(body: RegistrationDto): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerRegister({ body }, context('/api/v1/auth/registrations')),
      );
      await this.router.navigateByUrl('/auth/verification-sent');
    });
  }

  async login(body: PasswordSessionDto): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerLogin({ body }, context('/api/v1/auth/sessions')),
      );
      await this.routeAfterAuthentication();
    });
  }

  async verify(token: string): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerVerify(
          { body: { token } },
          context('/api/v1/auth/email-verifications'),
        ),
      );
      await this.routeAfterAuthentication();
    });
  }

  async requestVerification(body: EmailVerificationRequestDto): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerRequestVerification(
          { body },
          context('/api/v1/auth/email-verification-requests'),
        ),
      );
    });
  }

  async passkeyLogin(email: string | undefined, remember: boolean): Promise<void> {
    await this.run(async () => {
      const options = await firstValueFrom(
        this.api.identityControllerPasskeyOptions(
          { body: { email: email || undefined } },
          context('/api/v1/auth/passkey-sessions/options'),
        ),
      );
      const credential = await this.browser.get(options);
      await firstValueFrom(
        this.api.identityControllerPasskeyLogin(
          { body: { credential, remember } },
          context('/api/v1/auth/passkey-sessions'),
        ),
      );
      await this.routeAfterAuthentication();
    });
  }

  async changePassword(body: PasswordChangeDto): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerChangePassword({ body }, context('/api/v1/users/me/password')),
      );
      this.session.clear();
      await this.router.navigateByUrl('/auth/login');
    });
  }

  async logout(): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(
        this.api.identityControllerLogout(undefined, context('/api/v1/auth/session')),
      );
      this.session.clear();
      await this.router.navigateByUrl('/auth/login');
    });
  }

  reset(): void {
    this.commandState.set({ phase: 'idle' });
  }

  private async run(command: () => Promise<void>): Promise<void> {
    this.commandState.set({ phase: 'submitting' });
    try {
      await command();
      this.commandState.set({ phase: 'accepted' });
    } catch (error: unknown) {
      const safeError =
        error instanceof PasskeyCancelledError || error instanceof PasskeyUnsupportedError
          ? error
          : error instanceof ApiClientError
            ? error
            : error instanceof HttpErrorResponse
              ? parseApiError(error)
              : error instanceof Error
                ? error
                : parseApiError(error);
      this.commandState.set({ phase: 'failed', error: safeError });
    }
  }

  private async routeAfterAuthentication(): Promise<void> {
    await this.session.refresh();
    const user = this.session.currentUser();
    if (!user) throw new Error('SESSION_REFRESH_FAILED');
    if (!user.emailVerified) {
      await this.router.navigateByUrl('/auth/verification-sent');
      return;
    }
    if (user.entitlements.administration) {
      await this.router.navigateByUrl('/admin');
      return;
    }
    const state = await this.onboarding.load();
    await this.router.navigateByUrl(
      state.next === 'complete' ? '/app' : `/onboarding/${state.next}`,
    );
  }
}

function context(route: string): HttpContext {
  return new HttpContext().set(API_ROUTE_TEMPLATE, route);
}
