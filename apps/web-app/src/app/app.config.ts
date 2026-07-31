import { provideBrowserGlobalErrorListeners, type ApplicationConfig } from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { provideMymoneyMapIcons } from '@mymoneymap/web-design-system';
import { provideApiCore } from '@mymoneymap/web-core';
import { APP_ENVIRONMENT, provideAppI18n } from '@mymoneymap/web-shared';
import { environment } from '../environments/environment';
import { appRoutes } from './app.routes';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(appRoutes, withComponentInputBinding()),
    provideApiCore(),
    provideAppI18n(),
    provideMymoneyMapIcons(),
    { provide: APP_ENVIRONMENT, useValue: environment },
  ],
};
