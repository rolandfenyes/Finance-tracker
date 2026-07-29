import type { FxConversionStatus } from '../currency/currency.types';

export type CategoryKind = 'income' | 'spending';

export interface BudgetRule {
  id: string;
  label: string;
  percent: string;
  targetHint: string | null;
  assignedCategoryIds: string[];
  createdAt: string;
  updatedAt: string;
}

export interface Category {
  id: string;
  label: string;
  kind: CategoryKind;
  color: string;
  budgetRuleId: string | null;
  budgetRuleLabel: string | null;
  systemKey: string | null;
  protected: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface BasicIncome {
  id: string;
  label: string;
  amount: string;
  currency: string;
  validFrom: string;
  validTo: string | null;
  categoryId: string | null;
  categoryLabel: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface BudgetRulePlan {
  status: FxConversionStatus;
  currency: string;
  plannedAmount?: string;
  assignedCategorySpending?: string;
  signedVariance?: string;
}

export interface BudgetAllocation {
  totalPercent: string;
  status: 'within_allocation' | 'over_allocated';
  overAllocatedBy: string;
}

export interface BudgetPlanPeriod {
  month: string;
  currency: string;
  forecastIncomeStatus: FxConversionStatus;
  forecastIncome?: string;
}
