import { HttpContext } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import type { PasskeySummaryResponseDto } from '@mymoneymap/generated-api-client/models/passkey-summary-response-dto';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import { API_ROUTE_TEMPLATE, parseApiError } from '@mymoneymap/web-core';
import type { ApiClientError } from '@mymoneymap/web-core';
import { firstValueFrom } from 'rxjs';
import { PasskeyBrowserAdapter } from './passkey-browser.adapter';

@Injectable({ providedIn: 'root' })
export class SecurityFacade {
  private readonly api = inject(IdentityService);
  private readonly browser = inject(PasskeyBrowserAdapter);
  private readonly itemsSignal = signal<readonly PasskeySummaryResponseDto[]>([]);
  private readonly pendingSignal = signal(false);
  private readonly errorSignal = signal<ApiClientError | Error | null>(null);

  readonly items = this.itemsSignal.asReadonly();
  readonly pending = this.pendingSignal.asReadonly();
  readonly error = this.errorSignal.asReadonly();

  async load(): Promise<void> {
    await this.execute(async () => {
      const result = await firstValueFrom(
        this.api.identityControllerListPasskeys(undefined, context('/api/v1/auth/passkeys')),
      );
      this.itemsSignal.set(result.items);
    });
  }

  async enroll(label: string): Promise<void> {
    await this.execute(async () => {
      const options = await firstValueFrom(
        this.api.identityControllerRegistrationOptions(
          undefined,
          context('/api/v1/auth/passkeys/registration-options'),
        ),
      );
      const credential = await this.browser.create(options);
      await firstValueFrom(
        this.api.identityControllerRegisterPasskey(
          { body: { label, credential } },
          context('/api/v1/auth/passkeys'),
        ),
      );
      await this.load();
    });
  }

  async remove(id: string): Promise<void> {
    await this.execute(async () => {
      await firstValueFrom(
        this.api.identityControllerDeletePasskey({ id }, context('/api/v1/auth/passkeys/{id}')),
      );
      this.itemsSignal.update((items) => items.filter((item) => item.id !== id));
    });
  }

  private async execute(action: () => Promise<void>): Promise<void> {
    this.pendingSignal.set(true);
    this.errorSignal.set(null);
    try {
      await action();
    } catch (error: unknown) {
      this.errorSignal.set(error instanceof Error ? error : parseApiError(error));
    } finally {
      this.pendingSignal.set(false);
    }
  }
}

function context(route: string): HttpContext {
  return new HttpContext().set(API_ROUTE_TEMPLATE, route);
}
