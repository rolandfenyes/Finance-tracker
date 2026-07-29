import type { FxConversionStatus } from '../currency/currency.types';
import type { LedgerEconomicType, LedgerSourceModule } from '../ledger/ledger.types';

export const reportActivityKinds = [
  'income',
  'expense',
  'transfer',
  'adjustment',
  'trade_cash',
] as const;
export type ReportActivityKind = (typeof reportActivityKinds)[number];

export interface ReportFilters {
  kind?: ReportActivityKind;
  categoryId?: string;
  currency?: string;
  query?: string;
  minAmount?: string;
  maxAmount?: string;
}

export interface ReportConversionSummary {
  status: FxConversionStatus;
  complete: boolean;
  includedSourceCount: number;
  unavailableSourceCount: number;
  staleSourceCount: number;
  providers: string[];
  oldestRateAt: string | null;
  newestFetchedAt: string | null;
}

export interface ReportSummary {
  currency: string;
  income: string;
  expense: string;
  transfer: string;
  adjustmentNet: string;
  tradeCashNet: string;
  netCashFlow: string;
  conversion: ReportConversionSummary;
}

export interface PostedAggregateRow {
  income: string;
  expense: string;
  transfer: string;
  adjustmentNet: string;
  tradeCashNet: string;
  netCashFlow: string;
  includedSourceCount: number;
  unavailableSourceCount: number;
  staleSourceCount: number;
  providers: string[];
  oldestRateAt: Date | null;
  newestFetchedAt: Date | null;
}

export interface ReportActivityItem {
  sourceEntryId: string;
  economicType: LedgerEconomicType;
  kind: ReportActivityKind;
  categoryId: string | null;
  note: string | null;
  source: { module: LedgerSourceModule; referenceId: string | null };
  postedOn: string;
  effectiveAt: string;
  amount: string;
  currency: string;
  convertedAmount?: string;
  reportingCurrency: string;
  conversionStatus: FxConversionStatus;
  provider: string | null;
  rateAt: string | null;
  fetchedAt: string | null;
  reversesEntryId: string | null;
}

export interface ReportActivityPage {
  items: ReportActivityItem[];
  nextCursor: string | null;
}

export interface ForecastSource {
  sourceKind: 'basic_income' | 'recurring_rule';
  sourceId: string;
  sourceEntryId: string;
  label: string;
  occurrenceOn: string;
  kind: 'income' | 'expense' | 'transfer';
  categoryId: string | null;
  amount: string;
  currency: string;
  convertedAmount?: string;
  reportingCurrency: string;
  conversionStatus: FxConversionStatus;
  provider: string | null;
  rateAt: string | null;
  fetchedAt: string | null;
}

export interface BasicIncomeForecastRow {
  id: string;
  label: string;
  amount: string;
  currency: string;
  validFrom: string;
  validTo: string | null;
  categoryId: string | null;
}

export interface RecurringRuleForecastRow {
  id: string;
  title: string;
  amount: string;
  currency: string;
  economicType: 'income' | 'expense' | 'transfer';
  startsOn: string;
  rrule: string;
  categoryId: string | null;
}

export interface ForecastReport {
  summary: ReportSummary;
  sources: ForecastSource[];
}

export interface ReportPeriod {
  first: string;
  last: string;
  year: number;
  month?: number;
  timeZone: string;
}

export interface ReportPeriodResult {
  period: ReportPeriod;
  posted: ReportSummary;
  forecast: ForecastReport;
  combinedProjection: ReportSummary;
}
