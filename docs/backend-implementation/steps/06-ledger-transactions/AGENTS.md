# Step 06 — Accounts, Journal, Transfers, and Transactions

## Objective

Create the corrected financial source of truth and replace the prototype's income/spending-only ledger.

## Evidence

`src/controllers/transactions.php`, `month.php`, helpers that sign/aggregate transactions, migrations 001 and 004, and findings F-04 through F-08 and F-17.

## Required model

- user-owned financial accounts/buckets sufficient to represent current cash, goal, emergency, loan, and investment movements;
- immutable posted journal entries and balanced legs;
- external income, external expense, internal transfer, adjustment, fee, interest, dividend, and trade-linked semantics only where current modules require them;
- reversals/replacements instead of destructive history edits;
- optional category on appropriate external entries;
- source/module linkage and idempotency key;
- posted date, effective timestamp, created timestamp, and audit actor.

## Corrections

Reject zero/negative values where direction is represented by type; enforce ownership in DB/application policies; do not add bank reconciliation/open-banking fields beyond the approved migration/MVP scope.

## Acceptance

Balance, reversal, transfer-zero-effect, concurrency, duplicate-idempotency, currency, ownership, pagination, and aggregate reconciliation tests pass.

