import type { RecurringRule } from '../recurrence/recurrence.types';

export type GoalStatus = 'active' | 'paused' | 'completed';

export interface GoalRecurringRule extends Omit<
  RecurringRule,
  'economicType' | 'categoryId' | 'categoryLabel' | 'goalId'
> {
  economicType: 'transfer';
  categoryId: null;
  categoryLabel: null;
  goalId: string;
}

export interface GoalContribution {
  id: string;
  journalEntryId: string;
  amount: string;
  currency: string;
  goalAmount: string;
  goalCurrency: string;
  occurredOn: string;
  note: string | null;
  reversedByJournalEntryId: string | null;
  correctsContributionId: string | null;
  createdAt: string;
}

export interface Goal {
  id: string;
  title: string;
  targetAmount: string;
  currentAmount: string;
  remainingAmount: string;
  progressPercent: string;
  currency: string;
  deadline: string | null;
  priority: number;
  status: GoalStatus;
  categoryId: string | null;
  categoryLabel: string | null;
  archivedAt: string | null;
  recurringRule: GoalRecurringRule | null;
  contributions: GoalContribution[];
  createdAt: string;
  updatedAt: string;
}

export interface LockedGoal {
  id: string;
  userId: string;
  targetAmount: string;
  currency: string;
  status: GoalStatus;
  archivedAt: Date | null;
  title: string;
  ledgerAccountId: string;
  currentAmount: string;
}
