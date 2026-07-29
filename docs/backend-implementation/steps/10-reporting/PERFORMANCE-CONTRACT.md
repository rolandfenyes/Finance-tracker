# Step 10 Reporting Performance Contract

Approved on 2026-07-29 under Option A.

This contract makes the Step 10 query-plan acceptance criterion deterministic without claiming
production latency from a development or CI machine.

## Synthetic volume

The required PostgreSQL plan test uses synthetic data only and measures a user-scoped reporting
query against at least:

- 50,000 posted journal entries distributed across at least 50 users;
- immutable conversion snapshots for every posted entry;
- a target user with at least 1,000 entries in the measured year.

The functional integration suite separately covers budget rules, basic incomes, recurring rules,
cross-user isolation, and forecast expansion.

## Measured query

The test runs the production-shaped posted calendar-month aggregate for the target user with
`EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` after one unmeasured warm-up execution.

The measured plan must:

- complete within 750 milliseconds;
- use an index whose leading columns are `user_id, posted_on` for the target journal scan;
- avoid a sequential scan of `journal_entries`;
- return one aggregate row;
- examine no more than 5,000 shared hit/read blocks.

The elapsed-time ceiling is deliberately conservative for CI variance. The index and buffer
criteria are the primary regression guards. Production SLOs and load/concurrency budgets remain
Step 21 concerns.

The Step 10 index budget is one new reporting-specific journal index. Existing FX snapshot,
basic-income, recurring-rule, category, and budget-rule indexes must be reused rather than
duplicated.
