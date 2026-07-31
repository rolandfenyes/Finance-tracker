# Step 05 — Journal, Corrections, Reversals, and Reports

## Objective

Implement immutable journal workflows and historic month/year reports while
preserving exact values, cursor stability, idempotency, and reporting
calculation boundaries.

## Dependencies

- Steps 00–04 are complete.
- Product shell, exact-value adapters, command lifecycle, chart adapter, and
  dashboard reporting components are operational.

## Required evidence

Read ledger/reporting controllers, DTOs, generated services, journal and report
types, source-module semantics, idempotency declarations, conversion metadata,
report calculator tests, and route coverage for transactions/months/years.

## Routes and required behavior

- `/app/activity`: cursor/date-filtered immutable journal feed.
- `/app/activity/new`: post one supported manual economic type.
- `/app/activity/:id`: entry, legs, source, dates, FX provenance, and reversal
  relationships from returned list/read data.
- `/app/activity/:id/correct`: explicit reverse-and-replace command.
- `/app/activity/:id/reverse`: explicit reversal, never destructive delete.
- `/app/reports`: years with actual report sources.
- `/app/reports/:year`: annual and month-by-month cash-flow aggregates.
- `/app/reports/:year/:month`: URL-backed filters and cursor activity.

Manual form behavior must follow the generated DTO for external income,
external expense, internal transfer, adjustment, fee, interest, and dividend.
Do not expose module-owned loan repayment or trade cash as manual types.

## Command rules

- Generate and retain an idempotency key for create, correction, and reversal.
- On uncertain result, refresh the journal/report before offering a new intent.
- Preserve positive exact amount and use economic type/direction fields.
- A correction compares original and replacement; it does not mutate original.
- A reversal explains that history remains visible.
- Refresh journal, relevant report, and dashboard only after authoritative
  success.

## Reusable UI

- financial command dialog/form;
- journal entry card and accounting-leg disclosure;
- correction comparison;
- reversal confirmation;
- cursor pager/load-more;
- report filter sheet/desktop panel;
- period navigator;
- report chart/table compositions.

## Tests

- generated DTO serialization and required idempotency headers;
- same intent retry does not get a new key;
- correction/reversal language and state;
- exact amount/currency/date handling;
- transfers have zero income/expense presentation;
- filters are represented in URL query state and cursors remain opaque;
- complete filtered totals do not change between activity pages;
- unavailable/stale FX remains explicit;
- no report or journal total is locally aggregated;
- keyboard, dialog focus, mobile filter sheet, desktop table, and EN/ES/HU;
- Playwright create -> report -> correct -> reverse and historic report journeys.

## Acceptance criteria

- All journal and reporting operations have intentional UI coverage.
- History is never visually or semantically deleted.
- Reporting remains authoritative for totals.
- Idempotent uncertain-result recovery is tested.
- Planning/domain-specific movement work was not started.
- Step 06 was not started.
