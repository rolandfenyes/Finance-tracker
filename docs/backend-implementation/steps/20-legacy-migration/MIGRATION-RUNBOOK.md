# Step 20 — Legacy Migration Rehearsal and Cutover Runbook

This runbook is an operational contract, not authorization to migrate production data. The Step 20 command reads the PHP/PostgreSQL source and writes only hashed migration-control, quarantine, and reconciliation records to the NestJS database. It never writes source data and never inserts into target business tables.

## Safety boundary

- `LEGACY_MIGRATION_ENABLED` is `false` by default.
- The source URL must identify a dedicated role with `CONNECT` and `SELECT` only. The extractor also starts `REPEATABLE READ READ ONLY` and verifies `transaction_read_only=on`.
- Rehearsal is the default and only normal mode.
- `cutover` records a cutover preflight batch only. It additionally requires `LEGACY_MIGRATION_CUTOVER_APPROVED=true`; it still does not import business rows.
- No email-verification token, remembered-login token, provider secret, billing secret, or API credential is copied into a plan or report.
- Administrator rows are quarantined for owner-approved recovery. No legacy administrator credential grants access to the new application.
- Reports contain stable hashes, counts, reason codes, domains, currencies, and decimal totals. They contain no email address, name, note, feedback text, template variables, credential, or raw financial row.

## Source versions

The extractor recognizes:

- the repository migration ledger through recorded `035_system_configuration.sql`;
- recorded `036_goal_category.sql`;
- the configured audit drift where `goals.category_id` and the untracked `investments.stock_id`/`investments.units` columns exist.

Missing required tables/columns, unknown tables/columns, or a migration ledger before `035` block the batch. The configured investment columns are recognized but their securities link/quantity values remain quarantined until independently reconciled.

## Explicit transformation boundaries

The executable catalog is `apps/api/src/legacy-migration/legacy-schema.manifest.ts`.

| Legacy area                                   | Target/disposition                                                                     |
| --------------------------------------------- | -------------------------------------------------------------------------------------- |
| Users                                         | normalized target users; safely reusable password hashes only; admins require recovery |
| Remember tokens and email-verification tokens | invalidated, never copied                                                              |
| Passkeys                                      | copied only when the stored public key parses as compatible SPKI material              |
| Login activity                                | target audit shape with identifiers hashed by the approved identity path               |
| Preferences/onboarding                        | target user preference fields; unsupported locale falls back to English                |
| Currencies/user currencies                    | currency master and owned currency selection                                           |
| FX rates                                      | `legacy_imported` quotes with the observed source date                                 |
| Transactions                                  | immutable journal entry plus balanced legs; exact decimal strings                      |
| Categories/rules/income                       | target budgeting models; no equal-allocation rule is invented                          |
| Schedules                                     | recurring rules; no catch-up execution occurs during migration                         |
| Goals/contributions                           | goal buckets and internal transfers; `current_amount` is not authoritative             |
| Emergency reserve                             | reserve bucket and internal transfers; `total` is not authoritative                    |
| Loans/payments                                | loan/liability and posted payment models; history is not synthesized                   |
| Generic investments                           | investment buckets and signed movement direction; `balance` is reconciled, not trusted |
| Securities                                    | instruments, source trades/cash, and watchlist; lots/positions/P&L are rebuilt         |
| Feedback/responses                            | owned feedback records; unresolved authorship is quarantined                           |
| Billing records                               | administrative plans/promotions/subscriptions/invoices/payments only                   |
| Billing/provider settings                     | secrets discarded; operators must re-enter approved configuration                      |
| Email templates/channel                       | email only; provider configuration disabled pending the Step 21 gate                   |
| SMS/push/arbitrary channels                   | quarantined as unsupported                                                             |
| Custom roles/baby steps                       | not active v1 data; fixed roles only                                                   |
| Source migration ledger                       | version/fingerprint evidence only                                                      |

## Audited correction rules

1. Ordinary `income`/`spending` rows remain external journal events.
2. Rows linked through `source='ef'`, `source_ref_id`, or `ef_tx_id` are matched to `emergency_fund_tx` and are not imported twice.
3. Goal contributions and emergency additions/withdrawals become balanced internal transfers. They never affect income or spending totals.
4. `goal_transactions` and `emergency_transactions` migrate only when deterministically matched to their authoritative ledgers. Matched duplicates are skipped; unmatched rows are quarantined.
5. Denormalized goal, reserve, investment, position, lot, realized-P/L, and snapshot totals are not authoritative. Source movements/trades are the rebuild boundary.
6. Orphan-owned and cross-owner rows are quarantined and are never assigned to another user.
7. Invalid dates, currencies, enums, relations, exact decimals, or quantities are quarantined. No repair value is inferred.

## Rehearsal

1. Restore an anonymized, production-shaped legacy snapshot to an isolated PostgreSQL database.
2. Create a dedicated read-only source role and verify it cannot create, alter, update, or delete.
3. Apply all target migrations to an isolated NestJS database.
4. Set the target migration connection variables plus:

   ```text
   LEGACY_MIGRATION_ENABLED=true
   LEGACY_MIGRATION_MODE=rehearsal
   LEGACY_MIGRATION_CUTOVER_APPROVED=false
   LEGACY_DATABASE_URL=postgresql://<read-only-role>@<isolated-host>/<legacy-snapshot>
   ```

5. Run:

   ```sh
   pnpm nx run api:legacy-migration-rehearsal
   ```

6. A blocked batch exits non-zero. Diagnose it only through the restricted control tables:

   - `legacy_migration_batches`
   - `legacy_migration_row_ledger`
   - `legacy_migration_quarantine`
   - `legacy_migration_reconciliation`

7. Require every reconciliation row to be `exact` or to carry an approved explanation code. `blocked` is not cutover-ready.
8. Run the identical snapshot again. The command must return the same batch ID with `reused=true`; row counts must not increase.

## Cutover approval checklist

Cutover is not authorized until all of the following are recorded by the owner/operator:

- accepted source schema fingerprint and transformer version;
- zero blocked reconciliation rows;
- reviewed quarantine counts and disposition for every reason code;
- verified per-user/currency/domain counts and exact decimal totals;
- backup/PITR checkpoint for the target and a tested restore;
- a final legacy delta window and explicit PHP read-only switch;
- no known default administrator credential, token, or provider secret in the target plan;
- approved canary users and reconciliation sign-off;
- rollback owner, decision deadline, and traffic-switch procedure;
- Step 21 operational/security gates relevant to production are complete.

After approval, `LEGACY_MIGRATION_MODE=cutover` may be used to record an immutable preflight against the final read-only snapshot. Applying planned business rows is a separately reviewed operator action; this repository intentionally provides no endpoint or automatic production writer.

## Rollback

Rehearsal rollback requires no business-data action: no target business rows were written. Retain the batch as audit evidence and mark the operational rehearsal abandoned outside the application.

For an approved external cutover:

1. stop new-target writes and switch traffic back before the rollback deadline;
2. restore the pre-cutover target checkpoint or reverse the reviewed import transaction according to the approved operator procedure;
3. leave the legacy PHP database read-only until reconciliation evidence is preserved;
4. record the failed transformer version, source fingerprint, reason codes, and count-only reconciliation;
5. do not delete or repair source records during rollback.

No rollback procedure may rotate credentials, delete source records, or infer financial repairs.
