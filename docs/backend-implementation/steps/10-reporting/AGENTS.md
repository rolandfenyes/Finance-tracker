# Step 10 — Month, Year, Dashboard, and Reporting Read Models

## Objective

Rebuild current month/year/dashboard outputs from corrected posted and forecast sources with explainable definitions.

## Evidence

`src/controllers/month.php`, `years.php`, dashboard logic in `index.php`/helpers, email report collectors, and the formula register in the main audit.

## Required behavior

- separate posted, pending/forecast, and combined projections;
- month activity pagination/filtering;
- income, expense, transfer, net cash flow, budget variance, and currency-conversion status;
- year/month aggregates;
- source-entry drill-down identifiers;
- freshness/rate metadata;
- query-shaped indexes and measured plans.

## Prohibited

Do not call net cash flow an account balance or net worth. Do not substitute missing FX/market values. Do not clamp negative budget variance.

## Acceptance

Every aggregate reconciles to source entries, transfers have zero income/expense effect, pagination does not change totals, unavailable FX is explicit, and realistic-volume query plans meet the approved budget.

