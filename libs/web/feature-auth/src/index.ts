import type { Routes } from '@angular/router';
import { signedOutGuard } from '@mymoneymap/web-core';
import {
  AuthLayoutComponent,
  LoginPageComponent,
  PasskeyPageComponent,
  RegisterPageComponent,
  VerificationSentPageComponent,
  VerifyEmailPageComponent,
} from './lib/auth-pages';

export { PasskeyBrowserAdapter } from './lib/passkey-browser.adapter';
export { SecurityPanelsComponent } from './lib/auth-pages';

export const AUTH_ROUTES: Routes = [
  {
    path: '',
    component: AuthLayoutComponent,
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'login' },
      { path: 'login', canMatch: [signedOutGuard], component: LoginPageComponent },
      { path: 'register', canMatch: [signedOutGuard], component: RegisterPageComponent },
      { path: 'passkey', canMatch: [signedOutGuard], component: PasskeyPageComponent },
      { path: 'verify-email', component: VerifyEmailPageComponent },
      { path: 'verification-sent', component: VerificationSentPageComponent },
    ],
  },
];
