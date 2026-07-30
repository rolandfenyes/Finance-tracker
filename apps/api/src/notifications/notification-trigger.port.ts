import type {
  EmergencyReserve,
  EmergencyReserveMovement,
} from '../emergency-reserve/emergency-reserve.types';
import type { Goal } from '../goals/goals.types';

export const NOTIFICATION_TRIGGER = Symbol('NOTIFICATION_TRIGGER');

export interface BudgetOverspendingNotification {
  ruleId: string;
  ruleLabel: string;
  plannedAmount: string;
  spendingAmount: string;
  overspendAmount: string;
  currency: string;
  month: string;
  calculatedAt: string;
}

export interface PeriodicReportNotification {
  cadence: 'weekly' | 'monthly' | 'yearly';
  period: { first: string; last: string };
  currency: string;
  expense: string;
  income: string;
  netCashFlow: string;
  calculatedAt: string;
}

export interface NotificationTrigger {
  goalCompleted(userId: string, goal: Goal): Promise<void>;
  emergencyWithdrawal(
    userId: string,
    reserve: EmergencyReserve,
    movement: EmergencyReserveMovement,
  ): Promise<void>;
  emergencyMotivation(userId: string, reserve: EmergencyReserve, periodKey: string): Promise<void>;
  feedbackCreated(input: {
    feedbackId: string;
    userId: string;
    title: string;
    kind: string;
    severity: string | null;
    createdAt: string;
  }): Promise<void>;
  feedbackResolved(input: {
    feedbackId: string;
    userId: string;
    title: string;
    resolvedAt: string;
  }): Promise<void>;
  budgetOverspent(
    userId: string,
    sourceEntryId: string,
    snapshot: BudgetOverspendingNotification,
  ): Promise<void>;
  periodicReport(userId: string, snapshot: PeriodicReportNotification): Promise<void>;
  educationalTips(userId: string, periodKey: string): Promise<void>;
}
