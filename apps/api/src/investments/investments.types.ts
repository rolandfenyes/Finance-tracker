import type { InvestmentFrequency } from './investment-calculator';

export type InvestmentType = 'savings' | 'etf' | 'stock';
export type InvestmentMovementDirection = 'deposit' | 'withdrawal';

export interface InvestmentMovement {
  id: string;
  journalEntryId: string;
  direction: InvestmentMovementDirection;
  amount: string;
  currency: string;
  investmentAmount: string;
  investmentCurrency: string;
  occurredOn: string;
  note: string | null;
  reversedByJournalEntryId: string | null;
  createdAt: string;
}

export interface InvestmentRecurringRule {
  id: string;
  title: string;
  amount: string;
  currency: string;
  economicType: 'transfer';
  startsOn: string;
  rrule: string;
  investmentId: string;
  createdAt: string;
  updatedAt: string;
}

export interface InvestmentRecord {
  id: string;
  type: InvestmentType;
  name: string;
  provider: string | null;
  identifier: string | null;
  notes: string | null;
  currency: string;
  scenarioAnnualRate: string | null;
  scenarioFrequency: InvestmentFrequency;
  accountId: string;
  movements: InvestmentMovement[];
  recurringRule: InvestmentRecurringRule | null;
  createdAt: string;
  updatedAt: string;
}

export interface Investment extends InvestmentRecord {
  balance: string;
  scenario: {
    enabled: boolean;
    version: 'nominal_compound_scenario_v1';
    label: 'User-authored nominal compound return scenario';
    nominalAnnualRate: string | null;
    frequency: InvestmentFrequency;
    guaranteed: false;
    expectedReturn: false;
    affectsPostedBalance: false;
    milestones: Array<{
      horizonYears: string;
      value: string;
      contributionTotal: string;
      scenarioGain: string;
    }>;
  };
  recurringContributionForecast: {
    from: string;
    to: string;
    occurrences: string[];
    amount: string;
    currency: string;
    investmentCurrencyContributionTotal: string | null;
    conversionStatus: 'same_currency' | 'future_fx_unavailable';
    truncated: boolean;
  } | null;
}
