/* eslint-disable @typescript-eslint/unbound-method -- Angular's stateless Validators are passed by design. */
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import type { OnInit } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { ActivatedRoute, Router, RouterLink, RouterOutlet } from '@angular/router';
import {
  FormErrorSummaryComponent,
  InlineAlertComponent,
  PageHeaderComponent,
} from '@mymoneymap/web-design-system';
import { TranslocoPipe, TranslocoService } from '@jsverse/transloco';
import { ApiClientError } from '@mymoneymap/web-core';
import { IdentityFacade, type IdentityCommandState } from './identity.facade';
import { PasskeyCancelledError, PasskeyUnsupportedError } from './passkey-browser.adapter';
import { SecurityFacade } from './security.facade';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [InlineAlertComponent, TranslocoPipe],
  selector: 'mmm-command-error',
  template: `
    @if (messageKey()) {
      <mmm-inline-alert tone="danger">
        {{ messageKey() | transloco }}
        @if (retryAfter()) {
          <span> {{ 'identity.errors.retryAfter' | transloco: { seconds: retryAfter() } }}</span>
        }
      </mmm-inline-alert>
    }
  `,
})
export class CommandErrorComponent {
  readonly state = input.required<IdentityCommandState>();

  protected messageKey(): string {
    const state = this.state();
    if (state.phase !== 'failed') return '';
    if (state.error instanceof PasskeyUnsupportedError) return 'identity.errors.passkeyUnsupported';
    if (state.error instanceof PasskeyCancelledError) return 'identity.errors.passkeyCancelled';
    if (state.error instanceof ApiClientError && state.error.kind === 'rate-limit')
      return 'identity.errors.rateLimit';
    if (state.error instanceof ApiClientError && state.error.kind === 'validation')
      return 'identity.errors.validation';
    return 'identity.errors.generic';
  }

  protected retryAfter(): number | null {
    const state = this.state();
    return state.phase === 'failed' && state.error instanceof ApiClientError
      ? state.error.retryAfterSeconds
      : null;
  }
}

const FORM_IMPORTS = [
  ReactiveFormsModule,
  MatButtonModule,
  MatCheckboxModule,
  MatFormFieldModule,
  MatIconModule,
  MatInputModule,
] as const;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, RouterOutlet, TranslocoPipe],
  selector: 'mmm-auth-layout',
  template: `
    <main id="main-content" class="feature-shell auth-feature-shell">
      <a
        class="feature-brand"
        routerLink="/auth/login"
        [attr.aria-label]="'identity.shared.homeLabel' | transloco"
      >
        <span aria-hidden="true">M</span><strong>MyMoneyMap</strong>
      </a>
      <section class="feature-panel"><router-outlet /></section>
      <p class="feature-trust">{{ 'identity.shared.trust' | transloco }}</p>
    </main>
  `,
})
export class AuthLayoutComponent {}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
  ],
  selector: 'mmm-password-field',
  template: `
    <mat-form-field appearance="outline">
      <mat-label>{{ label() }}</mat-label>
      <input
        matInput
        [id]="id()"
        [formControl]="control()"
        [type]="visible() ? 'text' : 'password'"
        autocomplete="current-password"
      />
      <button
        mat-icon-button
        matSuffix
        type="button"
        [attr.aria-label]="toggleLabel()"
        (click)="visible.update((value) => !value)"
      >
        <mat-icon>{{ visible() ? 'visibility_off' : 'visibility' }}</mat-icon>
      </button>
      <mat-hint>{{ hint() }}</mat-hint>
    </mat-form-field>
  `,
})
export class PasswordFieldComponent {
  readonly control = input.required<FormControl<string>>();
  readonly id = input('password');
  readonly label = input.required<string>();
  readonly hint = input('');
  readonly toggleLabel = input.required<string>();
  protected readonly visible = signal(false);
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...FORM_IMPORTS, CommandErrorComponent, PageHeaderComponent, RouterLink, TranslocoPipe],
  selector: 'mmm-login-page',
  template: `
    <mmm-page-header
      [eyebrow]="'identity.login.eyebrow' | transloco"
      [title]="'identity.login.title' | transloco"
      [description]="'identity.login.description' | transloco"
    />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()" novalidate>
      <mmm-command-error [state]="facade.state()" />
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.email' | transloco }}</mat-label>
        <input
          id="login-email"
          matInput
          type="email"
          autocomplete="email"
          formControlName="email"
        />
      </mat-form-field>
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.password' | transloco }}</mat-label>
        <input
          id="login-password"
          matInput
          type="password"
          autocomplete="current-password"
          formControlName="password"
        />
      </mat-form-field>
      <mat-checkbox formControlName="remember">{{
        'identity.login.remember' | transloco
      }}</mat-checkbox>
      <button mat-flat-button type="submit" [disabled]="pending()">
        {{ 'identity.login.submit' | transloco }}
      </button>
    </form>
    <div class="feature-actions">
      <a mat-stroked-button routerLink="/auth/passkey">{{
        'identity.login.passkey' | transloco
      }}</a>
      <a mat-button routerLink="/auth/register">{{ 'identity.login.register' | transloco }}</a>
    </div>
  `,
})
export class LoginPageComponent {
  protected readonly facade = inject(IdentityFacade);
  protected readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    remember: new FormControl(false, { nonNullable: true }),
  });
  protected readonly pending = (): boolean => this.facade.state().phase === 'submitting';

  protected submit(): void {
    if (this.form.invalid) return this.form.markAllAsTouched();
    void this.facade.login(this.form.getRawValue());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ...FORM_IMPORTS,
    CommandErrorComponent,
    FormErrorSummaryComponent,
    PageHeaderComponent,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-register-page',
  template: `
    <mmm-page-header
      [eyebrow]="'identity.register.eyebrow' | transloco"
      [title]="'identity.register.title' | transloco"
      [description]="'identity.register.description' | transloco"
    />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()" novalidate>
      <mmm-command-error [state]="facade.state()" />
      <mmm-form-error-summary
        [title]="'identity.errors.formTitle' | transloco"
        [items]="submitted() && form.invalid ? formErrors() : []"
      />
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.email' | transloco }}</mat-label>
        <input
          id="register-email"
          matInput
          type="email"
          autocomplete="email"
          formControlName="email"
        />
      </mat-form-field>
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.fullName' | transloco }}</mat-label>
        <input id="register-name" matInput autocomplete="name" formControlName="fullName" />
      </mat-form-field>
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.dateOfBirth' | transloco }}</mat-label>
        <input
          id="register-birth-date"
          matInput
          type="date"
          autocomplete="bday"
          formControlName="dateOfBirth"
        />
      </mat-form-field>
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.password' | transloco }}</mat-label>
        <input
          id="register-password"
          matInput
          type="password"
          autocomplete="new-password"
          formControlName="password"
        />
        <mat-hint>{{ 'identity.register.passwordHint' | transloco }}</mat-hint>
      </mat-form-field>
      <button mat-flat-button type="submit" [disabled]="pending()">
        {{ 'identity.register.submit' | transloco }}
      </button>
    </form>
    <a mat-button routerLink="/auth/login">{{ 'identity.register.login' | transloco }}</a>
  `,
})
export class RegisterPageComponent {
  protected readonly facade = inject(IdentityFacade);
  private readonly transloco = inject(TranslocoService);
  protected readonly submitted = signal(false);
  protected readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    fullName: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(200)],
    }),
    dateOfBirth: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    password: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(8), Validators.maxLength(200)],
    }),
  });
  protected readonly pending = (): boolean => this.facade.state().phase === 'submitting';

  protected submit(): void {
    this.submitted.set(true);
    if (this.form.invalid) return this.form.markAllAsTouched();
    void this.facade.register(this.form.getRawValue());
  }

  protected formErrors(): readonly { field: string; message: string }[] {
    return [
      { field: 'register-email', message: this.transloco.translate('identity.errors.emailField') },
      { field: 'register-name', message: this.transloco.translate('identity.errors.nameField') },
      {
        field: 'register-birth-date',
        message: this.transloco.translate('identity.errors.birthDateField'),
      },
      {
        field: 'register-password',
        message: this.transloco.translate('identity.errors.passwordField'),
      },
    ];
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ...FORM_IMPORTS,
    CommandErrorComponent,
    InlineAlertComponent,
    PageHeaderComponent,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-verification-sent-page',
  template: `
    <mmm-page-header
      [eyebrow]="'identity.verification.eyebrow' | transloco"
      [title]="'identity.verification.sentTitle' | transloco"
      [description]="'identity.verification.sentDescription' | transloco"
    />
    @if (facade.state().phase === 'accepted') {
      <mmm-inline-alert tone="success">{{
        'identity.verification.accepted' | transloco
      }}</mmm-inline-alert>
    }
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()">
      <mmm-command-error [state]="facade.state()" />
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.fields.email' | transloco }}</mat-label>
        <input
          id="verification-email"
          matInput
          type="email"
          autocomplete="email"
          formControlName="email"
        />
      </mat-form-field>
      <button mat-flat-button type="submit" [disabled]="facade.state().phase === 'submitting'">
        {{ 'identity.verification.resend' | transloco }}
      </button>
    </form>
    <a mat-button routerLink="/auth/login">{{ 'identity.verification.login' | transloco }}</a>
  `,
})
export class VerificationSentPageComponent {
  protected readonly facade = inject(IdentityFacade);
  protected readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
  });
  protected submit(): void {
    if (this.form.invalid) return this.form.markAllAsTouched();
    void this.facade.requestVerification(this.form.getRawValue());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    MatButtonModule,
    CommandErrorComponent,
    InlineAlertComponent,
    PageHeaderComponent,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-verify-email-page',
  template: `
    <mmm-page-header
      [eyebrow]="'identity.verification.eyebrow' | transloco"
      [title]="'identity.verification.verifyTitle' | transloco"
      [description]="'identity.verification.verifyDescription' | transloco"
    />
    @if (!tokenFound) {
      <mmm-inline-alert tone="danger">{{
        'identity.verification.missingToken' | transloco
      }}</mmm-inline-alert>
    } @else if (facade.state().phase === 'submitting') {
      <p role="status">{{ 'identity.verification.verifying' | transloco }}</p>
    } @else {
      <mmm-command-error [state]="facade.state()" />
    }
    <a mat-button routerLink="/auth/verification-sent">{{
      'identity.verification.resend' | transloco
    }}</a>
  `,
})
export class VerifyEmailPageComponent implements OnInit {
  protected readonly facade = inject(IdentityFacade);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  protected tokenFound = false;

  ngOnInit(): void {
    const token = this.route.snapshot.queryParamMap.get('token');
    this.tokenFound = Boolean(token);
    if (token) {
      void this.router
        .navigateByUrl('/auth/verify-email', { replaceUrl: true })
        .then(() => this.facade.verify(token));
    }
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ...FORM_IMPORTS,
    CommandErrorComponent,
    InlineAlertComponent,
    PageHeaderComponent,
    RouterLink,
    TranslocoPipe,
  ],
  selector: 'mmm-passkey-page',
  template: `
    <mmm-page-header
      [eyebrow]="'identity.passkey.eyebrow' | transloco"
      [title]="'identity.passkey.title' | transloco"
      [description]="'identity.passkey.description' | transloco"
    />
    @if (!facade.passkeySupported) {
      <mmm-inline-alert tone="warning">{{
        'identity.passkey.unsupported' | transloco
      }}</mmm-inline-alert>
    }
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()">
      <mmm-command-error [state]="facade.state()" />
      <mat-form-field appearance="outline">
        <mat-label>{{ 'identity.passkey.optionalEmail' | transloco }}</mat-label>
        <input
          id="passkey-email"
          matInput
          type="email"
          autocomplete="email webauthn"
          formControlName="email"
        />
      </mat-form-field>
      <mat-checkbox formControlName="remember">{{
        'identity.login.remember' | transloco
      }}</mat-checkbox>
      <button
        mat-flat-button
        type="submit"
        [disabled]="!facade.passkeySupported || facade.state().phase === 'submitting'"
      >
        {{ 'identity.passkey.submit' | transloco }}
      </button>
    </form>
    <a mat-button routerLink="/auth/login">{{ 'identity.passkey.passwordFallback' | transloco }}</a>
  `,
})
export class PasskeyPageComponent {
  protected readonly facade = inject(IdentityFacade);
  protected readonly form = new FormGroup({
    email: new FormControl('', { nonNullable: true, validators: [Validators.email] }),
    remember: new FormControl(false, { nonNullable: true }),
  });
  protected submit(): void {
    if (this.form.invalid) return this.form.markAllAsTouched();
    const value = this.form.getRawValue();
    void this.facade.passkeyLogin(value.email, value.remember);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    TranslocoPipe,
  ],
  selector: 'mmm-security-panels',
  template: `
    <section class="feature-stack" aria-labelledby="password-heading">
      <h2 id="password-heading">{{ 'identity.security.passwordTitle' | transloco }}</h2>
      <form class="feature-form" [formGroup]="passwordForm" (ngSubmit)="changePassword()">
        <mat-form-field appearance="outline">
          <mat-label>{{ 'identity.security.currentPassword' | transloco }}</mat-label>
          <input
            matInput
            type="password"
            autocomplete="current-password"
            formControlName="currentPassword"
          />
        </mat-form-field>
        <mat-form-field appearance="outline">
          <mat-label>{{ 'identity.security.newPassword' | transloco }}</mat-label>
          <input
            matInput
            type="password"
            autocomplete="new-password"
            formControlName="newPassword"
          />
        </mat-form-field>
        <button mat-flat-button type="submit">
          {{ 'identity.security.changePassword' | transloco }}
        </button>
      </form>
    </section>
    <section class="feature-stack" aria-labelledby="passkeys-heading">
      <h2 id="passkeys-heading">{{ 'identity.security.passkeysTitle' | transloco }}</h2>
      <form
        class="feature-form feature-form-inline"
        [formGroup]="passkeyForm"
        (ngSubmit)="enroll()"
      >
        <mat-form-field appearance="outline">
          <mat-label>{{ 'identity.security.passkeyLabel' | transloco }}</mat-label>
          <input matInput formControlName="label" />
        </mat-form-field>
        <button mat-flat-button type="submit" [disabled]="security.pending()">
          {{ 'identity.security.addPasskey' | transloco }}
        </button>
      </form>
      <ul class="security-list">
        @for (passkey of security.items(); track passkey.id) {
          <li>
            <span
              ><strong>{{ passkey.label }}</strong
              ><small>{{ passkey.createdAt }}</small></span
            >
            <button mat-button type="button" (click)="remove(passkey.id)">
              {{ 'identity.security.deletePasskey' | transloco }}
            </button>
          </li>
        } @empty {
          <li>{{ 'identity.security.noPasskeys' | transloco }}</li>
        }
      </ul>
    </section>
  `,
})
export class SecurityPanelsComponent implements OnInit {
  protected readonly identity = inject(IdentityFacade);
  protected readonly security = inject(SecurityFacade);
  protected readonly passwordForm = new FormGroup({
    currentPassword: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    newPassword: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(8)],
    }),
  });
  protected readonly passkeyForm = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(100)],
    }),
  });

  ngOnInit(): void {
    void this.security.load();
  }
  protected changePassword(): void {
    if (this.passwordForm.valid) void this.identity.changePassword(this.passwordForm.getRawValue());
  }
  protected enroll(): void {
    if (this.passkeyForm.valid) void this.security.enroll(this.passkeyForm.getRawValue().label);
  }
  protected remove(id: string): void {
    void this.security.remove(id);
  }
}
