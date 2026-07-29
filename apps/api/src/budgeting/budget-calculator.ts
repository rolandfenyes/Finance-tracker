import { Injectable } from '@nestjs/common';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import type { BudgetAllocation } from './budgeting.types';

const ONE_HUNDRED = ExactDecimal.create('100');

@Injectable()
export class BudgetCalculator {
  allocation(percentages: readonly string[]): BudgetAllocation {
    const total = percentages.reduce(
      (sum, value) => sum.add(this.percentage(value)),
      ExactDecimal.create('0'),
    );
    const over =
      total.compare(ONE_HUNDRED) > 0 ? total.subtract(ONE_HUNDRED) : ExactDecimal.create('0');
    return {
      totalPercent: total.toString(),
      status: over.isPositive() ? 'over_allocated' : 'within_allocation',
      overAllocatedBy: over.toString(),
    };
  }

  rulePlan(
    forecastIncome: string,
    percent: string,
    assignedCategorySpending: string,
    policy: RoundingPolicy,
  ): { plannedAmount: string; assignedCategorySpending: string; signedVariance: string } {
    const planned = ExactDecimal.create(forecastIncome)
      .multiply(this.percentage(percent))
      .divide(ONE_HUNDRED, policy);
    const spent = ExactDecimal.create(assignedCategorySpending).round(policy);
    return {
      plannedAmount: planned.toString(),
      assignedCategorySpending: spent.toString(),
      signedVariance: planned.subtract(spent).round(policy).toString(),
    };
  }

  private percentage(value: string): ExactDecimal {
    const percent = ExactDecimal.create(value);
    if (percent.isNegative() || percent.compare(ONE_HUNDRED) > 0) {
      throw new Error('Rule percentage must be between zero and one hundred');
    }
    return percent;
  }
}
