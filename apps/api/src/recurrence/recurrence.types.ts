export const recurrenceFrequencies = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'] as const;
export type RecurrenceFrequency = (typeof recurrenceFrequencies)[number];

export const recurrenceEconomicTypes = ['income', 'expense', 'transfer'] as const;
export type RecurrenceEconomicType = (typeof recurrenceEconomicTypes)[number];

export const recurrenceWeekdays = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'] as const;
export type RecurrenceWeekday = (typeof recurrenceWeekdays)[number];

export interface ParsedRecurrenceRule {
  frequency: RecurrenceFrequency | null;
  interval: number;
  byDay: RecurrenceWeekday[];
  byMonthDay: number | null;
  byMonth: number | null;
  count: number | null;
  until: string | null;
  canonical: string;
}

export interface RecurrenceExpansion {
  dates: string[];
  iterations: number;
  truncated: boolean;
}

export interface RecurringRule {
  id: string;
  title: string;
  amount: string;
  currency: string;
  economicType: RecurrenceEconomicType;
  startsOn: string;
  rrule: string;
  categoryId: string | null;
  categoryLabel: string | null;
  goalId: string | null;
  createdAt: string;
  updatedAt: string;
}

export type RecurrenceJobStatus =
  'queued' | 'running' | 'completed' | 'retryable_failed' | 'dead_letter';

export interface RecurrenceJobExecution {
  id: string;
  jobKey: string;
  queueJobId: string;
  dueThrough: string;
  status: RecurrenceJobStatus;
  attemptCount: number;
  maxAttempts: number;
  errorCode: string | null;
  startedAt: string | null;
  finishedAt: string | null;
  createdAt: string;
  updatedAt: string;
}
