export const PRIVACY_MANIFEST_VERSION = 1;

export type DataLifecycleClassification =
  | 'user_export_delete'
  | 'user_export_retain_audit'
  | 'security_internal_delete'
  | 'security_internal_retain'
  | 'shared_reference'
  | 'system_configuration'
  | 'global_job_state';

export interface ExportDatasetDefinition {
  key: string;
  table: string;
  columns: readonly string[];
  ownerWhere: string;
  csv: boolean;
}

export interface DatabaseLifecycleDefinition {
  table: string;
  domain: string;
  classification: DataLifecycleClassification;
  exportDataset?: string;
  deletion: 'delete' | 'retain_pseudonymous' | 'not_user_owned';
  notes: string;
}

const direct = (
  key: string,
  table: string,
  columns: string,
  csv = true,
): ExportDatasetDefinition => ({
  key,
  table,
  columns: columns.split(' '),
  ownerWhere: 'user_id = $1',
  csv,
});

export const EXPORT_DATASETS: readonly ExportDatasetDefinition[] = [
  {
    key: 'profile',
    table: 'users',
    columns: [
      'id',
      'email',
      'full_name',
      'date_of_birth',
      'role',
      'status',
      'email_verified_at',
      'created_at',
      'updated_at',
      'theme',
      'desired_language',
      'onboard_step',
      'needs_tutorial',
      'tutorial_seen',
    ],
    ownerWhere: 'id = $1',
    csv: false,
  },
  direct('user_currencies', 'user_currencies', 'code is_main created_at'),
  direct('budget_rules', 'budget_rules', 'id label percent target_hint created_at updated_at'),
  direct(
    'categories',
    'categories',
    'id label kind color budget_rule_id system_key protected created_at updated_at',
  ),
  direct(
    'basic_incomes',
    'basic_incomes',
    'id label amount currency valid_from valid_to category_id created_at updated_at',
  ),
  direct('ledger_accounts', 'ledger_accounts', 'id kind module_reference_id created_at'),
  direct(
    'journal_entries',
    'journal_entries',
    'id economic_type category_id note source_module source_reference_id posted_on effective_at created_at reverses_entry_id replaces_entry_id',
  ),
  direct('journal_legs', 'journal_legs', 'id entry_id account_id side amount currency created_at'),
  direct(
    'fx_conversion_snapshots',
    'fx_conversion_snapshots',
    'id entry_id source_currency target_currency source_amount converted_amount source_rate target_rate conversion_rate provider rate_at fetched_at status precision rounding_mode created_at',
  ),
  direct(
    'recurring_rules',
    'recurring_rules',
    'id title amount currency economic_type starts_on rrule category_id goal_id loan_id investment_id created_at updated_at',
  ),
  direct(
    'recurring_occurrences',
    'recurring_occurrences',
    'id rule_id due_on economic_type amount currency category_id state created_at',
  ),
  direct(
    'goals',
    'goals',
    'id title target_amount currency deadline priority status category_id archived_at created_at updated_at',
  ),
  direct(
    'goal_contributions',
    'goal_contributions',
    'id goal_id journal_entry_id amount currency goal_amount goal_currency occurred_on note reversed_by_journal_entry_id corrects_contribution_id created_at',
  ),
  direct(
    'emergency_reserves',
    'emergency_reserves',
    'target_amount currency reserve_account_id linked_investment_account_id created_at updated_at',
  ),
  direct(
    'emergency_reserve_movements',
    'emergency_reserve_movements',
    'id journal_entry_id holding_account_id direction amount currency reserve_amount reserve_currency occurred_on note reversed_by_journal_entry_id created_at',
  ),
  direct(
    'loans',
    'loans',
    'id title principal currency nominal_annual_rate term_months starts_on ends_on payment_day extra_payment_scenario insurance_monthly estimate_version liability_account_id completed_at archived_at created_at updated_at',
  ),
  direct(
    'loan_payments',
    'loan_payments',
    'id loan_id journal_entry_id amount currency principal_component interest_component fee_component loan_principal_component loan_interest_component loan_fee_component loan_currency conversion_status conversion_rate conversion_provider rate_at fetched_at paid_on source recurring_occurrence_id note reversed_by_journal_entry_id corrects_payment_id created_at',
  ),
  direct(
    'investments',
    'investments',
    'id type name provider identifier notes currency scenario_annual_rate scenario_frequency scenario_version account_id created_at updated_at',
  ),
  direct(
    'investment_movements',
    'investment_movements',
    'id investment_id journal_entry_id direction amount currency investment_amount investment_currency occurred_on note reversed_by_journal_entry_id created_at',
  ),
  direct('securities_portfolios', 'securities_portfolios', 'id cash_account_id created_at'),
  direct(
    'securities_positions',
    'securities_positions',
    'id instrument_id holding_account_id quantity remaining_cost_local remaining_cost_base local_currency base_currency created_at updated_at',
  ),
  direct(
    'securities_trades',
    'securities_trades',
    'id position_id instrument_id side quantity unit_price fee currency notional notional_base fee_base base_currency conversion_status conversion_rate conversion_provider rate_at fetched_at executed_at traded_on note cash_journal_entry_id fee_journal_entry_id reversed_by_cash_journal_entry_id reversed_by_fee_journal_entry_id created_at',
  ),
  direct(
    'securities_lots',
    'securities_lots',
    'id position_id instrument_id buy_trade_id original_quantity remaining_quantity total_cost_local total_cost_base currency base_currency opened_at created_at',
  ),
  direct(
    'securities_lot_consumptions',
    'securities_lot_consumptions',
    'id sell_trade_id lot_id quantity cost_local cost_base created_at',
  ),
  direct(
    'securities_realized_results',
    'securities_realized_results',
    'id instrument_id sell_trade_id quantity proceeds_local cost_local fees_local realized_local proceeds_base cost_base fees_base realized_base currency base_currency method closed_at created_at',
  ),
  direct(
    'securities_cash_movements',
    'securities_cash_movements',
    'id direction amount currency occurred_on note journal_entry_id reversed_by_journal_entry_id created_at',
  ),
  direct('securities_watchlist', 'securities_watchlist', 'instrument_id created_at'),
  direct(
    'securities_imports',
    'securities_imports',
    'id fingerprint status row_count valid_count error_count ignored_count rows committed_at created_at',
  ),
  direct(
    'securities_refresh_jobs',
    'securities_refresh_jobs',
    'id status attempt_count max_attempts error_code created_at started_at finished_at',
  ),
  direct(
    'securities_clear_requests',
    'securities_clear_requests',
    'id status trade_count cash_count created_at completed_at',
  ),
  direct('feedback', 'feedback', 'id kind title message severity status created_at updated_at'),
  {
    key: 'feedback_responses',
    table: 'feedback_responses',
    columns: ['id', 'feedback_id', 'message', 'created_at', 'updated_at'],
    ownerWhere:
      'EXISTS (SELECT 1 FROM mymoneymap.feedback f WHERE f.id = feedback_id AND f.user_id = $1)',
    csv: true,
  },
  direct(
    'user_subscriptions',
    'user_subscriptions',
    'id plan_code plan_name status billing_interval interval_count amount currency started_at current_period_start current_period_end cancel_at canceled_at trial_ends_at notes created_at updated_at',
  ),
  direct(
    'user_invoices',
    'user_invoices',
    'id subscription_id invoice_number status total_amount currency issued_at due_at paid_at failure_reason refund_reason notes created_at updated_at',
  ),
  direct(
    'user_payments',
    'user_payments',
    'id invoice_id type status amount currency gateway transaction_reference failure_reason notes processed_at created_at updated_at',
  ),
  direct('email_preferences', 'user_email_preferences', 'educational_enabled updated_at', false),
  direct(
    'email_deliveries',
    'email_deliveries',
    'id correlation_id recipient_email template_code template_version locale classification template_data provenance status attempt_count max_attempts error_code created_at queued_at started_at delivered_at failed_at',
    false,
  ),
  direct(
    'privacy_export_history',
    'privacy_export_requests',
    'id manifest_version status attempt_count max_attempts error_code created_at started_at completed_at expires_at',
    false,
  ),
  {
    key: 'login_security_activity',
    table: 'login_audit_events',
    columns: ['id', 'outcome', 'method', 'created_at'],
    ownerWhere: 'user_id = $1',
    csv: true,
  },
  {
    key: 'privileged_activity',
    table: 'privileged_audit_events',
    columns: ['id', 'action', 'target_type', 'target_id', 'details', 'created_at'],
    ownerWhere: "target_type = 'user' AND target_id = $1",
    csv: false,
  },
  {
    key: 'privacy_security_activity',
    table: 'security_audit_events',
    columns: ['id', 'action', 'target_type', 'target_id', 'details', 'created_at'],
    ownerWhere: 'subject_user_id = $1',
    csv: false,
  },
] as const;

const exported = (table: string, domain: string, dataset: string): DatabaseLifecycleDefinition => ({
  table,
  domain,
  classification: 'user_export_delete',
  exportDataset: dataset,
  deletion: 'delete',
  notes:
    'Owned rows are exported through the named safe-column dataset and deleted with the account.',
});

const shared = (
  table: string,
  domain: string,
  classification: 'shared_reference' | 'system_configuration' | 'global_job_state',
): DatabaseLifecycleDefinition => ({
  table,
  domain,
  classification,
  deletion: 'not_user_owned',
  notes: 'No user-owned row; lifecycle is outside an individual account request.',
});

export const DATABASE_LIFECYCLE_MANIFEST: readonly DatabaseLifecycleDefinition[] = [
  exported('users', 'identity', 'profile'),
  {
    table: 'email_verification_tokens',
    domain: 'identity',
    classification: 'security_internal_delete',
    deletion: 'delete',
    notes: 'Token hashes are excluded from export and deleted.',
  },
  {
    table: 'passkeys',
    domain: 'identity',
    classification: 'security_internal_delete',
    deletion: 'delete',
    notes: 'Credential identifiers, public keys, counters, and challenge internals are excluded.',
  },
  {
    table: 'account_recovery_requests',
    domain: 'identity',
    classification: 'security_internal_delete',
    deletion: 'delete',
    notes: 'Recovery token hashes and pending security state are excluded and deleted.',
  },
  {
    table: 'idempotency_keys',
    domain: 'platform',
    classification: 'security_internal_delete',
    deletion: 'delete',
    notes: 'Request and key hashes are internal and deleted for the user scope.',
  },
  {
    table: 'login_audit_events',
    domain: 'audit',
    classification: 'user_export_retain_audit',
    exportDataset: 'login_security_activity',
    deletion: 'retain_pseudonymous',
    notes:
      'Only outcome, method, and time export; hashes remain under an unapproved retention policy.',
  },
  {
    table: 'privileged_audit_events',
    domain: 'audit',
    classification: 'user_export_retain_audit',
    exportDataset: 'privileged_activity',
    deletion: 'retain_pseudonymous',
    notes: 'Immutable privileged evidence is retained; no purge period is invented.',
  },
  {
    table: 'security_audit_events',
    domain: 'audit',
    classification: 'user_export_retain_audit',
    exportDataset: 'privacy_security_activity',
    deletion: 'retain_pseudonymous',
    notes: 'Immutable privacy/security evidence is retained with a subject hash.',
  },
  ...[
    ['user_currencies', 'currency', 'user_currencies'],
    ['budget_rules', 'budgeting', 'budget_rules'],
    ['categories', 'budgeting', 'categories'],
    ['basic_incomes', 'budgeting', 'basic_incomes'],
    ['ledger_accounts', 'ledger', 'ledger_accounts'],
    ['journal_entries', 'ledger', 'journal_entries'],
    ['journal_legs', 'ledger', 'journal_legs'],
    ['fx_conversion_snapshots', 'currency', 'fx_conversion_snapshots'],
    ['recurring_rules', 'recurrence', 'recurring_rules'],
    ['recurring_occurrences', 'recurrence', 'recurring_occurrences'],
    ['goals', 'goals', 'goals'],
    ['goal_contributions', 'goals', 'goal_contributions'],
    ['emergency_reserves', 'emergency_fund', 'emergency_reserves'],
    ['emergency_reserve_movements', 'emergency_fund', 'emergency_reserve_movements'],
    ['loans', 'loans', 'loans'],
    ['loan_payments', 'loans', 'loan_payments'],
    ['investments', 'investments', 'investments'],
    ['investment_movements', 'investments', 'investment_movements'],
    ['securities_portfolios', 'securities', 'securities_portfolios'],
    ['securities_positions', 'securities', 'securities_positions'],
    ['securities_trades', 'securities', 'securities_trades'],
    ['securities_lots', 'securities', 'securities_lots'],
    ['securities_lot_consumptions', 'securities', 'securities_lot_consumptions'],
    ['securities_realized_results', 'securities', 'securities_realized_results'],
    ['securities_cash_movements', 'securities', 'securities_cash_movements'],
    ['securities_watchlist', 'securities', 'securities_watchlist'],
    ['securities_imports', 'securities', 'securities_imports'],
    ['securities_refresh_jobs', 'securities', 'securities_refresh_jobs'],
    ['securities_clear_requests', 'securities', 'securities_clear_requests'],
    ['feedback', 'feedback', 'feedback'],
    ['feedback_responses', 'feedback', 'feedback_responses'],
    ['user_subscriptions', 'billing', 'user_subscriptions'],
    ['user_invoices', 'billing', 'user_invoices'],
    ['user_payments', 'billing', 'user_payments'],
    ['user_email_preferences', 'notifications', 'email_preferences'],
    ['email_deliveries', 'notifications', 'email_deliveries'],
    ['privacy_export_requests', 'privacy', 'privacy_export_history'],
  ].map(([table, domain, dataset]) => exported(table!, domain!, dataset!)),
  {
    table: 'privacy_export_artifacts',
    domain: 'privacy',
    classification: 'security_internal_delete',
    deletion: 'delete',
    notes: 'Object keys and checksums are internal; objects and metadata are deleted.',
  },
  {
    table: 'privacy_deletion_requests',
    domain: 'privacy',
    classification: 'security_internal_retain',
    deletion: 'retain_pseudonymous',
    notes: 'The operational outcome survives with only the stable subject hash.',
  },
  {
    table: 'email_suppressions',
    domain: 'notifications',
    classification: 'security_internal_retain',
    deletion: 'retain_pseudonymous',
    notes:
      'Normalized-email hashes remain to prevent unsafe redelivery; no purge period is invented.',
  },
  ...[
    ['currencies', 'currency', 'shared_reference'],
    ['fx_quotes', 'currency', 'shared_reference'],
    ['securities_instruments', 'securities', 'shared_reference'],
    ['securities_quotes', 'securities', 'shared_reference'],
    ['securities_daily_prices', 'securities', 'shared_reference'],
    ['billing_plans', 'billing', 'shared_reference'],
    ['billing_promotions', 'billing', 'shared_reference'],
    ['system_settings', 'administration', 'system_configuration'],
    ['api_integrations', 'administration', 'system_configuration'],
    ['email_templates', 'notifications', 'system_configuration'],
    ['email_channel_settings', 'notifications', 'system_configuration'],
    ['recurrence_job_executions', 'recurrence', 'global_job_state'],
    ['recurrence_job_events', 'recurrence', 'global_job_state'],
    ['legacy_migration_batches', 'legacy_migration', 'global_job_state'],
    ['legacy_migration_row_ledger', 'legacy_migration', 'global_job_state'],
    ['legacy_migration_quarantine', 'legacy_migration', 'global_job_state'],
    ['legacy_migration_reconciliation', 'legacy_migration', 'global_job_state'],
  ].map(([table, domain, classification]) =>
    shared(
      table!,
      domain!,
      classification as 'shared_reference' | 'system_configuration' | 'global_job_state',
    ),
  ),
] as const;

export const NON_DATABASE_LIFECYCLE_MANIFEST = [
  {
    category: 'redis_sessions',
    deletion: 'delete',
    notes: 'Revoke every registered server-side session and its user registry.',
  },
  {
    category: 'redis_login_rate_cache',
    deletion: 'delete',
    notes:
      'Delete the normalized account-key throttle entry; shared IP throttles are not user-owned.',
  },
  {
    category: 'bullmq_user_jobs',
    deletion: 'delete',
    notes: 'Remove export, email, and securities jobs identified by persisted queue job IDs.',
  },
  {
    category: 'private_export_objects',
    deletion: 'delete',
    notes: 'Delete every manifest-recorded private object before account-row deletion.',
  },
  {
    category: 'application_logs_and_traces',
    deletion: 'not_individually_addressable',
    notes:
      'Logs must be PII-safe; no source payload is logged and no retention period is invented.',
  },
  {
    category: 'database_and_object_storage_backups',
    deletion: 'approved_restore_lifecycle_only',
    notes:
      'Step 19 does not purge backups or claim a deadline; Step 21 must approve restore/retention.',
  },
] as const;

export const ACCOUNT_DELETION_ORDER = [
  'privacy_export_artifacts',
  'privacy_export_requests',
  'email_deliveries',
  'user_email_preferences',
  'user_payments',
  'user_invoices',
  'user_subscriptions',
  'securities_lot_consumptions',
  'securities_lots',
  'securities_realized_results',
  'securities_cash_movements',
  'securities_trades',
  'securities_positions',
  'securities_watchlist',
  'securities_imports',
  'securities_refresh_jobs',
  'securities_clear_requests',
  'securities_portfolios',
  'loan_payments',
  'goal_contributions',
  'emergency_reserve_movements',
  'investment_movements',
  'recurring_occurrences',
  'recurring_rules',
  'fx_conversion_snapshots',
  'journal_legs',
  'journal_entries',
  'loans',
  'goals',
  'emergency_reserves',
  'investments',
  'basic_incomes',
  'categories',
  'budget_rules',
  'ledger_accounts',
  'user_currencies',
  'feedback',
  'account_recovery_requests',
  'email_verification_tokens',
  'passkeys',
  'idempotency_keys',
] as const;
