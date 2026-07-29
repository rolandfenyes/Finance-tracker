# Step 00 — Legacy Data to Target Model Map

## Purpose and boundary

This is the authoritative disposition map for the **configured legacy PostgreSQL schema inspected during Step 00**. It assigns every observed table/view and column to a target model, an owning implementation step, or an explicit removal/quarantine action.

It does not define final target DDL. Exact target column names, types, precision, constraints, indexes, and migration SQL belong to Steps 02–20. No legacy data may be transformed until the reconciliation and migration tests in Step 20 exist.

Disposition terms:

- **migrate:** transform the value into the named target model.
- **derive:** recompute from authoritative migrated records; do not trust the stored value.
- **retain:** keep the concept with validation or naming changes.
- **quarantine:** retain for reconciliation but do not expose as authoritative v1 data.
- **drop:** deliberately has no target data field.
- **do not migrate:** security secret/state must not cross into the new system.

All user-owned target records require a non-null owner and database-backed tenant integrity. All financial values require role-specific `NUMERIC` precision and application decimal types; the `numeric` legacy type does not authorize JavaScript `number`.

## Identity, access, and user settings

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `users` | `id`, `email`, `password_hash`, `full_name`, `created_at`, `date_of_birth`, `onboard_step`, `needs_tutorial`, `theme`, `tutorial_seen`, `email_verified_at`, `email_verification_token`, `email_verification_sent_at`, `desired_language`, `role`, `status`, `deactivated_at`, `full_name_search` | `id/email/password_hash/created_at/date_of_birth/full_name` → user/profile; onboarding and theme/language fields → onboarding/preferences; `email_verified_at` → verification state; fixed `role` and `status/deactivated_at` → entitlement/account status. Reissue verification tokens rather than migrating plaintext token state. Derive search representation; drop `full_name_search`. Validate role to `free`, `premium`, or `admin`. | 04–05, 20 |
| `user_remember_tokens` | `id`, `user_id`, `selector`, `token_hash`, `expires_at`, `created_at` | **Do not migrate.** Replace with Redis-backed rotating server sessions. Existing remembered logins are invalidated at cutover. | 04, 20 |
| `user_passkeys` | `id`, `user_id`, `credential_id`, `public_key_pem`, `sign_count`, `label`, `created_at`, `last_used` | Passkey credential model. Transform the maintained package's supported credential/public-key representation; preserve counter, label, and timestamps only after compatibility validation. Never describe this public credential material as a stored private key. | 04, 20 |
| `user_login_activity` | `id`, `user_id`, `email`, `success`, `method`, `ip_address`, `user_agent`, `created_at` | Security/audit event. Minimize duplicate email, normalize method/outcome, apply IP/user-agent retention and redaction policy. Rows with null owner remain security events, not user-owned financial data. | 04, 19–20 |
| `roles` | `id`, `slug`, `name`, `description`, `is_system`, `capabilities`, `created_at`, `updated_at` | **Drop as configurable authorization data.** V1 roles are fixed `free/premium/admin`; capability policy is code/config tested in Step 05. Preserve an archival snapshot only for reconciliation if any user role cannot map exactly. | 05, 20 |

## Currency and foreign exchange

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `currencies` | `code`, `name` | Currency reference. Validate ISO code and add explicit minor-unit/rounding metadata from an approved source during Step 07. | 07, 20 |
| `user_currencies` | `user_id`, `code`, `is_main` | User currency selection and main-currency preference. Add non-null ownership/FKs and a transaction-safe partial uniqueness invariant for one main currency. | 07, 20 |
| `fx_rates` | `rate_date`, `base_code`, `code`, `rate` | FX quote candidate. Add provider/source, provider timestamp, retrieval timestamp, and quality/staleness state. Reject invalid/non-positive rates. Legacy rows without provenance are labeled legacy/imported and must not masquerade as provider-verified facts. | 07, 20 |
| `v_fx_latest` | `code`, `asof`, `rate` | **Do not migrate.** Replace the generated-series view with a tested indexed as-of query or materialized read model. | 07, 20–21 |

## Ledger, planning, and recurrence

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `transactions` | `id`, `user_id`, `kind`, `category_id`, `amount`, `currency`, `occurred_on`, `note`, `created_at`, `updated_at`, `main_currency`, `fx_rate_to_main`, `amount_main`, `source`, `source_ref_id`, `locked`, `ef_tx_id` | Transform into immutable `journal_entries` plus balanced `journal_legs`, with category and source links. Preserve original identifiers in migration provenance. Reconcile `kind/source/source_ref_id/ef_tx_id` to distinguish external income/expense from internal transfers. Preserve valid FX snapshot data only with an explicit legacy provenance marker; otherwise mark conversion unavailable. `locked` does not replace immutability. | 06–07, 20 |
| `categories` | `id`, `user_id`, `label`, `kind`, `color`, `cashflow_rule_id`, `system_key`, `protected` | Category model plus budget-rule assignment. Enforce ownership, allowed economic classification, system-category invariants, and tenant-safe rule reference. | 08, 20 |
| `cashflow_rules` | `id`, `user_id`, `label`, `percent`, `target_hint` | Budget rule. Retain exact percent as decimal; expose aggregate over-allocation explicitly. `target_hint` remains descriptive text, not a recommendation or equal per-category cap. | 08, 20 |
| `basic_incomes` | `id`, `user_id`, `label`, `amount`, `currency`, `valid_from`, `valid_to`, `category_id` | Basic-income plan/forecast. Enforce positive amount, currency/category ownership, and valid date range. It is not a posted journal entry. | 08, 20 |
| `scheduled_payments` | `id`, `user_id`, `title`, `amount`, `currency`, `rrule`, `next_due`, `category_id`, `loan_id`, `goal_id`, `investment_id`, `archived_at` | Recurring rule with explicit economic type `income`, `expense`, or `transfer`, plus typed optional domain link. Existing generic unlinked rows migrate as expense; linked loan/goal/investment rows map to their approved transfer/repayment semantics. `next_due` is derived worker state, not evidence of a posted payment. | 09, 20 |

## Goals and emergency reserve

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `goals` | `id`, `user_id`, `title`, `target_amount`, `current_amount`, `currency`, `deadline`, `priority`, `status`, `archived_at`, `category_id` | Goal/bucket. Migrate descriptive and target fields. Derive balance from linked transfer legs; reconcile rather than trust `current_amount`. Auto-lock at target. Archive changes visibility only and never posts income. `category_id` is observed schema drift and must be reconciled against migration 036. | 11, 20 |
| `goal_contributions` | `id`, `user_id`, `goal_id`, `amount`, `currency`, `occurred_on`, `note`, `created_at` | Goal contribution link to an internal-transfer journal entry. Enforce owner consistency across user, goal, account, and journal. | 11, 20 |
| `goal_transactions` | `id`, `user_id`, `goal_id`, `occurred_on`, `amount`, `currency`, `note` | Legacy duplicate: **quarantine**, match/deduplicate against `goal_contributions` and transaction source links, then migrate only reconciled unique movements as transfer-linked contributions. | 11, 20 |
| `emergency_fund` | `user_id`, `target_amount`, `currency`, `total`, `investment_id` | Emergency-reserve bucket and manual target. Derive total from transfer-linked movements; do not trust `total`. `investment_id` becomes an explicitly validated bucket/account link or is quarantined if ambiguous. No “needs” or “good now” recommendation model. | 12, 20 |
| `emergency_fund_tx` | `id`, `user_id`, `occurred_on`, `kind`, `amount_native`, `currency_native`, `amount_main`, `main_currency`, `rate_used`, `note`, `investment_tx_id` | Emergency movement linked to balanced transfer legs. Preserve native amount/currency; accept main amount/rate only as legacy provenance after reconciliation. Link any valid investment movement atomically. | 12, 20 |
| `emergency_transactions` | `id`, `user_id`, `occurred_on`, `amount`, `kind`, `note` | Legacy duplicate: **quarantine**, deduplicate against `emergency_fund_tx`/transactions, and migrate only reconciled unique movements. Null owner rows cannot become active user data without deterministic ownership evidence. | 12, 20 |

## Loans and generic investments

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `loans` | `id`, `user_id`, `name`, `principal`, `interest_rate`, `start_date`, `end_date`, `payment_day`, `extra_payment`, `balance`, `scheduled_payment_id`, `currency`, `insurance_monthly`, `history_confirmed`, `finished_at`, `archived_at` | Loan liability plus versioned estimate assumptions. Preserve contractual inputs when valid; derive balance from opening liability and posted repayment legs. Treat `extra_payment` and computed schedule as scenarios. `history_confirmed` cannot synthesize posted history. Link recurrence through the target rule model. | 13, 20 |
| `loan_payments` | `id`, `loan_id`, `paid_on`, `amount`, `principal_component`, `interest_component`, `currency`, `transaction_id` | Posted repayment linked to journal entry and loan. Reconcile components and currency; never silently subtract a cross-currency amount. Missing explicit owner is recovered only through an owned loan with DB-enforced tenant integrity. | 13, 20 |
| `investments` | `id`, `user_id`, `type`, `name`, `provider`, `identifier`, `interest_rate`, `notes`, `created_at`, `updated_at`, `balance`, `currency`, `interest_frequency`, `stock_id`, `units` | Generic investment bucket/account and user-authored return scenario. Derive balance from movements. Rename deterministic `interest_rate/frequency` semantics to labeled scenario assumptions. `stock_id/units` are configured-schema drift absent from repository migrations: quarantine and reconcile to securities instruments/positions; do not silently copy. | 14–15, 20 |
| `investment_transactions` | `id`, `investment_id`, `user_id`, `amount`, `note`, `created_at` | Investment movement linked to balanced transfer journal entry. Infer date only from `created_at`; preserve signed legacy intent for reconciliation, then store explicit direction with positive exact amount. | 14, 20 |

## Securities

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `stocks` | `id`, `symbol`, `exchange`, `market`, `name`, `currency`, `sector`, `industry`, `beta`, `created_at`, `updated_at` | Shared instrument reference using canonical instrument/exchange identity. Preserve descriptive metadata only with provider/source provenance. Restrict/soft-retire deletion so user history cannot cascade. | 15, 20 |
| `stock_trades` | `id`, `user_id`, `symbol`, `trade_on`, `side`, `quantity`, `price`, `currency`, `stock_id`, `executed_at`, `fee`, `note`, `market`, `created_at`, `updated_at` | Immutable security trade linked to instrument and journal cash effect. Prefer canonical `stock_id`; reconcile redundant symbol/market. Enforce positive exact quantity/price, non-negative fee, ownership, atomic FIFO, oversell lock, reversal semantics, and acquisition-date FX snapshots. | 15, 20 |
| `stock_lots` | `id`, `position_id`, `qty_open`, `qty_closed`, `open_price`, `fee`, `opened_at`, `closed_at`, `created_at`, `updated_at` | FIFO lot state derived/validated from immutable trades. Rebuild in rehearsal and compare; do not trust impossible or negative quantities. Target lots retain originating trade and owner/instrument integrity. | 15, 20 |
| `stock_positions` | `id`, `user_id`, `stock_id`, `qty`, `avg_cost_ccy`, `avg_cost_currency`, `cash_impact_ccy`, `created_at`, `updated_at` | Derived position projection. Rebuild from trades/lots; do not migrate aggregate values as authority. | 15, 20 |
| `stock_realized_pl` | `id`, `user_id`, `stock_id`, `sell_trade_id`, `realized_pl_base`, `realized_pl_ccy`, `currency`, `method`, `qty_closed`, `closed_at`, `created_at` | Derived FIFO realized-result projection linked to the sell and consumed lots. Rebuild and reconcile; v1 method is FIFO only. | 15, 20 |
| `stock_cash_movements` | `id`, `user_id`, `amount`, `currency`, `executed_at`, `note`, `created_at` | Securities cash movement linked to journal entries. Reconcile manual deposits/withdrawals and trade-created movements; target trade cash links are mandatory and reverse atomically. | 15, 20 |
| `stock_portfolio_snapshots` | `id`, `user_id`, `snapshot_on`, `total_value_base`, `created_at` | Derived valuation snapshot with quote/FX provenance and unavailable/stale state. Legacy total without complete provenance is quarantine-only, not an authoritative valuation. | 15, 20 |
| `stock_prices_last` | `stock_id`, `last`, `prev_close`, `day_high`, `day_low`, `volume`, `provider_ts`, `stale`, `updated_at` | Latest quote cache/read model. Preserve only validated provider data; add retrieval/source metadata and explicit unavailable/stale semantics. Never substitute cost for missing quote. | 15, 20 |
| `price_daily` | `id`, `stock_id`, `date`, `open`, `high`, `low`, `close`, `volume`, `provider`, `created_at` | Daily price observation with canonical instrument, provider, retrieval timestamp, validation, and market-session semantics. | 15, 20 |
| `watchlist` | `id`, `user_id`, `stock_id`, `created_at` | Owned watchlist membership with tenant and instrument FKs/uniqueness. | 15, 20 |
| `user_settings_stocks` | `user_id`, `unrealized_method`, `realized_method`, `target_allocations`, `refresh_seconds` | Securities preferences. Force realized method to approved FIFO; validate remaining preferences and decimal allocations. Signals/allocations are descriptive, not advice. | 15, 20 |

## Feedback, administration, notifications, and billing records

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `feedback` | `id`, `user_id`, `kind`, `title`, `message`, `severity`, `status`, `created_at`, `updated_at` | Owned feedback record with constrained workflow/status. | 16, 20 |
| `feedback_responses` | `id`, `feedback_id`, `admin_id`, `message`, `created_at`, `updated_at` | Feedback response/audit record. Preserve nullable admin only when system-origin is explicit; otherwise quarantine unresolved authorship. | 16, 20 |
| `system_settings` | `id`, `site_name`, `primary_url`, `support_email`, `contact_email`, `logo_url`, `favicon_url`, `maintenance_mode`, `maintenance_message`, `created_at`, `updated_at` | Validated non-secret operational settings. Canonical URL is environment-controlled; database value cannot override trusted origin/security boundaries. | 16, 20 |
| `api_integrations` | `id`, `name`, `service`, `api_key_encrypted`, `status`, `metadata`, `last_used_at`, `created_at`, `updated_at` | Integration metadata/status. **Do not migrate encrypted key ciphertext** unless a separately approved secure re-encryption procedure can decrypt, rotate, and write to the target secret store without exposure. API responses are write-only/masked for secrets. | 16, 20 |
| `email_templates` | `id`, `code`, `name`, `subject`, `body`, `locale`, `last_tested_at`, `created_at`, `updated_at` | Versioned localized email template. Validate variables and rendering; test with synthetic data. | 18, 20 |
| `notification_channels` | `id`, `channel`, `name`, `is_enabled`, `config`, `created_at`, `updated_at` | Email-channel operational configuration only. Drop unsupported arbitrary SMS/push channels and quarantine their config; never migrate secrets embedded in JSON without review. | 18, 20 |
| `billing_plans` | `id`, `code`, `name`, `description`, `price`, `currency`, `billing_interval`, `interval_count`, `role_slug`, `trial_days`, `is_active`, `stripe_product_id`, `stripe_price_id`, `metadata`, `created_at`, `updated_at` | Administrative plan/catalog record. Preserve exact price/currency/interval and fixed-role mapping after validation. Stripe IDs remain optional inert references; no checkout/provider behavior is implied. | 17, 20 |
| `billing_promotions` | `id`, `code`, `name`, `description`, `discount_percent`, `discount_amount`, `currency`, `max_redemptions`, `redeem_by`, `trial_days`, `plan_code`, `stripe_coupon_id`, `stripe_promo_code_id`, `metadata`, `created_at`, `updated_at` | Administrative promotion record with exact decimal constraints and plan reference. Provider IDs remain inert metadata. | 17, 20 |
| `billing_settings` | `id`, `stripe_secret_key`, `stripe_publishable_key`, `stripe_webhook_secret`, `default_currency`, `created_at`, `updated_at` | **Do not migrate secret columns and do not create a target table.** V1 has no checkout/webhook. `default_currency` belongs in validated non-secret catalog configuration if needed. Rotate any real legacy secrets outside this implementation. | 17, 20 |
| `user_subscriptions` | `id`, `user_id`, `plan_code`, `plan_name`, `status`, `billing_interval`, `interval_count`, `amount`, `currency`, `started_at`, `current_period_start`, `current_period_end`, `cancel_at`, `canceled_at`, `trial_ends_at`, `notes`, `created_at`, `updated_at` | Administrative subscription/entitlement record only. Preserve historical plan snapshot and dates; do not claim self-service cancellation or provider synchronization. | 17, 20 |
| `user_invoices` | `id`, `user_id`, `subscription_id`, `invoice_number`, `status`, `total_amount`, `currency`, `issued_at`, `due_at`, `paid_at`, `failure_reason`, `refund_reason`, `notes`, `created_at`, `updated_at` | Administrative invoice record with exact amount, owner/subscription integrity, constrained lifecycle, and audit history. | 17, 20 |
| `user_payments` | `id`, `user_id`, `invoice_id`, `type`, `status`, `amount`, `currency`, `gateway`, `transaction_reference`, `failure_reason`, `notes`, `processed_at`, `created_at`, `updated_at` | Administrative payment record with exact amount and owner/invoice integrity. `gateway/reference` are records, not evidence of a provider integration. | 17, 20 |

## Learning, migration metadata, and target-only operational models

| Legacy relation | Observed columns | Target model and disposition | Owner |
|---|---|---|---:|
| `baby_steps` | `user_id`, `step`, `status`, `note` | **No active backend-v1 target.** Quarantine/export as legacy data until the owner separately approves a product feature; no current route/controller supports it. | 19–20 |
| `schema_migrations` | `filename`, `run_at` | Source migration evidence only. **Do not import into the new migration ledger.** Store the inspected legacy ledger and schema fingerprint in migration rehearsal artifacts. | 02, 20 |

The target also needs models with no direct legacy-table equivalent:

- accounts, journal entries, journal legs, reversals/corrections, and transfer/source links (Step 06);
- FX conversion snapshots and rounding results (Step 07);
- recurring occurrences, job executions, retry/dead-letter observability, idempotency records, and outbox records (Steps 03, 09, 18);
- export jobs/artifacts, deletion requests/executions, consent/policy records, and audit events (Step 19);
- migration batches, reconciliation results, exceptions, and source fingerprints (Step 20).

These are corrective infrastructure/domain models, not invented product features.

## Drift and reconciliation gates

Step 20 must stop rather than silently migrate when any of these gates fails:

1. The configured source schema fingerprint differs from the Step 00 inventory without an approved updated map.
2. `investments.stock_id` or `investments.units` cannot be deterministically reconciled with securities records.
3. `goals.category_id` and the recorded migration ledger disagree without an explicit repair record.
4. Duplicate goal/emergency movement tables cannot be matched without ambiguous double counting.
5. A user-owned record has no deterministic owner.
6. A denormalized balance differs from rebuilt journal/lot history outside the approved exact tolerance.
7. A currency, amount, quantity, date, or relationship violates the corrected target invariant.
8. A secret/token would be copied into application data or a migration artifact.

No source row is discarded merely because it cannot become active v1 data: unresolved rows go to a restricted reconciliation report/quarantine process with counts and reason codes, never logs containing raw financial or credential values.
