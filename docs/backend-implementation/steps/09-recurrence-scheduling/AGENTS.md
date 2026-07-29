# Step 09 — Recurrence and Scheduled Jobs

## Objective

Reimplement the existing RRULE subset and remove all request-bound background processing.

## Evidence

`src/recurrence.php`, `src/scheduled_runner.php`, `src/controllers/scheduled.php`, migrations 008–012, and findings O-01/O-02 and F-08.

## Required behavior

- preserve only the observed subset: daily/weekly/monthly/yearly, interval, `BYDAY`, `BYMONTHDAY`, `BYMONTH`, `COUNT`, `UNTIL`;
- validate unsupported rules explicitly;
- generate forecast occurrences without posting;
- materialize only approved linked behaviors through BullMQ workers;
- transactional job idempotency, locking, retry, dead-letter, status, and audit;
- fixed clock/timezone behavior and bounded catch-up.

## Corrections

Scheduled items must carry an explicit economic type; they are not all spending. API reads never execute catch-up.

## Acceptance

Month-end clamping, leap dates, BYDAY, COUNT/UNTIL, duplicate retry, concurrent worker, partial failure/rollback, timezone, and 2,000-iteration boundary-equivalent tests pass.

