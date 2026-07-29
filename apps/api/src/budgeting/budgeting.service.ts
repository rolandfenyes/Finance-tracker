import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import type { UserRole } from '../identity/identity.types';
import { CurrencyService } from '../currency/currency.service';
import { FxConversionService } from '../currency/fx-conversion.service';
import type { FxConversionStatus } from '../currency/currency.types';
import { LedgerPlanningReadService } from '../ledger/ledger-planning-read.service';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import { ApplicationError } from '../platform/http/application-error';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { EntitlementsService } from '../users/entitlements.service';
import type {
  BasicIncomesResponseDto,
  BudgetRulesResponseDto,
  CategoriesResponseDto,
  CreateBasicIncomeDto,
  CreateBudgetRuleDto,
  CreateCategoryDto,
  UpdateBasicIncomeDto,
  UpdateBudgetRuleDto,
  UpdateCategoryDto,
} from './budgeting.dto';
import { BudgetCalculator } from './budget-calculator';
import { BudgetingRepository } from './budgeting.repository';

@Injectable()
export class BudgetingService {
  constructor(
    @Inject(BudgetingRepository) private readonly repository: BudgetingRepository,
    @Inject(BudgetCalculator) private readonly calculator: BudgetCalculator,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CurrencyService) private readonly currencies: CurrencyService,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(LedgerPlanningReadService) private readonly ledger: LedgerPlanningReadService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async rules(userId: string, month?: string): Promise<BudgetRulesResponseDto> {
    const rules = await this.repository.listRules(userId);
    const allocation = this.calculator.allocation(rules.map(({ percent }) => percent));
    if (!month) {
      return { items: rules.map((rule) => ({ ...rule, plan: null })), allocation, period: null };
    }
    const { first, last } = monthRange(month);
    const main = await this.currencies.mainCurrency(userId);
    const policy = RoundingPolicy.create(main.minorUnit, main.roundingMode);
    const incomes = await this.repository.activeBasicIncomes(userId, first, last);
    let forecastIncome = ExactDecimal.create('0');
    let incomeStatus: FxConversionStatus = 'available';
    for (const income of incomes) {
      const result = await this.fx.convertObserved(
        income.amount,
        income.currency,
        main.code,
        first,
      );
      incomeStatus = mergeStatus(incomeStatus, result.status);
      if (result.convertedAmount !== undefined) {
        forecastIncome = forecastIncome.add(ExactDecimal.create(result.convertedAmount));
      }
    }
    forecastIncome = forecastIncome.round(policy);

    const categoryIds = rules.flatMap(({ assignedCategoryIds }) => assignedCategoryIds);
    const spending = await this.ledger.spendingByCategories(userId, categoryIds, first, last);
    const spendingByCategory = new Map(spending.map((item) => [item.categoryId, item]));
    const items = rules.map((rule) => {
      let spent = ExactDecimal.create('0');
      let spendingStatus: FxConversionStatus = 'available';
      for (const categoryId of rule.assignedCategoryIds) {
        const item = spendingByCategory.get(categoryId);
        if (!item) continue;
        spent = spent.add(ExactDecimal.create(item.amount));
        spendingStatus = mergeStatus(spendingStatus, item.status);
      }
      const status = mergeStatus(incomeStatus, spendingStatus);
      if (status === 'unavailable') {
        return { ...rule, plan: { status, currency: main.code } };
      }
      return {
        ...rule,
        plan: {
          status,
          currency: main.code,
          ...this.calculator.rulePlan(
            forecastIncome.toString(),
            rule.percent,
            spent.toString(),
            policy,
          ),
        },
      };
    });
    return {
      items,
      allocation,
      period: {
        month,
        currency: main.code,
        forecastIncomeStatus: incomeStatus,
        ...(incomeStatus === 'unavailable' ? {} : { forecastIncome: forecastIncome.toString() }),
      },
    };
  }

  async initializeRules(
    userId: string,
    role: UserRole,
    rules: CreateBudgetRuleDto[],
  ): Promise<BudgetRulesResponseDto> {
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role !== role || user.role === 'admin') throw forbidden();
        if ((await this.repository.countRules(userId, transaction)) !== 0) {
          throw new ApplicationError(
            HttpStatus.CONFLICT,
            'CONFLICT',
            'Initial budget rules have already been configured',
          );
        }
        if (role === 'free' && user.onboardStep > 2) throw cashFlowForbidden();
        const now = this.clock.now().toDate();
        for (const rule of rules) {
          await this.repository.createRule(transaction, userId, normalizeRule(rule), now);
        }
        await this.repository.advanceOnboarding(transaction, userId, 3, now);
      });
      return this.rules(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async createRule(
    userId: string,
    role: UserRole,
    dto: CreateBudgetRuleDto,
  ): Promise<BudgetRulesResponseDto> {
    this.assertCashFlowEditing(role);
    try {
      const now = this.clock.now().toDate();
      await this.repository.createRuleDirect(userId, normalizeRule(dto), now);
      return this.rules(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async updateRule(
    userId: string,
    role: UserRole,
    ruleId: string,
    dto: UpdateBudgetRuleDto,
  ): Promise<BudgetRulesResponseDto> {
    this.assertCashFlowEditing(role);
    assertNonEmptyPatch(dto);
    try {
      const updated = await this.repository.updateRule(
        userId,
        ruleId,
        normalizeRulePatch(dto),
        this.clock.now().toDate(),
      );
      if (!updated) throw notFound('Budget rule');
      return this.rules(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async deleteRule(userId: string, role: UserRole, ruleId: string): Promise<void> {
    this.assertCashFlowEditing(role);
    try {
      const deleted = await this.repository.transaction((transaction) =>
        this.repository.deleteRule(transaction, userId, ruleId, this.clock.now().toDate()),
      );
      if (!deleted) throw notFound('Budget rule');
    } catch (error) {
      throw translate(error);
    }
  }

  async categories(userId: string): Promise<CategoriesResponseDto> {
    return { items: await this.repository.listCategories(userId) };
  }

  async createCategory(userId: string, dto: CreateCategoryDto): Promise<CategoriesResponseDto> {
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role === 'admin') throw forbidden();
        const current = await this.repository.countCategories(userId, transaction);
        this.entitlements.assertWithinQuota(user.role, 'categories', current);
        const now = this.clock.now().toDate();
        await this.repository.createCategory(
          transaction,
          userId,
          { label: normalizeText(dto.label), kind: dto.kind, color: normalizeColor(dto.color) },
          now,
        );
        await this.repository.advanceOnboarding(transaction, userId, 5, now);
      });
      return this.categories(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async updateCategory(
    userId: string,
    categoryId: string,
    dto: UpdateCategoryDto,
  ): Promise<CategoriesResponseDto> {
    assertNonEmptyPatch(dto);
    try {
      const updated = await this.repository.updateCategory(
        userId,
        categoryId,
        {
          ...(dto.label === undefined ? {} : { label: normalizeText(dto.label) }),
          ...(dto.kind === undefined ? {} : { kind: dto.kind }),
          ...(dto.color === undefined ? {} : { color: normalizeColor(dto.color) }),
        },
        this.clock.now().toDate(),
      );
      if (!updated) throw notFound('Category');
      return this.categories(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async assignCategoryRule(
    userId: string,
    role: UserRole,
    categoryId: string,
    ruleId: string | null,
  ): Promise<CategoriesResponseDto> {
    this.assertCashFlowEditing(role);
    try {
      if (ruleId !== null && !(await this.repository.ruleExists(userId, ruleId))) {
        throw notFound('Budget rule');
      }
      const updated = await this.repository.assignCategoryRule(
        userId,
        categoryId,
        ruleId,
        this.clock.now().toDate(),
      );
      if (!updated) throw notFound('Spending category');
      return this.categories(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async deleteCategory(userId: string, categoryId: string): Promise<void> {
    try {
      const result = await this.repository.deleteCategory(userId, categoryId);
      if (result === 'not_found') throw notFound('Category');
      if (result === 'protected') {
        throw new ApplicationError(
          HttpStatus.CONFLICT,
          'CONFLICT',
          'Protected system categories cannot be deleted',
        );
      }
    } catch (error) {
      throw translate(error, 'Referenced categories cannot be deleted');
    }
  }

  async basicIncomes(userId: string): Promise<BasicIncomesResponseDto> {
    return { items: await this.repository.listBasicIncomes(userId) };
  }

  async createBasicIncome(
    userId: string,
    dto: CreateBasicIncomeDto,
  ): Promise<BasicIncomesResponseDto> {
    validateAmount(dto.amount);
    validateDates(dto.validFrom, dto.validTo);
    try {
      await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.lockUser(transaction, userId))) throw forbidden();
        await this.assertIncomeReferences(transaction, userId, dto.currency, dto.categoryId);
        const now = this.clock.now().toDate();
        await this.repository.createBasicIncome(
          transaction,
          userId,
          {
            label: normalizeText(dto.label),
            amount: ExactDecimal.create(dto.amount).toString(),
            currency: dto.currency,
            validFrom: dto.validFrom,
            validTo: dto.validTo,
            categoryId: dto.categoryId,
          },
          now,
        );
        await this.repository.advanceOnboarding(transaction, userId, 6, now);
      });
      return this.basicIncomes(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async updateBasicIncome(
    userId: string,
    incomeId: string,
    dto: UpdateBasicIncomeDto,
  ): Promise<BasicIncomesResponseDto> {
    assertNonEmptyPatch(dto);
    const existing = (await this.repository.listBasicIncomes(userId)).find(
      ({ id }) => id === incomeId,
    );
    if (!existing) throw notFound('Basic income');
    const amount = dto.amount ?? existing.amount;
    const validFrom = dto.validFrom ?? existing.validFrom;
    const validTo = dto.validTo === undefined ? existing.validTo : dto.validTo;
    validateAmount(amount);
    validateDates(validFrom, validTo);
    try {
      await this.repository.transaction(async (transaction) => {
        if (!(await this.repository.lockUser(transaction, userId))) throw forbidden();
        await this.assertIncomeReferences(
          transaction,
          userId,
          dto.currency ?? existing.currency,
          dto.categoryId === undefined ? existing.categoryId : dto.categoryId,
        );
        const updated = await this.repository.updateBasicIncome(
          transaction,
          userId,
          incomeId,
          {
            ...(dto.label === undefined ? {} : { label: normalizeText(dto.label) }),
            ...(dto.amount === undefined
              ? {}
              : { amount: ExactDecimal.create(dto.amount).toString() }),
            ...(dto.currency === undefined ? {} : { currency: dto.currency }),
            ...(dto.validFrom === undefined ? {} : { validFrom: dto.validFrom }),
            ...(dto.validTo === undefined ? {} : { validTo: dto.validTo }),
            ...(dto.categoryId === undefined ? {} : { categoryId: dto.categoryId }),
          },
          this.clock.now().toDate(),
        );
        if (!updated) throw notFound('Basic income');
      });
      return this.basicIncomes(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async deleteBasicIncome(userId: string, incomeId: string): Promise<void> {
    if (!(await this.repository.deleteBasicIncome(userId, incomeId))) {
      throw notFound('Basic income');
    }
  }

  private assertCashFlowEditing(role: UserRole): void {
    if (!this.entitlements.forRole(role).cashFlowRuleEditing) throw cashFlowForbidden();
  }

  private async assertIncomeReferences(
    executor: Parameters<BudgetingRepository['currencyMembershipExists']>[2],
    userId: string,
    currency: string,
    categoryId?: string | null,
  ): Promise<void> {
    if (!(await this.repository.currencyMembershipExists(userId, currency, executor))) {
      throw new ApplicationError(
        HttpStatus.UNPROCESSABLE_ENTITY,
        'UNPROCESSABLE_ENTITY',
        'Basic income currency must be selected by the current user',
      );
    }
    if (
      categoryId !== undefined &&
      categoryId !== null &&
      !(await this.repository.incomeCategoryExists(userId, categoryId, executor))
    ) {
      throw new ApplicationError(
        HttpStatus.UNPROCESSABLE_ENTITY,
        'UNPROCESSABLE_ENTITY',
        'Basic income category must be an owned income category',
      );
    }
  }
}

function normalizeRule(dto: CreateBudgetRuleDto): {
  label: string;
  percent: string;
  targetHint: string | null;
} {
  return {
    label: normalizeText(dto.label),
    percent: ExactDecimal.create(dto.percent).toString(),
    targetHint:
      dto.targetHint === null ? null : dto.targetHint ? normalizeText(dto.targetHint) : null,
  };
}

function normalizeRulePatch(dto: UpdateBudgetRuleDto): {
  label?: string;
  percent?: string;
  targetHint?: string | null;
} {
  return {
    ...(dto.label === undefined ? {} : { label: normalizeText(dto.label) }),
    ...(dto.percent === undefined ? {} : { percent: ExactDecimal.create(dto.percent).toString() }),
    ...(dto.targetHint === undefined
      ? {}
      : { targetHint: dto.targetHint === null ? null : normalizeText(dto.targetHint) }),
  };
}

function normalizeText(value: string): string {
  return value.trim();
}

function normalizeColor(value: string): string {
  return value.toUpperCase();
}

function validateAmount(value: string): void {
  if (!ExactDecimal.create(value).isPositive()) {
    throw new ApplicationError(
      HttpStatus.UNPROCESSABLE_ENTITY,
      'UNPROCESSABLE_ENTITY',
      'Amount must be greater than zero',
    );
  }
}

function validateDates(validFrom: string, validTo?: string | null): void {
  CalendarDate.create(validFrom);
  if (validTo !== undefined && validTo !== null) {
    CalendarDate.create(validTo);
    if (validTo < validFrom) {
      throw new ApplicationError(
        HttpStatus.UNPROCESSABLE_ENTITY,
        'UNPROCESSABLE_ENTITY',
        'validTo must not be before validFrom',
      );
    }
  }
}

function assertNonEmptyPatch(value: object): void {
  if (Object.values(value).every((item) => item === undefined)) {
    throw new ApplicationError(
      HttpStatus.BAD_REQUEST,
      'BAD_REQUEST',
      'At least one field is required',
    );
  }
}

function monthRange(month: string): { first: string; last: string } {
  const [yearText, monthText] = month.split('-');
  const year = Number(yearText);
  const monthNumber = Number(monthText);
  const days = [31, isLeapYear(year) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][
    monthNumber - 1
  ]!;
  return { first: `${month}-01`, last: `${month}-${days.toString().padStart(2, '0')}` };
}

function isLeapYear(year: number): boolean {
  return year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
}

function mergeStatus(left: FxConversionStatus, right: FxConversionStatus): FxConversionStatus {
  if (left === 'unavailable' || right === 'unavailable') return 'unavailable';
  if (left === 'stale' || right === 'stale') return 'stale';
  return 'available';
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function cashFlowForbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Cash-flow rule editing requires premium access');
}

function notFound(resource: string): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', `${resource} was not found`);
}

function translate(
  error: unknown,
  referenceMessage = 'Referenced resource update conflicted',
): Error {
  if (error instanceof ApplicationError) return error;
  const code =
    typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
  if (code === '23503') return new ApplicationError(409, 'CONFLICT', referenceMessage);
  if (code === '23505') {
    return new ApplicationError(409, 'CONFLICT', 'Planning resource update conflicted');
  }
  if (code === '23514' || code === '22007' || code === '22008') {
    return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', 'Planning resource invariant failed');
  }
  return error instanceof Error ? error : new Error('Planning persistence failed');
}
