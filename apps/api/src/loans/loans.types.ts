import type { ProjectedLoanPayment } from './loan-calculator';

export interface LoanPayment {
  id: string;
  journalEntryId: string;
  amount: string;
  currency: string;
  principalComponent: string;
  interestComponent: string;
  feeComponent: string;
  loanPrincipalComponent: string;
  loanInterestComponent: string;
  loanFeeComponent: string;
  loanCurrency: string;
  conversion: {
    status: 'available' | 'stale';
    rate: string;
    provider: string;
    rateAt: string;
    fetchedAt: string;
  };
  paidOn: string;
  source: 'manual' | 'scheduled';
  recurringOccurrenceId: string | null;
  note: string | null;
  reversedByJournalEntryId: string | null;
  correctsPaymentId: string | null;
  createdAt: string;
}

export interface LoanRecurringRule {
  id: string;
  title: string;
  amount: string;
  currency: string;
  economicType: 'expense';
  startsOn: string;
  rrule: string;
  loanId: string;
  createdAt: string;
  updatedAt: string;
}

export interface Loan {
  id: string;
  title: string;
  principal: string;
  outstandingPrincipal: string;
  currency: string;
  nominalAnnualRate: string;
  termMonths: number;
  startsOn: string;
  endsOn: string | null;
  paymentDay: number | null;
  extraPaymentScenario: string;
  insuranceMonthly: string;
  estimate: {
    version: 'standard_nominal_monthly_annuity_v1';
    label: 'Standard fixed nominal-rate monthly annuity illustration';
    rateLabel: 'Nominal annual rate';
    isApr: false;
    monthlyPayment: string;
    assumptions: string[];
  };
  projectedSchedule: ProjectedLoanPayment[];
  payments: LoanPayment[];
  recurringRule: LoanRecurringRule | null;
  completedAt: string | null;
  archivedAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface LockedLoan {
  id: string;
  userId: string;
  title: string;
  principal: string;
  outstandingPrincipal: string;
  currency: string;
  nominalAnnualRate: string;
  insuranceMonthly: string;
  liabilityAccountId: string;
  completedAt: Date | null;
  archivedAt: Date | null;
}
