import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import { BudgetCalculator } from './budget-calculator';

describe('BudgetCalculator', () => {
  const calculator = new BudgetCalculator();
  const money = RoundingPolicy.create(2, 'HALF_EVEN');

  it('implements BUD-01 and BUD-02 with visible signed variance', () => {
    expect(calculator.rulePlan('1000', '50', '425', money)).toEqual({
      plannedAmount: '500',
      assignedCategorySpending: '425',
      signedVariance: '75',
    });
    expect(calculator.rulePlan('1000', '50', '575', money)).toEqual({
      plannedAmount: '500',
      assignedCategorySpending: '575',
      signedVariance: '-75',
    });
  });

  it('implements BUD-03 without normalizing aggregate over-allocation', () => {
    expect(calculator.allocation(['50', '70'])).toEqual({
      totalPercent: '120',
      status: 'over_allocated',
      overAllocatedBy: '20',
    });
  });

  it('does not invent a per-category cap for BUD-04', () => {
    expect(calculator.rulePlan('1000', '50', '0', money)).toEqual({
      plannedAmount: '500',
      assignedCategorySpending: '0',
      signedVariance: '500',
    });
  });
});
