# Step 08 — Categories, Basic Income, and Budgeting

## Objective

Implement current categories, baseline income, and percentage cash-flow rules with corrected validation and reporting semantics.

## Evidence

`src/controllers/categories.php`, `cashflow.php`, `settings_incomes.php`, onboarding category/income flows, migrations 005–008, and findings F-09 through F-11.

## Required behavior

- user-owned income/spending categories with validated color metadata;
- recurring/basic income definitions as planning inputs, not posted income;
- cash-flow rules and category assignment;
- plan capability/limit enforcement;
- visible negative variance;
- explicit over-allocation result when aggregate percentages exceed 100%.

## Decision required

Do not preserve the arbitrary equal per-category split as a true user budget without Step 00 approval. If retained for parity, label it an inferred display allocation and test it separately.

## Acceptance

Ownership, quota, aggregate percentage, category deletion/reference, date/currency, negative variance, and empty-assignment cases pass.

