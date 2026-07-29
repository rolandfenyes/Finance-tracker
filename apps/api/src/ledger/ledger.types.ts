export const ledgerEconomicTypes = [
  'external_income',
  'external_expense',
  'internal_transfer',
  'adjustment',
  'fee',
  'interest',
  'dividend',
  'loan_repayment',
  'trade_cash',
] as const;
export type LedgerEconomicType = (typeof ledgerEconomicTypes)[number];

export const manualEconomicTypes = [
  'external_income',
  'external_expense',
  'internal_transfer',
  'adjustment',
  'fee',
  'interest',
  'dividend',
] as const;
export type ManualEconomicType = (typeof manualEconomicTypes)[number];

export const ledgerAccountKinds = [
  'cash',
  'goal',
  'emergency_reserve',
  'investment',
  'loan_liability',
  'securities_cash',
  'securities_holding',
] as const;
export type LedgerAccountKind = (typeof ledgerAccountKinds)[number];

export type AdjustmentDirection = 'increase' | 'decrease';
export type JournalSide = 'debit' | 'credit';
export type LedgerSourceModule =
  | 'manual'
  | 'scheduling'
  | 'goals'
  | 'emergency_fund'
  | 'loans'
  | 'investments'
  | 'securities'
  | 'migration';

export interface JournalLeg {
  id: string;
  accountId: string | null;
  side: JournalSide;
  amount: string;
  currency: string;
}

export interface JournalEntry {
  id: string;
  economicType: LedgerEconomicType;
  categoryId: string | null;
  note: string | null;
  source: { module: LedgerSourceModule; referenceId: string | null };
  postedOn: string;
  effectiveAt: string;
  createdAt: string;
  actorUserId: string;
  reversesEntryId: string | null;
  replacesEntryId: string | null;
  legs: JournalLeg[];
}

export interface PostJournalCommand {
  userId: string;
  actorUserId: string;
  economicType: LedgerEconomicType;
  amount: string;
  currency: string;
  postedOn: string;
  effectiveAt: Date;
  createdAt: Date;
  accountId?: string;
  sourceAccountId?: string;
  destinationAccountId?: string;
  adjustmentDirection?: AdjustmentDirection;
  categoryId?: string;
  note?: string;
  sourceModule: LedgerSourceModule;
  sourceReferenceId?: string;
  idempotencyKeyHash: string;
  reversesEntryId?: string;
  replacesEntryId?: string;
}

export interface JournalListQuery {
  dateFrom?: string;
  dateTo?: string;
  limit: number;
  cursor?: string;
}

export interface JournalListPage {
  items: JournalEntry[];
  nextCursor: string | null;
}
