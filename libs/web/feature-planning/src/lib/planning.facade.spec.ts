import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { BasicIncomeService } from '@mymoneymap/generated-api-client/services/basic-income.service';
import { BudgetingService } from '@mymoneymap/generated-api-client/services/budgeting.service';
import { CategoriesService } from '@mymoneymap/generated-api-client/services/categories.service';
import { CurrenciesService } from '@mymoneymap/generated-api-client/services/currencies.service';
import { RecurrenceService } from '@mymoneymap/generated-api-client/services/recurrence.service';
import { SessionStore } from '@mymoneymap/web-core';
import { of } from 'rxjs';
import { PlanningFacade } from './planning.facade';

describe('PlanningFacade boundaries', () => {
  const budget = {
    items: [
      {
        id: 'rule-1',
        label: 'Needs',
        percent: '101.0001',
        targetHint: null,
        assignedCategoryIds: [],
        createdAt: '2026-01-01T00:00:00.000Z',
        updatedAt: '2026-01-01T00:00:00.000Z',
        plan: {
          currency: 'HUF',
          status: 'available' as const,
          plannedAmount: '1000.01',
          assignedCategorySpending: '1250.02',
          signedVariance: '-250.01',
        },
      },
    ],
    allocation: {
      status: 'over_allocated' as const,
      totalPercent: '101.0001',
      overAllocatedBy: '1.0001',
    },
    period: {
      month: '2026-02',
      currency: 'HUF',
      forecastIncomeStatus: 'available' as const,
      forecastIncome: '9900.03',
    },
  };
  const budgetApi = {
    budgetingControllerRules: vi.fn(() => of(budget)),
    budgetingControllerCreateRule: vi.fn(() => of(budget)),
    budgetingControllerUpdateRule: vi.fn(() => of(budget)),
    budgetingControllerDeleteRule: vi.fn(() => of(undefined)),
  };
  const categoriesApi = {
    budgetingControllerCategories: vi.fn(() => of({ items: [] })),
    budgetingControllerCreateCategory: vi.fn(() => of({ items: [] })),
    budgetingControllerUpdateCategory: vi.fn(() => of({ items: [] })),
    budgetingControllerAssignRule: vi.fn(() => of({ items: [] })),
    budgetingControllerDeleteCategory: vi.fn(() => of(undefined)),
  };
  const incomesApi = {
    budgetingControllerBasicIncomes: vi.fn(() => of({ items: [] })),
    budgetingControllerCreateBasicIncome: vi.fn(() => of({ items: [] })),
    budgetingControllerUpdateBasicIncome: vi.fn(() => of({ items: [] })),
    budgetingControllerDeleteBasicIncome: vi.fn(() => of(undefined)),
  };
  const recurrenceApi = {
    recurrenceControllerRules: vi.fn(() =>
      of({
        items: [
          {
            id: 'schedule-1',
            title: 'Rent',
            amount: '1000.001',
            currency: 'EUR',
            economicType: 'expense' as const,
            categoryId: null,
            categoryLabel: null,
            startsOn: '2026-01-31',
            rrule: 'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=31',
            goalId: null,
            loanId: null,
            investmentId: null,
            createdAt: '2026-01-01T00:00:00.000Z',
            updatedAt: '2026-01-01T00:00:00.000Z',
            forecast: {
              from: '2026-02-01',
              to: '2026-03-31',
              occurrences: ['2026-02-28', '2026-03-31'],
              truncated: false,
              iterationLimit: 2000,
            },
          },
        ],
      }),
    ),
    recurrenceControllerCreate: vi.fn(),
    recurrenceControllerUpdate: vi.fn(),
    recurrenceControllerDelete: vi.fn(),
  };
  const currentUser = signal({
    entitlements: {
      cashFlowRuleEditing: false,
      personalFinanceAccess: true,
      administration: false,
      resources: {
        categories: { allowed: true, limit: 10 },
        activeScheduledItems: { allowed: true, limit: 2 },
        activeGoals: { allowed: true, limit: 1 },
        activeLoans: { allowed: true, limit: 1 },
        currencies: { allowed: true, limit: 2 },
      },
    },
  });

  beforeEach(() => {
    vi.clearAllMocks();
    currentUser.update((user) => ({
      ...user,
      entitlements: { ...user.entitlements, cashFlowRuleEditing: false },
    }));
    TestBed.configureTestingModule({
      providers: [
        PlanningFacade,
        { provide: BudgetingService, useValue: budgetApi },
        { provide: CategoriesService, useValue: categoriesApi },
        { provide: BasicIncomeService, useValue: incomesApi },
        { provide: RecurrenceService, useValue: recurrenceApi },
        {
          provide: CurrenciesService,
          useValue: {
            currencyControllerUserCurrencies: vi.fn(() =>
              of({ items: [], available: [], mainCurrency: 'HUF' }),
            ),
          },
        },
        { provide: SessionStore, useValue: { currentUser } },
      ],
    });
  });

  it('preserves authoritative exact allocation and negative variance values', async () => {
    const facade = TestBed.inject(PlanningFacade);
    await facade.loadBudget('2026-02');
    expect(budgetApi.budgetingControllerRules).toHaveBeenCalledWith({ month: '2026-02' });
    expect(facade.budget().data?.allocation).toEqual(budget.allocation);
    expect(facade.budget().data?.items[0]?.plan?.signedVariance).toBe('-250.01');
  });

  it('uses server forecast occurrences without posting or client materialization', async () => {
    const facade = TestBed.inject(PlanningFacade);
    await facade.loadSchedules('2026-02-01', '2026-03-31');
    expect(recurrenceApi.recurrenceControllerRules).toHaveBeenCalledWith({
      from: '2026-02-01',
      to: '2026-03-31',
    });
    expect(recurrenceApi.recurrenceControllerCreate).not.toHaveBeenCalled();
    expect(facade.schedules().data?.items[0]?.forecast?.occurrences).toEqual([
      '2026-02-28',
      '2026-03-31',
    ]);
  });

  it('exposes server entitlements and passes exact command strings unchanged', async () => {
    const facade = TestBed.inject(PlanningFacade);
    expect(facade.canEditCashFlowRules()).toBe(false);
    expect(facade.categoryEntitlement()?.limit).toBe(10);
    await facade.createRule({ label: 'Future', percent: '33.3333', targetHint: null });
    expect(budgetApi.budgetingControllerCreateRule).toHaveBeenCalledWith(
      expect.objectContaining({ body: { label: 'Future', percent: '33.3333', targetHint: null } }),
      expect.anything(),
    );
  });

  it('derives premium and administrative editing presentation from the server capability', () => {
    const facade = TestBed.inject(PlanningFacade);
    currentUser.update((user) => ({
      ...user,
      entitlements: { ...user.entitlements, cashFlowRuleEditing: true },
    }));
    expect(facade.canEditCashFlowRules()).toBe(true);
  });
});
