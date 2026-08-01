/* eslint-disable @typescript-eslint/unbound-method -- Angular's stateless Validators are passed by design. */
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import type { OnInit } from '@angular/core';
import { FormArray, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatRadioModule } from '@angular/material/radio';
import { MatSelectModule } from '@angular/material/select';
import { RouterLink, RouterOutlet } from '@angular/router';
import type { UpdateThemeDto } from '@mymoneymap/generated-api-client/models/update-theme-dto';
import {
  InlineAlertComponent,
  PageHeaderComponent,
  ThemeService,
  DISPLAY_MODES,
  type DisplayMode,
} from '@mymoneymap/web-design-system';
import { TranslocoPipe } from '@jsverse/transloco';
import { OnboardingFacade } from './onboarding.facade';

const PAGE_IMPORTS = [
  ReactiveFormsModule,
  MatButtonModule,
  MatFormFieldModule,
  MatInputModule,
  MatRadioModule,
  MatSelectModule,
  PageHeaderComponent,
  TranslocoPipe,
] as const;

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [TranslocoPipe],
  selector: 'mmm-onboarding-progress',
  template: `
    <div
      class="onboarding-progress"
      [attr.aria-label]="'onboarding.shared.progressLabel' | transloco"
    >
      <span>{{ step() }}/6</span>
      <progress [value]="step()" max="6">{{ step() }}/6</progress>
    </div>
  `,
})
export class OnboardingProgressComponent {
  private readonly facade = inject(OnboardingFacade);
  readonly step = (): number => this.facade.state()?.currentStep ?? 0;
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MatButtonModule, OnboardingProgressComponent, RouterLink, RouterOutlet, TranslocoPipe],
  selector: 'mmm-onboarding-layout',
  template: `
    <main id="main-content" class="feature-shell onboarding-feature-shell">
      <header class="onboarding-topbar">
        <a
          class="feature-brand"
          routerLink="/onboarding"
          [attr.aria-label]="'onboarding.shared.homeLabel' | transloco"
        >
          <span aria-hidden="true">M</span><strong>MyMoneyMap</strong>
        </a>
        <mmm-onboarding-progress />
        <button mat-button type="button" (click)="logout()">
          {{ 'onboarding.shared.signOut' | transloco }}
        </button>
      </header>
      <section class="feature-panel"><router-outlet /></section>
    </main>
  `,
})
export class OnboardingLayoutComponent implements OnInit {
  protected readonly facade = inject(OnboardingFacade);
  ngOnInit(): void {
    void this.facade.refreshState();
  }
  protected logout(): void {
    void this.facade.logout();
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [InlineAlertComponent, TranslocoPipe],
  selector: 'mmm-onboarding-error',
  template: `
    @if (facade.error(); as error) {
      <mmm-inline-alert tone="danger">
        {{
          error.kind === 'rate-limit'
            ? ('onboarding.errors.rateLimit' | transloco)
            : ('onboarding.errors.generic' | transloco)
        }}
        @if (error.requestId) {
          <small>{{ error.requestId }}</small>
        }
      </mmm-inline-alert>
    }
  `,
})
export class OnboardingErrorComponent {
  protected readonly facade = inject(OnboardingFacade);
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [TranslocoPipe],
  selector: 'mmm-onboarding-entry',
  template: `<p role="status">{{ 'onboarding.shared.loading' | transloco }}</p>`,
})
export class OnboardingEntryComponent implements OnInit {
  private readonly facade = inject(OnboardingFacade);
  ngOnInit(): void {
    void this.facade.loadAndRedirect();
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, OnboardingErrorComponent],
  selector: 'mmm-theme-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.theme.title' | transloco"
      [description]="'onboarding.theme.description' | transloco"
    />
    <mmm-onboarding-error />
    <fieldset class="palette-grid">
      <legend>{{ 'onboarding.theme.paletteLegend' | transloco }}</legend>
      @for (theme of facade.themePreferences()?.supportedThemes ?? []; track theme) {
        <button
          class="palette-option"
          type="button"
          [attr.aria-pressed]="selectedTheme === theme"
          (mouseenter)="facade.previewTheme(theme)"
          (focus)="facade.previewTheme(theme)"
          (click)="selectedTheme = theme"
        >
          <span class="palette-swatch" aria-hidden="true"></span>
          {{ 'onboarding.theme.options.' + theme | transloco }}
        </button>
      }
    </fieldset>
    <fieldset class="mode-selector">
      <legend>{{ 'onboarding.theme.modeLegend' | transloco }}</legend>
      <mat-radio-group [value]="theme.mode()" (change)="setMode($event.value)">
        @for (mode of modes; track mode) {
          <mat-radio-button [value]="mode">{{
            'onboarding.theme.modes.' + mode | transloco
          }}</mat-radio-button>
        }
      </mat-radio-group>
    </fieldset>
    <button
      mat-flat-button
      type="button"
      [disabled]="!selectedTheme || facade.pending()"
      (click)="save()"
    >
      {{ 'state.continue' | transloco }}
    </button>
  `,
})
export class ThemeStepComponent implements OnInit {
  protected readonly facade = inject(OnboardingFacade);
  protected readonly theme = inject(ThemeService);
  protected readonly modes = DISPLAY_MODES;
  protected selectedTheme: UpdateThemeDto['theme'] | null = null;

  ngOnInit(): void {
    void this.load();
  }
  private async load(): Promise<void> {
    await this.facade.loadTheme();
    this.selectedTheme = this.facade.themePreferences()?.theme ?? null;
  }
  protected setMode(mode: DisplayMode): void {
    this.theme.setMode(mode);
  }
  protected save(): void {
    if (this.selectedTheme) void this.facade.selectTheme(this.selectedTheme);
  }
}

function ruleGroup(): FormGroup<{
  label: FormControl<string>;
  percent: FormControl<string>;
  targetHint: FormControl<string>;
}> {
  return new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    percent: new FormControl('', {
      nonNullable: true,
      validators: [
        Validators.required,
        Validators.pattern(/^(?:0|[1-9]\d?)(?:\.\d{1,4})?$|^100(?:\.0{1,4})?$/),
      ],
    }),
    targetHint: new FormControl('', { nonNullable: true, validators: [Validators.maxLength(500)] }),
  });
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, OnboardingErrorComponent],
  selector: 'mmm-rules-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.rules.title' | transloco"
      [description]="'onboarding.rules.description' | transloco"
    />
    <mmm-onboarding-error />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()">
      <div class="feature-stack">
        @for (rule of rules.controls; track $index) {
          <fieldset class="rule-row" [formGroup]="rule">
            <legend>{{ 'onboarding.rules.rule' | transloco: { number: $index + 1 } }}</legend>
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'onboarding.rules.label' | transloco }}</mat-label
              ><input matInput formControlName="label"
            /></mat-form-field>
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'onboarding.rules.percent' | transloco }}</mat-label
              ><input matInput inputmode="decimal" formControlName="percent"
            /></mat-form-field>
            <mat-form-field appearance="outline"
              ><mat-label>{{ 'onboarding.rules.hint' | transloco }}</mat-label
              ><input matInput formControlName="targetHint"
            /></mat-form-field>
            @if (rules.length > 1) {
              <button mat-button type="button" (click)="rules.removeAt($index)">
                {{ 'onboarding.rules.remove' | transloco }}
              </button>
            }
          </fieldset>
        }
      </div>
      <button mat-stroked-button type="button" (click)="rules.push(createRule())">
        {{ 'onboarding.rules.add' | transloco }}
      </button>
      <button mat-flat-button type="submit" [disabled]="rules.invalid || facade.pending()">
        {{ 'state.continue' | transloco }}
      </button>
    </form>
  `,
})
export class RulesStepComponent {
  protected readonly facade = inject(OnboardingFacade);
  protected readonly rules = new FormArray([ruleGroup()]);
  protected readonly form = new FormGroup({ rules: this.rules });
  protected readonly createRule = ruleGroup;
  protected submit(): void {
    if (this.rules.invalid) return this.rules.markAllAsTouched();
    const values = this.rules.getRawValue().map((rule) => ({
      label: rule.label,
      percent: rule.percent,
      targetHint: rule.targetHint || undefined,
    }));
    void this.facade.initializeRules(values);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, OnboardingErrorComponent],
  selector: 'mmm-currencies-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.currencies.title' | transloco"
      [description]="'onboarding.currencies.description' | transloco"
    />
    <mmm-onboarding-error />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="add()">
      <mat-form-field appearance="outline">
        <mat-label>{{ 'onboarding.currencies.currency' | transloco }}</mat-label>
        <mat-select formControlName="code">
          @for (currency of facade.catalogue()?.items ?? []; track currency.code) {
            <mat-option [value]="currency.code"
              >{{ currency.code }} — {{ currency.name }}</mat-option
            >
          }
        </mat-select>
      </mat-form-field>
      <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
        {{ 'onboarding.currencies.add' | transloco }}
      </button>
    </form>
    <ul class="selection-list">
      @for (currency of facade.currencies()?.items ?? []; track currency.code) {
        <li>
          <span>{{ currency.code }} — {{ currency.name }}</span>
          @if (currency.isMain) {
            <strong>{{ 'onboarding.currencies.main' | transloco }}</strong>
          } @else {
            <button mat-button type="button" (click)="setMain(currency.code)">
              {{ 'onboarding.currencies.makeMain' | transloco }}
            </button>
          }
        </li>
      }
    </ul>
  `,
})
export class CurrenciesStepComponent implements OnInit {
  protected readonly facade = inject(OnboardingFacade);
  protected readonly form = new FormGroup({
    code: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });
  ngOnInit(): void {
    void this.facade.loadCurrencies();
  }
  protected add(): void {
    if (this.form.valid) void this.facade.addCurrency(this.form.getRawValue().code);
  }
  protected setMain(code: string): void {
    void this.facade.setMainCurrency(code);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, OnboardingErrorComponent],
  selector: 'mmm-categories-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.categories.title' | transloco"
      [description]="'onboarding.categories.description' | transloco"
    />
    <mmm-onboarding-error />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()">
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.categories.label' | transloco }}</mat-label
        ><input matInput formControlName="label"
      /></mat-form-field>
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.categories.kind' | transloco }}</mat-label
        ><mat-select formControlName="kind"
          ><mat-option value="spending">{{
            'onboarding.categories.spending' | transloco
          }}</mat-option
          ><mat-option value="income">{{
            'onboarding.categories.income' | transloco
          }}</mat-option></mat-select
        ></mat-form-field
      >
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.categories.color' | transloco }}</mat-label
        ><input matInput type="color" formControlName="color"
      /></mat-form-field>
      <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
        {{ 'state.continue' | transloco }}
      </button>
    </form>
  `,
})
export class CategoriesStepComponent implements OnInit {
  protected readonly facade = inject(OnboardingFacade);
  protected readonly form = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    kind: new FormControl<'income' | 'spending'>('spending', { nonNullable: true }),
    color: new FormControl('#0f766e', {
      nonNullable: true,
      validators: [Validators.pattern(/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/)],
    }),
  });
  ngOnInit(): void {
    void this.facade.loadCategories();
  }
  protected submit(): void {
    if (this.form.valid) void this.facade.createCategory(this.form.getRawValue());
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, InlineAlertComponent, OnboardingErrorComponent],
  selector: 'mmm-income-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.income.title' | transloco"
      [description]="'onboarding.income.description' | transloco"
    />
    <mmm-inline-alert tone="information">{{
      'onboarding.income.planningOnly' | transloco
    }}</mmm-inline-alert>
    <mmm-onboarding-error />
    <form class="feature-form" [formGroup]="form" (ngSubmit)="submit()">
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.income.label' | transloco }}</mat-label
        ><input matInput formControlName="label"
      /></mat-form-field>
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.income.amount' | transloco }}</mat-label
        ><input matInput inputmode="decimal" formControlName="amount"
      /></mat-form-field>
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.income.currency' | transloco }}</mat-label
        ><mat-select formControlName="currency">
          @for (currency of facade.currencies()?.items ?? []; track currency.code) {
            <mat-option [value]="currency.code"
              >{{ currency.code }} — {{ currency.name }}</mat-option
            >
          }
        </mat-select></mat-form-field
      >
      <mat-form-field appearance="outline"
        ><mat-label>{{ 'onboarding.income.validFrom' | transloco }}</mat-label
        ><input matInput type="date" formControlName="validFrom"
      /></mat-form-field>
      <button mat-flat-button type="submit" [disabled]="form.invalid || facade.pending()">
        {{ 'state.continue' | transloco }}
      </button>
    </form>
  `,
})
export class IncomeStepComponent implements OnInit {
  protected readonly facade = inject(OnboardingFacade);
  protected readonly form = new FormGroup({
    label: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(120)],
    }),
    amount: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/)],
    }),
    currency: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.pattern(/^[A-Z]{3}$/)],
    }),
    validFrom: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });
  ngOnInit(): void {
    void this.loadCurrencies();
  }
  protected submit(): void {
    if (this.form.valid) void this.facade.createIncome(this.form.getRawValue());
  }
  private async loadCurrencies(): Promise<void> {
    await this.facade.loadCurrencies();
    const mainCurrency = this.facade.currencies()?.mainCurrency;
    if (mainCurrency) this.form.controls.currency.setValue(mainCurrency);
  }
}

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [...PAGE_IMPORTS, OnboardingErrorComponent],
  selector: 'mmm-tutorial-step',
  template: `
    <mmm-page-header
      [title]="'onboarding.tutorial.title' | transloco"
      [description]="'onboarding.tutorial.description' | transloco"
    />
    <mmm-onboarding-error />
    <ol class="tutorial-list">
      <li>
        <strong>{{ 'onboarding.tutorial.postedTitle' | transloco }}</strong
        ><span>{{ 'onboarding.tutorial.postedBody' | transloco }}</span>
      </li>
      <li>
        <strong>{{ 'onboarding.tutorial.forecastTitle' | transloco }}</strong
        ><span>{{ 'onboarding.tutorial.forecastBody' | transloco }}</span>
      </li>
      <li>
        <strong>{{ 'onboarding.tutorial.historyTitle' | transloco }}</strong
        ><span>{{ 'onboarding.tutorial.historyBody' | transloco }}</span>
      </li>
    </ol>
    <button mat-flat-button type="button" [disabled]="facade.pending()" (click)="complete()">
      {{ 'onboarding.tutorial.complete' | transloco }}
    </button>
  `,
})
export class TutorialStepComponent {
  protected readonly facade = inject(OnboardingFacade);
  protected complete(): void {
    void this.facade.completeTutorial();
  }
}
