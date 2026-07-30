import type { ColumnType } from 'kysely';
import type { JsonValue } from '../events/outbox.port';

type DatabaseTimestamp = ColumnType<Date, Date, Date>;
type GeneratedTimestamp = ColumnType<Date, Date | undefined, Date>;
type DatabaseDecimal = ColumnType<string, string, string>;
type DatabaseDate = ColumnType<string | Date, string, string>;

export interface UsersTable {
  id: string;
  email: string;
  password_hash: string;
  full_name: string;
  date_of_birth: string;
  role: 'free' | 'premium' | 'admin';
  status: 'active' | 'inactive';
  email_verified_at: DatabaseTimestamp | null;
  created_at: GeneratedTimestamp;
  updated_at: DatabaseTimestamp;
  theme: string;
  desired_language: string;
  onboard_step: number;
  needs_tutorial: boolean;
  tutorial_seen: boolean;
}

export interface EmailVerificationTokensTable {
  id: string;
  user_id: string;
  token_hash: string;
  expires_at: DatabaseTimestamp;
  consumed_at: DatabaseTimestamp | null;
  created_at: GeneratedTimestamp;
}

export interface PasskeysTable {
  id: string;
  user_id: string;
  credential_id: string;
  public_key: Uint8Array;
  counter: ColumnType<string, string, string>;
  revision: ColumnType<string, string, string>;
  transports: string[];
  device_type: string;
  backed_up: boolean;
  label: string;
  created_at: GeneratedTimestamp;
  last_used_at: DatabaseTimestamp | null;
}

export interface LoginAuditEventsTable {
  id: string;
  user_id: string | null;
  email_hash: string;
  outcome: 'success' | 'failure' | 'throttled';
  method: 'password' | 'passkey';
  ip_hash: string;
  user_agent_hash: string;
  created_at: GeneratedTimestamp;
}

export interface FeedbackTable {
  id: string;
  user_id: string;
  kind: 'bug' | 'idea';
  title: string;
  message: string;
  severity: 'low' | 'medium' | 'high' | null;
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface FeedbackResponsesTable {
  id: string;
  feedback_id: string;
  admin_id: string;
  message: string;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface SystemSettingsTable {
  id: number;
  site_name: string;
  primary_url: string | null;
  support_email: string | null;
  contact_email: string | null;
  logo_url: string | null;
  favicon_url: string | null;
  maintenance_mode: boolean;
  maintenance_message: string | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface ApiIntegrationsTable {
  id: string;
  name: string;
  service: string;
  api_key_encrypted: string;
  status: 'active' | 'inactive';
  metadata: JsonValue;
  last_used_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface AccountRecoveryRequestsTable {
  id: string;
  user_id: string;
  requested_by_admin_id: string;
  kind: 'password_reset' | 'email_change';
  token_hash: string;
  pending_email: string | null;
  expires_at: DatabaseTimestamp;
  consumed_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
}

export interface PrivilegedAuditEventsTable {
  id: string;
  actor_user_id: string;
  action:
    | 'feedback.updated'
    | 'feedback.responded'
    | 'system.settings_updated'
    | 'integration.upserted'
    | 'integration.deleted'
    | 'user.role_updated'
    | 'user.status_updated'
    | 'user.password_reset_requested'
    | 'user.email_verification_requested'
    | 'user.email_change_requested';
  target_type: 'feedback' | 'system_settings' | 'integration' | 'user';
  target_id: string | null;
  details: JsonValue;
  created_at: DatabaseTimestamp;
}

export interface IdempotencyKeysTable {
  scope_id: string;
  operation: string;
  key_hash: string;
  request_hash: string;
  status: 'completed' | 'in_progress';
  response: Readonly<Record<string, JsonValue>> | null;
  created_at: DatabaseTimestamp;
  completed_at: DatabaseTimestamp | null;
}

export interface LedgerAccountsTable {
  id: string;
  user_id: string;
  kind:
    | 'cash'
    | 'goal'
    | 'emergency_reserve'
    | 'investment'
    | 'loan_liability'
    | 'securities_cash'
    | 'securities_holding';
  module_reference_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface JournalEntriesTable {
  id: string;
  user_id: string;
  economic_type:
    | 'external_income'
    | 'external_expense'
    | 'internal_transfer'
    | 'adjustment'
    | 'fee'
    | 'interest'
    | 'dividend'
    | 'loan_repayment'
    | 'trade_cash';
  category_id: string | null;
  note: string | null;
  source_module:
    | 'manual'
    | 'scheduling'
    | 'goals'
    | 'emergency_fund'
    | 'loans'
    | 'investments'
    | 'securities'
    | 'migration';
  source_reference_id: string | null;
  idempotency_key_hash: string;
  posted_on: DatabaseDate;
  effective_at: DatabaseTimestamp;
  created_at: DatabaseTimestamp;
  actor_user_id: string;
  reverses_entry_id: string | null;
  replaces_entry_id: string | null;
}

export interface JournalLegsTable {
  id: string;
  entry_id: string;
  user_id: string;
  account_id: string | null;
  side: 'debit' | 'credit';
  amount: DatabaseDecimal;
  currency: string;
  created_at: DatabaseTimestamp;
}

export interface CurrenciesTable {
  code: string;
  name: string;
  minor_unit: number;
  rounding_mode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN';
  active: boolean;
}

export interface UserCurrenciesTable {
  user_id: string;
  code: string;
  is_main: boolean;
  created_at: DatabaseTimestamp;
}

export interface FxQuotesTable {
  id: string;
  provider: string;
  base_code: string;
  quote_code: string;
  rate: DatabaseDecimal;
  observed_on: DatabaseDate;
  observed_at: DatabaseTimestamp;
  fetched_at: DatabaseTimestamp;
  quality: 'provider_observed' | 'legacy_imported';
  status: 'available' | 'rejected';
}

export interface FxConversionSnapshotsTable {
  id: string;
  entry_id: string;
  user_id: string;
  source_currency: string;
  target_currency: string;
  source_amount: DatabaseDecimal;
  converted_amount: DatabaseDecimal | null;
  source_rate: DatabaseDecimal | null;
  target_rate: DatabaseDecimal | null;
  conversion_rate: DatabaseDecimal | null;
  source_quote_id: string | null;
  target_quote_id: string | null;
  provider: string | null;
  rate_at: DatabaseTimestamp | null;
  fetched_at: DatabaseTimestamp | null;
  status: 'available' | 'stale' | 'unavailable';
  precision: number;
  rounding_mode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN';
  created_at: DatabaseTimestamp;
}

export interface BudgetRulesTable {
  id: string;
  user_id: string;
  label: string;
  percent: DatabaseDecimal;
  target_hint: string | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface CategoriesTable {
  id: string;
  user_id: string;
  label: string;
  kind: 'income' | 'spending';
  color: string;
  budget_rule_id: string | null;
  system_key: string | null;
  protected: boolean;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface BasicIncomesTable {
  id: string;
  user_id: string;
  label: string;
  amount: DatabaseDecimal;
  currency: string;
  valid_from: DatabaseDate;
  valid_to: DatabaseDate | null;
  category_id: string | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface RecurringRulesTable {
  id: string;
  user_id: string;
  title: string;
  amount: DatabaseDecimal;
  currency: string;
  economic_type: 'income' | 'expense' | 'transfer';
  starts_on: DatabaseDate;
  rrule: string;
  category_id: string | null;
  goal_id: string | null;
  loan_id: string | null;
  investment_id: string | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface InvestmentsTable {
  id: string;
  user_id: string;
  type: 'savings' | 'etf' | 'stock';
  name: string;
  provider: string | null;
  identifier: string | null;
  notes: string | null;
  currency: string;
  scenario_annual_rate: DatabaseDecimal | null;
  scenario_frequency: 'daily' | 'weekly' | 'monthly' | 'annual';
  scenario_version: 'nominal_compound_scenario_v1';
  account_id: string;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface InvestmentMovementsTable {
  id: string;
  user_id: string;
  investment_id: string;
  journal_entry_id: string;
  direction: 'deposit' | 'withdrawal';
  amount: DatabaseDecimal;
  currency: string;
  investment_amount: DatabaseDecimal;
  investment_currency: string;
  occurred_on: DatabaseDate;
  note: string | null;
  reversed_by_journal_entry_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface LoansTable {
  id: string;
  user_id: string;
  title: string;
  principal: DatabaseDecimal;
  currency: string;
  nominal_annual_rate: DatabaseDecimal;
  term_months: number;
  starts_on: DatabaseDate;
  ends_on: DatabaseDate | null;
  payment_day: number | null;
  extra_payment_scenario: DatabaseDecimal;
  insurance_monthly: DatabaseDecimal;
  estimate_version: 'standard_nominal_monthly_annuity_v1';
  liability_account_id: string;
  completed_at: DatabaseTimestamp | null;
  archived_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface LoanPaymentsTable {
  id: string;
  user_id: string;
  loan_id: string;
  journal_entry_id: string;
  amount: DatabaseDecimal;
  currency: string;
  principal_component: DatabaseDecimal;
  interest_component: DatabaseDecimal;
  fee_component: DatabaseDecimal;
  loan_principal_component: DatabaseDecimal;
  loan_interest_component: DatabaseDecimal;
  loan_fee_component: DatabaseDecimal;
  loan_currency: string;
  conversion_status: 'available' | 'stale';
  conversion_rate: DatabaseDecimal;
  conversion_provider: string;
  rate_at: DatabaseTimestamp;
  fetched_at: DatabaseTimestamp;
  paid_on: DatabaseDate;
  source: 'manual' | 'scheduled';
  recurring_occurrence_id: string | null;
  note: string | null;
  reversed_by_journal_entry_id: string | null;
  corrects_payment_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface EmergencyReservesTable {
  user_id: string;
  target_amount: DatabaseDecimal;
  currency: string;
  reserve_account_id: string;
  linked_investment_account_id: string | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface EmergencyReserveMovementsTable {
  id: string;
  user_id: string;
  journal_entry_id: string;
  holding_account_id: string;
  direction: 'contribution' | 'withdrawal';
  amount: DatabaseDecimal;
  currency: string;
  reserve_amount: DatabaseDecimal;
  reserve_currency: string;
  occurred_on: DatabaseDate;
  note: string | null;
  reversed_by_journal_entry_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface GoalsTable {
  id: string;
  user_id: string;
  title: string;
  target_amount: DatabaseDecimal;
  currency: string;
  deadline: DatabaseDate | null;
  priority: number;
  status: 'active' | 'paused' | 'completed';
  category_id: string | null;
  archived_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface GoalContributionsTable {
  id: string;
  user_id: string;
  goal_id: string;
  journal_entry_id: string;
  amount: DatabaseDecimal;
  currency: string;
  goal_amount: DatabaseDecimal;
  goal_currency: string;
  occurred_on: DatabaseDate;
  note: string | null;
  reversed_by_journal_entry_id: string | null;
  corrects_contribution_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface RecurrenceJobExecutionsTable {
  id: string;
  job_key: string;
  queue_job_id: string;
  due_through: DatabaseDate;
  status: 'queued' | 'running' | 'completed' | 'retryable_failed' | 'dead_letter';
  attempt_count: number;
  max_attempts: number;
  error_code: string | null;
  started_at: DatabaseTimestamp | null;
  finished_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface RecurrenceJobEventsTable {
  id: string;
  execution_id: string;
  status: 'queued' | 'running' | 'completed' | 'retryable_failed' | 'dead_letter';
  attempt: number;
  error_code: string | null;
  occurred_at: DatabaseTimestamp;
}

export interface RecurringOccurrencesTable {
  id: string;
  rule_id: string;
  user_id: string;
  due_on: DatabaseDate;
  economic_type: 'income' | 'expense' | 'transfer';
  amount: DatabaseDecimal;
  currency: string;
  category_id: string | null;
  state: 'forecast';
  job_execution_id: string;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesPortfoliosTable {
  id: string;
  user_id: string;
  cash_account_id: string;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesInstrumentsTable {
  id: string;
  symbol: string;
  market: string;
  exchange: string | null;
  name: string | null;
  currency: string;
  sector: string | null;
  industry: string | null;
  beta: DatabaseDecimal | null;
  metadata_provider: string | null;
  metadata_observed_at: DatabaseTimestamp | null;
  active: boolean;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface SecuritiesPositionsTable {
  id: string;
  user_id: string;
  instrument_id: string;
  holding_account_id: string;
  quantity: DatabaseDecimal;
  remaining_cost_local: DatabaseDecimal;
  remaining_cost_base: DatabaseDecimal;
  local_currency: string;
  base_currency: string;
  created_at: DatabaseTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface SecuritiesTradesTable {
  id: string;
  user_id: string;
  position_id: string;
  instrument_id: string;
  side: 'buy' | 'sell';
  quantity: DatabaseDecimal;
  unit_price: DatabaseDecimal;
  fee: DatabaseDecimal;
  currency: string;
  notional: DatabaseDecimal;
  notional_base: DatabaseDecimal;
  fee_base: DatabaseDecimal;
  base_currency: string;
  conversion_status: 'available' | 'stale';
  conversion_rate: DatabaseDecimal;
  conversion_provider: string;
  rate_at: DatabaseTimestamp;
  fetched_at: DatabaseTimestamp;
  executed_at: DatabaseTimestamp;
  traded_on: DatabaseDate;
  note: string | null;
  cash_journal_entry_id: string;
  fee_journal_entry_id: string | null;
  reversed_by_cash_journal_entry_id: string | null;
  reversed_by_fee_journal_entry_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesLotsTable {
  id: string;
  user_id: string;
  position_id: string;
  instrument_id: string;
  buy_trade_id: string;
  original_quantity: DatabaseDecimal;
  remaining_quantity: DatabaseDecimal;
  total_cost_local: DatabaseDecimal;
  total_cost_base: DatabaseDecimal;
  currency: string;
  base_currency: string;
  opened_at: DatabaseTimestamp;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesLotConsumptionsTable {
  id: string;
  user_id: string;
  sell_trade_id: string;
  lot_id: string;
  quantity: DatabaseDecimal;
  cost_local: DatabaseDecimal;
  cost_base: DatabaseDecimal;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesRealizedResultsTable {
  id: string;
  user_id: string;
  instrument_id: string;
  sell_trade_id: string;
  quantity: DatabaseDecimal;
  proceeds_local: DatabaseDecimal;
  cost_local: DatabaseDecimal;
  fees_local: DatabaseDecimal;
  realized_local: DatabaseDecimal;
  proceeds_base: DatabaseDecimal;
  cost_base: DatabaseDecimal;
  fees_base: DatabaseDecimal;
  realized_base: DatabaseDecimal;
  currency: string;
  base_currency: string;
  method: 'FIFO';
  closed_at: DatabaseTimestamp;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesCashMovementsTable {
  id: string;
  user_id: string;
  direction: 'deposit' | 'withdrawal';
  amount: DatabaseDecimal;
  currency: string;
  occurred_on: DatabaseDate;
  note: string | null;
  journal_entry_id: string;
  reversed_by_journal_entry_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesQuotesTable {
  instrument_id: string;
  last: DatabaseDecimal | null;
  previous_close: DatabaseDecimal | null;
  day_high: DatabaseDecimal | null;
  day_low: DatabaseDecimal | null;
  volume: DatabaseDecimal | null;
  currency: string;
  provider: string;
  quote_at: DatabaseTimestamp | null;
  retrieved_at: DatabaseTimestamp;
  status: 'available' | 'delayed' | 'stale' | 'unavailable';
}

export interface SecuritiesDailyPricesTable {
  id: string;
  instrument_id: string;
  trading_on: DatabaseDate;
  open: DatabaseDecimal | null;
  high: DatabaseDecimal | null;
  low: DatabaseDecimal | null;
  close: DatabaseDecimal;
  volume: DatabaseDecimal | null;
  currency: string;
  provider: string;
  observed_at: DatabaseTimestamp;
  retrieved_at: DatabaseTimestamp;
}

export interface SecuritiesWatchlistTable {
  user_id: string;
  instrument_id: string;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesImportsTable {
  id: string;
  user_id: string;
  fingerprint: string;
  status: 'preview' | 'committed';
  row_count: number;
  valid_count: number;
  error_count: number;
  ignored_count: number;
  rows: JsonValue;
  committed_at: DatabaseTimestamp | null;
  created_at: DatabaseTimestamp;
}

export interface SecuritiesRefreshJobsTable {
  id: string;
  user_id: string;
  queue_job_id: string;
  status: 'queued' | 'running' | 'completed' | 'retryable_failed' | 'dead_letter';
  attempt_count: number;
  max_attempts: number;
  error_code: string | null;
  created_at: DatabaseTimestamp;
  started_at: DatabaseTimestamp | null;
  finished_at: DatabaseTimestamp | null;
}

export interface SecuritiesClearRequestsTable {
  id: string;
  user_id: string;
  status: 'completed';
  trade_count: number;
  cash_count: number;
  created_at: DatabaseTimestamp;
  completed_at: DatabaseTimestamp;
}

export interface DatabaseSchema {
  'mymoneymap.idempotency_keys': IdempotencyKeysTable;
  'mymoneymap.users': UsersTable;
  'mymoneymap.email_verification_tokens': EmailVerificationTokensTable;
  'mymoneymap.passkeys': PasskeysTable;
  'mymoneymap.login_audit_events': LoginAuditEventsTable;
  'mymoneymap.feedback': FeedbackTable;
  'mymoneymap.feedback_responses': FeedbackResponsesTable;
  'mymoneymap.system_settings': SystemSettingsTable;
  'mymoneymap.api_integrations': ApiIntegrationsTable;
  'mymoneymap.account_recovery_requests': AccountRecoveryRequestsTable;
  'mymoneymap.privileged_audit_events': PrivilegedAuditEventsTable;
  'mymoneymap.ledger_accounts': LedgerAccountsTable;
  'mymoneymap.journal_entries': JournalEntriesTable;
  'mymoneymap.journal_legs': JournalLegsTable;
  'mymoneymap.currencies': CurrenciesTable;
  'mymoneymap.user_currencies': UserCurrenciesTable;
  'mymoneymap.fx_quotes': FxQuotesTable;
  'mymoneymap.fx_conversion_snapshots': FxConversionSnapshotsTable;
  'mymoneymap.budget_rules': BudgetRulesTable;
  'mymoneymap.categories': CategoriesTable;
  'mymoneymap.basic_incomes': BasicIncomesTable;
  'mymoneymap.recurring_rules': RecurringRulesTable;
  'mymoneymap.emergency_reserves': EmergencyReservesTable;
  'mymoneymap.emergency_reserve_movements': EmergencyReserveMovementsTable;
  'mymoneymap.goals': GoalsTable;
  'mymoneymap.goal_contributions': GoalContributionsTable;
  'mymoneymap.loans': LoansTable;
  'mymoneymap.loan_payments': LoanPaymentsTable;
  'mymoneymap.investments': InvestmentsTable;
  'mymoneymap.investment_movements': InvestmentMovementsTable;
  'mymoneymap.recurrence_job_executions': RecurrenceJobExecutionsTable;
  'mymoneymap.recurrence_job_events': RecurrenceJobEventsTable;
  'mymoneymap.recurring_occurrences': RecurringOccurrencesTable;
  'mymoneymap.securities_portfolios': SecuritiesPortfoliosTable;
  'mymoneymap.securities_instruments': SecuritiesInstrumentsTable;
  'mymoneymap.securities_positions': SecuritiesPositionsTable;
  'mymoneymap.securities_trades': SecuritiesTradesTable;
  'mymoneymap.securities_lots': SecuritiesLotsTable;
  'mymoneymap.securities_lot_consumptions': SecuritiesLotConsumptionsTable;
  'mymoneymap.securities_realized_results': SecuritiesRealizedResultsTable;
  'mymoneymap.securities_cash_movements': SecuritiesCashMovementsTable;
  'mymoneymap.securities_quotes': SecuritiesQuotesTable;
  'mymoneymap.securities_daily_prices': SecuritiesDailyPricesTable;
  'mymoneymap.securities_watchlist': SecuritiesWatchlistTable;
  'mymoneymap.securities_imports': SecuritiesImportsTable;
  'mymoneymap.securities_refresh_jobs': SecuritiesRefreshJobsTable;
  'mymoneymap.securities_clear_requests': SecuritiesClearRequestsTable;
}
