import { inject, Injectable } from '@angular/core';
import { HttpContext } from '@angular/common/http';
import { UsersAndSettingsService } from '@mymoneymap/generated-api-client/services/users-and-settings.service';
import { AppLanguageService } from '@mymoneymap/web-shared';
import { firstValueFrom } from 'rxjs';
import { API_ROUTE_TEMPLATE } from '../api/observability';
import { ApiClientError, parseApiError } from '../api/api-error';
import { SessionState } from './session-state';

@Injectable({ providedIn: 'root' })
export class SessionStore {
  private readonly users = inject(UsersAndSettingsService);
  private readonly language = inject(AppLanguageService);
  private readonly state = inject(SessionState);

  readonly status = this.state.status;
  readonly currentUser = this.state.currentUser;
  readonly authenticated = this.state.authenticated;

  bootstrap(): Promise<void> {
    return this.load();
  }

  refresh(): Promise<void> {
    return this.load();
  }

  clear(): void {
    this.state.markAnonymous();
  }

  private async load(): Promise<void> {
    this.state.markLoading();
    const context = new HttpContext().set(API_ROUTE_TEMPLATE, '/api/v1/users/me');
    try {
      const user = await firstValueFrom(this.users.usersControllerCurrentUser(undefined, context));
      this.state.markAuthenticated(user);
      this.language.setLanguage(user.desiredLanguage);
    } catch (error: unknown) {
      const parsed = error instanceof ApiClientError ? error : parseApiError(error);
      if (parsed.status === 401) {
        this.state.markAnonymous();
        return;
      }
      this.state.markUnavailable();
    }
  }
}
