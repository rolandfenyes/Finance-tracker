import { HttpContext } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import type { BudgetRulesResponseDto } from '@mymoneymap/generated-api-client/models/budget-rules-response-dto';
import type { CategoriesResponseDto } from '@mymoneymap/generated-api-client/models/categories-response-dto';
import type { CreateBasicIncomeDto } from '@mymoneymap/generated-api-client/models/create-basic-income-dto';
import type { CreateBudgetRuleDto } from '@mymoneymap/generated-api-client/models/create-budget-rule-dto';
import type { CreateCategoryDto } from '@mymoneymap/generated-api-client/models/create-category-dto';
import type { CurrencyCatalogueResponseDto } from '@mymoneymap/generated-api-client/models/currency-catalogue-response-dto';
import type { OnboardingResponseDto } from '@mymoneymap/generated-api-client/models/onboarding-response-dto';
import type { ThemePreferencesResponseDto } from '@mymoneymap/generated-api-client/models/theme-preferences-response-dto';
import type { UpdateThemeDto } from '@mymoneymap/generated-api-client/models/update-theme-dto';
import type { UserCurrenciesResponseDto } from '@mymoneymap/generated-api-client/models/user-currencies-response-dto';
import { BasicIncomeService } from '@mymoneymap/generated-api-client/services/basic-income.service';
import { BudgetingService } from '@mymoneymap/generated-api-client/services/budgeting.service';
import { CategoriesService } from '@mymoneymap/generated-api-client/services/categories.service';
import { CurrenciesService } from '@mymoneymap/generated-api-client/services/currencies.service';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import { UsersAndSettingsService } from '@mymoneymap/generated-api-client/services/users-and-settings.service';
import {
  API_ROUTE_TEMPLATE,
  ApiClientError,
  OnboardingPolicy,
  parseApiError,
  SessionStore,
} from '@mymoneymap/web-core';
import { ThemeService, type PaletteId } from '@mymoneymap/web-design-system';
import { firstValueFrom } from 'rxjs';

type ServerTheme = UpdateThemeDto['theme'];

const SERVER_THEME_TO_PALETTE: Readonly<Record<ServerTheme, PaletteId>> = {
  'polar-quartz': 'blue',
  'verdant-horizon': 'green',
  'celestial-tide': 'teal',
  'blush-nocturne': 'pink',
  'ember-vanguard': 'red',
  'lilac-eclipse': 'purple',
  'solaris-bloom': 'orange',
  'dune-mirage': 'indigo',
};

@Injectable({ providedIn: 'root' })
export class OnboardingFacade {
  private readonly users = inject(UsersAndSettingsService);
  private readonly identity = inject(IdentityService);
  private readonly budgeting = inject(BudgetingService);
  private readonly currenciesApi = inject(CurrenciesService);
  private readonly categoriesApi = inject(CategoriesService);
  private readonly incomes = inject(BasicIncomeService);
  private readonly policy = inject(OnboardingPolicy);
  private readonly session = inject(SessionStore);
  private readonly theme = inject(ThemeService);
  private readonly router = inject(Router);

  private readonly stateSignal = signal<OnboardingResponseDto | null>(null);
  private readonly pendingSignal = signal(false);
  private readonly errorSignal = signal<ApiClientError | null>(null);
  private readonly themeSignal = signal<ThemePreferencesResponseDto | null>(null);
  private readonly catalogueSignal = signal<CurrencyCatalogueResponseDto | null>(null);
  private readonly currenciesSignal = signal<UserCurrenciesResponseDto | null>(null);
  private readonly categoriesSignal = signal<CategoriesResponseDto | null>(null);

  readonly state = this.stateSignal.asReadonly();
  readonly pending = this.pendingSignal.asReadonly();
  readonly error = this.errorSignal.asReadonly();
  readonly themePreferences = this.themeSignal.asReadonly();
  readonly catalogue = this.catalogueSignal.asReadonly();
  readonly currencies = this.currenciesSignal.asReadonly();
  readonly categories = this.categoriesSignal.asReadonly();

  async loadAndRedirect(): Promise<void> {
    await this.execute(async () => {
      const state = await this.policy.load();
      this.stateSignal.set(state);
      await this.navigateFor(state);
    });
  }

  async refreshState(): Promise<void> {
    await this.execute(async () => this.stateSignal.set(await this.policy.load()));
  }

  async loadTheme(): Promise<void> {
    await this.execute(async () => {
      const result = await firstValueFrom(
        this.users.usersControllerTheme(undefined, context('/api/v1/users/me/preferences/theme')),
      );
      this.themeSignal.set(result);
      this.theme.setPalette(SERVER_THEME_TO_PALETTE[result.theme]);
    });
  }

  previewTheme(theme: ServerTheme): void {
    this.theme.setPalette(SERVER_THEME_TO_PALETTE[theme]);
  }

  async selectTheme(theme: ServerTheme): Promise<void> {
    await this.execute(async () => {
      const result = await firstValueFrom(
        this.users.usersControllerUpdateTheme(
          { body: { theme } },
          context('/api/v1/users/me/preferences/theme'),
        ),
      );
      this.themeSignal.set(result);
      this.theme.setPalette(SERVER_THEME_TO_PALETTE[result.theme]);
      await this.advance();
    });
  }

  async initializeRules(rules: CreateBudgetRuleDto[]): Promise<BudgetRulesResponseDto | null> {
    let result: BudgetRulesResponseDto | null = null;
    await this.execute(async () => {
      result = await firstValueFrom(
        this.budgeting.budgetingControllerInitialize(
          { body: { rules } },
          context('/api/v1/budget-rules'),
        ),
      );
      await this.advance();
    });
    return result;
  }

  async loadCurrencies(): Promise<void> {
    await this.execute(async () => {
      const [catalogue, memberships] = await Promise.all([
        firstValueFrom(
          this.currenciesApi.currencyControllerCatalogue(undefined, context('/api/v1/currencies')),
        ),
        firstValueFrom(
          this.currenciesApi.currencyControllerUserCurrencies(
            undefined,
            context('/api/v1/users/me/currencies'),
          ),
        ),
      ]);
      this.catalogueSignal.set(catalogue);
      this.currenciesSignal.set(memberships);
    });
  }

  async addCurrency(code: string): Promise<void> {
    await this.execute(async () => {
      const result = await firstValueFrom(
        this.currenciesApi.currencyControllerAdd(
          { body: { code } },
          context('/api/v1/users/me/currencies'),
        ),
      );
      this.currenciesSignal.set(result);
      await this.advance();
    });
  }

  async setMainCurrency(code: string): Promise<void> {
    await this.execute(async () => {
      const result = await firstValueFrom(
        this.currenciesApi.currencyControllerSetMain(
          { body: { code } },
          context('/api/v1/users/me/main-currency'),
        ),
      );
      this.currenciesSignal.set(result);
      await this.advance();
    });
  }

  async loadCategories(): Promise<void> {
    await this.execute(async () => {
      this.categoriesSignal.set(
        await firstValueFrom(
          this.categoriesApi.budgetingControllerCategories(
            undefined,
            context('/api/v1/categories'),
          ),
        ),
      );
    });
  }

  async createCategory(body: CreateCategoryDto): Promise<void> {
    await this.execute(async () => {
      this.categoriesSignal.set(
        await firstValueFrom(
          this.categoriesApi.budgetingControllerCreateCategory(
            { body },
            context('/api/v1/categories'),
          ),
        ),
      );
      await this.advance();
    });
  }

  async createIncome(body: CreateBasicIncomeDto): Promise<void> {
    await this.execute(async () => {
      await firstValueFrom(
        this.incomes.budgetingControllerCreateBasicIncome(
          { body },
          context('/api/v1/basic-incomes'),
        ),
      );
      await this.advance();
    });
  }

  async completeTutorial(): Promise<void> {
    await this.execute(async () => {
      const state = await firstValueFrom(
        this.users.usersControllerCompleteTutorial(
          { body: { tutorialCompleted: true } },
          context('/api/v1/users/me/onboarding'),
        ),
      );
      this.stateSignal.set(state);
      await this.navigateFor(state);
    });
  }

  async logout(): Promise<void> {
    await this.execute(async () => {
      await firstValueFrom(
        this.identity.identityControllerLogout(undefined, context('/api/v1/auth/session')),
      );
      this.session.clear();
      await this.router.navigateByUrl('/auth/login');
    });
  }

  private async advance(): Promise<void> {
    const state = await this.policy.load();
    this.stateSignal.set(state);
    await this.navigateFor(state);
  }

  private navigateFor(state: OnboardingResponseDto): Promise<boolean> {
    return this.router.navigateByUrl(
      state.next === 'complete' ? '/app' : `/onboarding/${state.next}`,
    );
  }

  private async execute(action: () => Promise<void>): Promise<void> {
    this.pendingSignal.set(true);
    this.errorSignal.set(null);
    try {
      await action();
    } catch (error: unknown) {
      this.errorSignal.set(error instanceof ApiClientError ? error : parseApiError(error));
    } finally {
      this.pendingSignal.set(false);
    }
  }
}

function context(route: string): HttpContext {
  return new HttpContext().set(API_ROUTE_TEMPLATE, route);
}
