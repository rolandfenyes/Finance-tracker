import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { BasicIncomeService } from '@mymoneymap/generated-api-client/services/basic-income.service';
import { BudgetingService } from '@mymoneymap/generated-api-client/services/budgeting.service';
import { CategoriesService } from '@mymoneymap/generated-api-client/services/categories.service';
import { CurrenciesService } from '@mymoneymap/generated-api-client/services/currencies.service';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import { UsersAndSettingsService } from '@mymoneymap/generated-api-client/services/users-and-settings.service';
import type { OnboardingResponseDto } from '@mymoneymap/generated-api-client/models/onboarding-response-dto';
import { OnboardingPolicy, SessionStore } from '@mymoneymap/web-core';
import { ThemeService } from '@mymoneymap/web-design-system';
import { of, throwError } from 'rxjs';
import { OnboardingFacade } from './onboarding.facade';

describe('OnboardingFacade', () => {
  const users = { usersControllerTheme: vi.fn(), usersControllerUpdateTheme: vi.fn() };
  const incomes = { budgetingControllerCreateBasicIncome: vi.fn() };
  const policy = { load: vi.fn() };
  const router = { navigateByUrl: vi.fn() };
  const theme = { setPalette: vi.fn() };

  beforeEach(() => {
    vi.clearAllMocks();
    router.navigateByUrl.mockResolvedValue(true);
    TestBed.configureTestingModule({
      providers: [
        OnboardingFacade,
        { provide: UsersAndSettingsService, useValue: users },
        { provide: IdentityService, useValue: {} },
        { provide: BudgetingService, useValue: {} },
        { provide: CurrenciesService, useValue: {} },
        { provide: CategoriesService, useValue: {} },
        { provide: BasicIncomeService, useValue: incomes },
        { provide: OnboardingPolicy, useValue: policy },
        { provide: SessionStore, useValue: {} },
        { provide: ThemeService, useValue: theme },
        { provide: Router, useValue: router },
      ],
    });
  });

  it('routes only from the server-returned next value', async () => {
    policy.load.mockResolvedValue(state('categories', 4));
    const facade = TestBed.inject(OnboardingFacade);

    await facade.loadAndRedirect();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/onboarding/categories');
    expect(facade.state()?.currentStep).toBe(4);
  });

  it('maps approved server palette identifiers while persisting the unchanged identifier', async () => {
    users.usersControllerUpdateTheme.mockReturnValue(
      of({ theme: 'lilac-eclipse', supportedThemes: ['lilac-eclipse'] }),
    );
    policy.load.mockResolvedValue(state('rules', 2));
    const facade = TestBed.inject(OnboardingFacade);

    await facade.selectTheme('lilac-eclipse');

    expect(users.usersControllerUpdateTheme).toHaveBeenCalledWith(
      { body: { theme: 'lilac-eclipse' } },
      expect.anything(),
    );
    expect(theme.setPalette).toHaveBeenCalledWith('purple');
    expect(router.navigateByUrl).toHaveBeenCalledWith('/onboarding/rules');
  });

  it('creates planning-only income through BasicIncomeService without transaction aggregation', async () => {
    incomes.budgetingControllerCreateBasicIncome.mockReturnValue(of({ id: 'synthetic-income' }));
    policy.load.mockResolvedValue(state('tutorial', 6));
    const facade = TestBed.inject(OnboardingFacade);
    const body = {
      amount: '1234.5600',
      currency: 'EUR',
      label: 'Synthetic baseline',
      validFrom: '2026-01-01',
    };

    await facade.createIncome(body);

    expect(incomes.budgetingControllerCreateBasicIncome).toHaveBeenCalledWith(
      { body },
      expect.anything(),
    );
    expect(router.navigateByUrl).toHaveBeenCalledWith('/onboarding/tutorial');
  });

  it('retains quota and validation failures as typed API state without inferring progress', async () => {
    users.usersControllerUpdateTheme.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            error: { error: { code: 'VALIDATION_FAILED' } },
          }),
      ),
    );
    const facade = TestBed.inject(OnboardingFacade);

    await facade.selectTheme('polar-quartz');

    expect(facade.error()).toMatchObject({ kind: 'validation', status: 422 });
    expect(policy.load).not.toHaveBeenCalled();
    expect(router.navigateByUrl).not.toHaveBeenCalled();
  });
});

function state(
  next: 'rules' | 'categories' | 'tutorial',
  currentStep: number,
): OnboardingResponseDto {
  return {
    currentStep,
    next,
    onboardingComplete: false,
    tutorialCompleted: false,
    tutorialRequired: true,
  } as const;
}
