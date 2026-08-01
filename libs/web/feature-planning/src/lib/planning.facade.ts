import { HttpContext } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import type { BasicIncomesResponseDto } from '@mymoneymap/generated-api-client/models/basic-incomes-response-dto';
import type { BudgetRulesResponseDto } from '@mymoneymap/generated-api-client/models/budget-rules-response-dto';
import type { CategoriesResponseDto } from '@mymoneymap/generated-api-client/models/categories-response-dto';
import type { CreateBasicIncomeDto } from '@mymoneymap/generated-api-client/models/create-basic-income-dto';
import type { CreateBudgetRuleDto } from '@mymoneymap/generated-api-client/models/create-budget-rule-dto';
import type { CreateCategoryDto } from '@mymoneymap/generated-api-client/models/create-category-dto';
import type { CreateRecurringRuleDto } from '@mymoneymap/generated-api-client/models/create-recurring-rule-dto';
import type { RecurringRulesResponseDto } from '@mymoneymap/generated-api-client/models/recurring-rules-response-dto';
import type { UpdateBasicIncomeDto } from '@mymoneymap/generated-api-client/models/update-basic-income-dto';
import type { UpdateBudgetRuleDto } from '@mymoneymap/generated-api-client/models/update-budget-rule-dto';
import type { UpdateCategoryDto } from '@mymoneymap/generated-api-client/models/update-category-dto';
import type { UpdateRecurringRuleDto } from '@mymoneymap/generated-api-client/models/update-recurring-rule-dto';
import type { UserCurrenciesResponseDto } from '@mymoneymap/generated-api-client/models/user-currencies-response-dto';
import { BasicIncomeService } from '@mymoneymap/generated-api-client/services/basic-income.service';
import { BudgetingService } from '@mymoneymap/generated-api-client/services/budgeting.service';
import { CategoriesService } from '@mymoneymap/generated-api-client/services/categories.service';
import { CurrenciesService } from '@mymoneymap/generated-api-client/services/currencies.service';
import { RecurrenceService } from '@mymoneymap/generated-api-client/services/recurrence.service';
import { API_ROUTE_TEMPLATE, parseApiError, SessionStore } from '@mymoneymap/web-core';
import { firstValueFrom, type Observable } from 'rxjs';

export type PlanningStatus = 'idle' | 'loading' | 'ready' | 'empty' | 'error';
export interface PlanningState<T> {
  readonly status: PlanningStatus;
  readonly data: T | null;
  readonly requestId: string | null;
}

const initial = <T>(): PlanningState<T> => ({ status: 'idle', data: null, requestId: null });

@Injectable({ providedIn: 'root' })
export class PlanningFacade {
  private readonly budgetApi = inject(BudgetingService);
  private readonly categoryApi = inject(CategoriesService);
  private readonly incomeApi = inject(BasicIncomeService);
  private readonly recurrenceApi = inject(RecurrenceService);
  private readonly currenciesApi = inject(CurrenciesService);
  private readonly session = inject(SessionStore);
  private readonly budgetSignal = signal(initial<BudgetRulesResponseDto>());
  private readonly categorySignal = signal(initial<CategoriesResponseDto>());
  private readonly incomeSignal = signal(initial<BasicIncomesResponseDto>());
  private readonly scheduleSignal = signal(initial<RecurringRulesResponseDto>());
  private readonly currenciesSignal = signal(initial<UserCurrenciesResponseDto>());
  private readonly pendingSignal = signal(false);
  private readonly commandErrorSignal = signal<ReturnType<typeof parseApiError> | null>(null);

  readonly budget = this.budgetSignal.asReadonly();
  readonly categories = this.categorySignal.asReadonly();
  readonly incomes = this.incomeSignal.asReadonly();
  readonly schedules = this.scheduleSignal.asReadonly();
  readonly currencies = this.currenciesSignal.asReadonly();
  readonly pending = this.pendingSignal.asReadonly();
  readonly commandError = this.commandErrorSignal.asReadonly();
  readonly canEditCashFlowRules = computed(
    () => this.session.currentUser()?.entitlements.cashFlowRuleEditing === true,
  );
  readonly categoryEntitlement = computed(
    () => this.session.currentUser()?.entitlements.resources.categories ?? null,
  );
  readonly scheduleEntitlement = computed(
    () => this.session.currentUser()?.entitlements.resources.activeScheduledItems ?? null,
  );

  async loadHub(): Promise<void> {
    await Promise.all([
      this.loadBudget(),
      this.loadCategories(),
      this.loadIncomes(),
      this.loadSchedules(),
    ]);
  }
  async loadBudget(month?: string): Promise<void> {
    await this.load(
      this.budgetSignal,
      () => this.budgetApi.budgetingControllerRules({ month }),
      (v) => v.items.length === 0,
    );
  }
  async loadCategories(): Promise<void> {
    await this.load(
      this.categorySignal,
      () => this.categoryApi.budgetingControllerCategories(),
      (v) => v.items.length === 0,
    );
  }
  async loadIncomes(): Promise<void> {
    await this.load(
      this.incomeSignal,
      () => this.incomeApi.budgetingControllerBasicIncomes(),
      (v) => v.items.length === 0,
    );
  }
  async loadSchedules(from?: string, to?: string): Promise<void> {
    const params = from && to ? { from, to } : undefined;
    await this.load(
      this.scheduleSignal,
      () => this.recurrenceApi.recurrenceControllerRules(params),
      (v) => v.items.length === 0,
    );
  }
  async loadCurrencies(): Promise<void> {
    await this.load(
      this.currenciesSignal,
      () => this.currenciesApi.currencyControllerUserCurrencies(),
      (v) => v.items.length === 0,
    );
  }

  createRule(body: CreateBudgetRuleDto): Promise<boolean> {
    return this.command(async () =>
      this.budgetSignal.set(
        this.ready(
          await firstValueFrom(
            this.budgetApi.budgetingControllerCreateRule({ body }, context('/api/v1/budget-rules')),
          ),
        ),
      ),
    );
  }
  updateRule(id: string, body: UpdateBudgetRuleDto): Promise<boolean> {
    return this.command(async () =>
      this.budgetSignal.set(
        this.ready(
          await firstValueFrom(
            this.budgetApi.budgetingControllerUpdateRule(
              { id, body },
              context('/api/v1/budget-rules/{id}'),
            ),
          ),
        ),
      ),
    );
  }
  deleteRule(id: string): Promise<boolean> {
    return this.command(async () => {
      await firstValueFrom(
        this.budgetApi.budgetingControllerDeleteRule({ id }, context('/api/v1/budget-rules/{id}')),
      );
      await this.loadBudget();
    });
  }
  createCategory(body: CreateCategoryDto): Promise<boolean> {
    return this.command(async () =>
      this.categorySignal.set(
        this.ready(
          await firstValueFrom(
            this.categoryApi.budgetingControllerCreateCategory(
              { body },
              context('/api/v1/categories'),
            ),
          ),
        ),
      ),
    );
  }
  updateCategory(id: string, body: UpdateCategoryDto): Promise<boolean> {
    return this.command(async () =>
      this.categorySignal.set(
        this.ready(
          await firstValueFrom(
            this.categoryApi.budgetingControllerUpdateCategory(
              { id, body },
              context('/api/v1/categories/{id}'),
            ),
          ),
        ),
      ),
    );
  }
  assignRule(id: string, budgetRuleId: string | null): Promise<boolean> {
    return this.command(async () =>
      this.categorySignal.set(
        this.ready(
          await firstValueFrom(
            this.categoryApi.budgetingControllerAssignRule(
              { id, body: { budgetRuleId } },
              context('/api/v1/categories/{id}/budget-rule'),
            ),
          ),
        ),
      ),
    );
  }
  deleteCategory(id: string): Promise<boolean> {
    return this.command(async () => {
      await firstValueFrom(
        this.categoryApi.budgetingControllerDeleteCategory(
          { id },
          context('/api/v1/categories/{id}'),
        ),
      );
      await this.loadCategories();
    });
  }
  createIncome(body: CreateBasicIncomeDto): Promise<boolean> {
    return this.command(async () =>
      this.incomeSignal.set(
        this.ready(
          await firstValueFrom(
            this.incomeApi.budgetingControllerCreateBasicIncome(
              { body },
              context('/api/v1/basic-incomes'),
            ),
          ),
        ),
      ),
    );
  }
  updateIncome(id: string, body: UpdateBasicIncomeDto): Promise<boolean> {
    return this.command(async () =>
      this.incomeSignal.set(
        this.ready(
          await firstValueFrom(
            this.incomeApi.budgetingControllerUpdateBasicIncome(
              { id, body },
              context('/api/v1/basic-incomes/{id}'),
            ),
          ),
        ),
      ),
    );
  }
  deleteIncome(id: string): Promise<boolean> {
    return this.command(async () => {
      await firstValueFrom(
        this.incomeApi.budgetingControllerDeleteBasicIncome(
          { id },
          context('/api/v1/basic-incomes/{id}'),
        ),
      );
      await this.loadIncomes();
    });
  }
  createSchedule(body: CreateRecurringRuleDto): Promise<boolean> {
    return this.command(async () =>
      this.scheduleSignal.set(
        this.ready(
          await firstValueFrom(
            this.recurrenceApi.recurrenceControllerCreate(
              { body },
              context('/api/v1/recurring-rules'),
            ),
          ),
        ),
      ),
    );
  }
  updateSchedule(id: string, body: UpdateRecurringRuleDto): Promise<boolean> {
    return this.command(async () =>
      this.scheduleSignal.set(
        this.ready(
          await firstValueFrom(
            this.recurrenceApi.recurrenceControllerUpdate(
              { id, body },
              context('/api/v1/recurring-rules/{id}'),
            ),
          ),
        ),
      ),
    );
  }
  deleteSchedule(id: string): Promise<boolean> {
    return this.command(async () => {
      await firstValueFrom(
        this.recurrenceApi.recurrenceControllerDelete(
          { id },
          context('/api/v1/recurring-rules/{id}'),
        ),
      );
      await this.loadSchedules();
    });
  }

  private ready<T>(data: T): PlanningState<T> {
    return { status: 'ready', data, requestId: null };
  }
  private async command(action: () => Promise<void>): Promise<boolean> {
    this.pendingSignal.set(true);
    this.commandErrorSignal.set(null);
    try {
      await action();
      return true;
    } catch (error) {
      this.commandErrorSignal.set(parseApiError(error));
      return false;
    } finally {
      this.pendingSignal.set(false);
    }
  }
  private async load<T>(
    target: {
      set(v: PlanningState<T>): void;
      update(fn: (v: PlanningState<T>) => PlanningState<T>): void;
    },
    request: () => Observable<T>,
    empty: (v: T) => boolean,
  ): Promise<void> {
    target.update((state) => ({ ...state, status: 'loading', requestId: null }));
    try {
      const data = await firstValueFrom(request());
      target.set({ status: empty(data) ? 'empty' : 'ready', data, requestId: null });
    } catch (error) {
      const parsed = parseApiError(error);
      target.update((state) => ({ ...state, status: 'error', requestId: parsed.requestId }));
    }
  }
}

function context(route: string): HttpContext {
  return new HttpContext().set(API_ROUTE_TEMPLATE, route);
}
