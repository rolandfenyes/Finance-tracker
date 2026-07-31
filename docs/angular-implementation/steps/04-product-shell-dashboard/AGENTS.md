# Step 04 — Product Shell and Current-Month Dashboard

## Objective

Build the responsive personal-finance shell and render the authoritative
current-month reporting read model as the product command center.

## Dependencies

- Steps 00–03 are complete.
- A verified personal-finance user can finish onboarding.
- Shared session, design-system, localization, and exact-value boundaries pass.

## Required evidence

Read the current-month reporting controller, DTOs, types, generated reporting
service, report calculator tests, budget read model, conversion statuses,
entitlements, and the handoff page/layout/chart requirements.

## Required deliverables

- Product shell under `/app` guarded by authenticated, verified, and
  personal-finance policies.
- Mobile bottom navigation: Home, Activity, Plan, Goals, More.
- Tablet navigation rail and desktop collapsible sidebar.
- Typed navigation model filtered by entitlements without template-level role
  duplication.
- `/app/home` using `GET /reports/months/current`.
- `/app/more` navigation hub for reserve, loans, investments, securities,
  reports, feedback, and settings.
- Current period heading and reporting currency.
- Posted cash-flow summary: income, expense, transfer, adjustment net, trade
  cash net, and net cash flow.
- Forecast summary and source presentation.
- Combined projection kept visually and semantically separate.
- Budget rule plan cards with signed variance and over-allocation.
- Activity preview with cursor-aware route into the later activity page.
- Conversion completeness, provider provenance, and stale/unavailable states.
- Chart adapter and accessible table alternative using the Step 00 renderer.
- Loading, empty, partial-data, error, gated, and retry states.

Do not label net cash flow as balance or net worth. Do not calculate totals from
activity items. Do not post or mutate journal data in this step.

## Reusable UI

- `CashFlowSummaryCard`;
- `MetricCard`;
- posted/forecast/projection selector;
- `ConversionStatus` and `PartialDataBanner`;
- `SignedVariance`;
- responsive `FinanceChart`;
- `DataView` composition and mobile activity row;
- shell navigation primitives.

## Tests

- dashboard values come directly from the reporting service fixture;
- totals remain unchanged when the activity cursor changes;
- transfer does not render as income/expense;
- forecast and posted sources cannot be confused;
- stale/unavailable conversion is not zero or source amount;
- navigation matrix for free/premium/admin;
- mobile bottom-nav, tablet rail, desktop sidebar, safe areas, and 320 px reflow;
- chart/table parity, keyboard navigation, reduced motion, and screen-reader
  summary;
- Playwright login-to-dashboard journey with complete and partial data.

## Acceptance criteria

- The personal shell is complete and responsive.
- The dashboard faithfully renders the backend cash-flow read model.
- No local financial aggregation or hidden provider fallback exists.
- Activity/report mutation pages remain outside this step.
- Step 05 was not started.
