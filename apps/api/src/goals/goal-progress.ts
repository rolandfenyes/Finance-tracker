import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';

const PRESENTATION_PERCENT = RoundingPolicy.create(4, 'HALF_EVEN');

export interface GoalProgressContribution {
  goalAmount: string;
  reversedByJournalEntryId: string | null;
}

export interface DerivedGoalProgress {
  currentAmount: string;
  remainingAmount: string;
  progressPercent: string;
}

export function deriveGoalProgress(
  targetAmount: string,
  contributions: readonly GoalProgressContribution[],
): DerivedGoalProgress {
  const target = ExactDecimal.create(targetAmount);
  if (!target.isPositive()) throw new Error('Goal target must be greater than zero');
  const current = contributions
    .filter(({ reversedByJournalEntryId }) => reversedByJournalEntryId === null)
    .reduce(
      (total, contribution) => total.add(ExactDecimal.create(contribution.goalAmount)),
      ExactDecimal.create('0'),
    );
  return {
    currentAmount: current.toString(),
    remainingAmount: target.subtract(current).toString(),
    progressPercent: current
      .multiply(ExactDecimal.create('100'))
      .divide(target, PRESENTATION_PERCENT)
      .toString(),
  };
}
