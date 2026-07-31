# Step 06 — Categories, Basic Income, Budgeting, and Recurrence

## Objective

Implement the complete planning surface for categories, forecast-only basic
income, percentage cash-flow rules, assignments, and supported recurring rules.

## Dependencies

- Steps 00–05 are complete.
- Dashboard/report and generic form/data-view patterns are stable.

## Required evidence

Read budgeting and recurrence controllers, DTOs, generated services, types,
calculators/tests, entitlement limits, supported RRULE subset, forecast
contracts, and related backend decisions.

## Routes and required behavior

- `/app/plan`: planning hub from authoritative rule, income, and recurrence
  reads.
- `/app/plan/budget`: rule-level planned value, assigned spending, signed
  variance, assignments, and aggregate allocation.
- `/app/plan/categories`: category create/update/delete with kind, color,
  protected, quota, and reference states.
- `/app/plan/income`: validity-ranged baseline income definitions.
- `/app/plan/schedules`: recurring rules and optional side-effect-free forecast.
- `/app/plan/schedules/new`: explicit income, expense, or transfer rule.
- `/app/plan/schedules/:id/edit`: update supported RRULE behavior.

Settings aliases for categories and income must route to or reuse the same
feature components; do not create duplicate business implementations.

## Business constraints

- Free users can read rule outcomes but cannot edit cash-flow rules.
- Category and active-schedule quotas come from entitlements.
- Protected or referenced categories cannot be presented as freely deletable.
- Basic income is planning input, never a posted transaction.
- Allocation above 100% remains `over_allocated`; do not normalize or block it.
- Negative variance remains visible.
- Forecast occurrences remain distinct from posted journal entries.
- Support only daily, weekly, monthly, yearly, interval, `BYDAY`,
  `BYMONTHDAY`, `BYMONTH`, `COUNT`, and `UNTIL`.
- Reads never trigger catch-up or posting.

## Reusable UI

- category chip/color selector with accessible non-color label;
- entitlement/limit banner;
- budget allocation and signed-variance components;
- validity-range form;
- RRULE structured form and human-readable presenter;
- forecast occurrence list with explicit status;
- responsive planning data view.

## Tests

- free/premium/admin capability presentation;
- category quota/protection/reference errors;
- exact percentage strings, over-allocation, negative variance, and empty
  assignments;
- basic income is forecast only;
- RRULE valid subset and explicit unsupported-rule errors;
- month-end, leap date, count/until, and timezone display fixtures from backend
  contract evidence;
- no read mutation or client-side forecast posting;
- mobile/desktop forms, keyboard, long translated labels, and non-color status;
- Playwright planning setup, quota denial, over-allocation, and schedule
  forecast journeys.

## Acceptance criteria

- Every budgeting/category/income/recurrence operation has UI coverage.
- Entitlements and forecast semantics are accurate.
- No local schedule materialization or financial aggregation exists.
- Step 07 was not started.
