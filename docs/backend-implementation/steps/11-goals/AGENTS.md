# Step 11 — Goals

## Objective

Preserve goal CRUD, contributions, scheduling, completion, archive/unarchive, and category relationships using corrected ledger semantics.

## Evidence

`src/controllers/goals.php`, migrations 012, 013, 025, 036, and findings F-17/F-18.

## Required behavior

- target amount/currency/date/status/category;
- contributions as transfers/allocations linked to posted journal entries;
- derived progress reconciled to contribution/reversal history;
- linked recurring contribution forecast;
- explicit completion and archive state transitions;
- archive without creating income;
- corrections through reversal, not hidden deletion.

## Decision required

Step 00 must define whether completed goals lock automatically or only after archive. Do not infer a new transition.

## Acceptance

Overfunding policy, completed/archive transitions, contribution retry, currency conversion, schedule linkage, reversal/unarchive, ownership, and ledger reconciliation tests pass.

