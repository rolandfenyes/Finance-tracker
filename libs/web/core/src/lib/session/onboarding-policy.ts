import { HttpContext } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { OnboardingResponseDto } from '@mymoneymap/generated-api-client/models/onboarding-response-dto';
import { UsersAndSettingsService } from '@mymoneymap/generated-api-client/services/users-and-settings.service';
import { firstValueFrom } from 'rxjs';
import { API_ROUTE_TEMPLATE } from '../api/observability';

@Injectable({ providedIn: 'root' })
export class OnboardingPolicy {
  private readonly users = inject(UsersAndSettingsService);

  load(): Promise<OnboardingResponseDto> {
    const context = new HttpContext().set(API_ROUTE_TEMPLATE, '/api/v1/users/me/onboarding');
    return firstValueFrom(this.users.usersControllerOnboarding(undefined, context));
  }
}
