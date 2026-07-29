# Step 20 — Legacy Data Migration and Reconciliation

## Objective

Transform supported PHP/PostgreSQL data into the corrected schema repeatably without destructive production operations.

## Evidence

All legacy migrations, configured-schema audit, duplicate tables, untracked investment columns, and every domain mapping from Step 00.

## Deliverables

- read-only extractor and versioned transformer;
- source-schema version detection;
- explicit mapping for users, security credentials where safely migratable, settings, currencies, ledger data, categories/rules, schedules, goals, emergency, loans, investments, stocks, feedback, billing, and notifications;
- correction policy for false income/spending transfers;
- quarantine report for ambiguous/invalid rows;
- per-user/currency/domain counts and amount reconciliation;
- resumable idempotent rehearsal;
- rollback/cutover plan.

## Prohibited

No production writes, credential rotation, record deletion, or inferred repair without an approved runbook. Never migrate the default administrator credential or hard-coded secrets.

## Acceptance

An anonymized production-shaped rehearsal is repeatable, differences are explained, totals reconcile within explicit decimal rules, ambiguous rows are quarantined, and rerun produces no duplicates.

