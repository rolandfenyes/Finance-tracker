# Step 08 — Loans and Generic Investments

## Objective

Implement loans and generic investments with posted history separated from
projections and user-authored scenarios.

## Dependencies

- Steps 00–07 are complete.
- Exact values, currency provenance, journal command patterns, recurrence,
  charts, and domain detail layouts are stable.

## Required evidence

Read loan/investment controllers, DTOs, generated services, types, calculators
and golden fixtures, recurrence linkage, FX behavior, notification
calculation-source tests, and approved loan/investment decisions.

## Loan routes and behavior

- `/app/loans`: active/completed/archived overview and quota.
- `/app/loans/new`: principal, currency, nominal rate, term, dates, payment day,
  extra-payment scenario, insurance/fees only as declared.
- `/app/loans/:id`: outstanding principal, versioned estimate, projected
  schedule, posted payments, recurrence, completion/archive state.
- `/app/loans/:id/edit`: update configuration without rewriting history.
- dialogs/panels for payment, correction, reversal, archive, and recurring-rule
  create/replace/delete.

Label the calculation “Standard fixed nominal-rate monthly annuity
illustration.” Never label it APR, a lender quote, or a confirmed payment.

## Investment routes and behavior

- `/app/investments`: savings/ETF/stock generic records.
- `/app/investments/new`;
- `/app/investments/:id`: ledger-derived balance, movements, recurrence, and
  user-authored scenario;
- `/app/investments/:id/edit`;
- dialogs/panels for deposit, withdrawal, reversal, and recurring contribution.

Keep generic investments distinct from the securities portfolio. A missing
rate disables the scenario, zero is allowed, and negative is rejected. Scenario
gain is not expected, guaranteed, or posted.

## Calculation boundary

Do not calculate outstanding principal, annuity payments, allocation,
compounding milestones, contribution totals, FX, or balance locally. Render the
approved read models and their assumption metadata.

## Tests

- loan values and schedule come from loan service fixtures;
- investment balance/scenario come from investment service fixtures;
- no local amortization or compounding implementation;
- nominal-vs-APR and posted-vs-projected labels;
- zero-rate and missing/zero/negative scenario states;
- payment/movement idempotency, correction, reversal, and uncertain result;
- cross-currency provenance/unavailable state;
- history-aware delete/archive and withdrawal-limit errors;
- mobile detail/dialog layouts, charts with table alternatives, keyboard,
  EN/ES/HU;
- Playwright loan payment/reversal and investment movement/scenario journeys.

## Acceptance criteria

- Every loan and generic-investment operation has UI coverage.
- Calculation-source boundaries are proven.
- Projection/scenario language cannot be mistaken for posted fact.
- Securities work was not started.
- Step 09 was not started.
