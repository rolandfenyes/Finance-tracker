import { InjectionToken } from '@angular/core';

export interface AppEnvironment {
  readonly apiBasePath: '/api/v1';
  readonly production: boolean;
}

export const APP_ENVIRONMENT = new InjectionToken<AppEnvironment>('APP_ENVIRONMENT');
