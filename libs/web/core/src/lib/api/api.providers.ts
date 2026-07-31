import { provideHttpClient, withInterceptors } from '@angular/common/http';
import {
  inject,
  makeEnvironmentProviders,
  provideAppInitializer,
  type EnvironmentProviders,
} from '@angular/core';
import { provideApiConfiguration } from '@mymoneymap/generated-api-client/api-configuration';
import { apiObservabilityInterceptor, apiSessionInterceptor } from './api.interceptors';
import { SessionStore } from '../session/session.store';

export function provideApiCore(): EnvironmentProviders {
  return makeEnvironmentProviders([
    provideHttpClient(withInterceptors([apiSessionInterceptor, apiObservabilityInterceptor])),
    provideApiConfiguration(''),
    provideAppInitializer(() => inject(SessionStore).bootstrap()),
  ]);
}
