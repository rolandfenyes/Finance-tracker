# Step 14 — Generic Investments

## Objective

Preserve generic savings/investment balance tracking, adjustments, recurrence, and projection without presenting deterministic market returns as facts.

## Evidence

`src/controllers/investments.php`, migrations 023–026, documented untracked `stock_id`/`units` drift, and finding F-21.

## Required behavior

- current supported investment types and currencies;
- deposit/withdraw as transfers linked to journal entries;
- derived/reconciled balance;
- recurring contribution forecast;
- optional nominal compound-interest scenario using the existing formula and explicit assumption metadata;
- no automatic accrual into posted balance unless an actual interest entry is posted.

## Corrections

Label ETF/stock fixed-rate output as a user-defined return scenario, not interest, expected return, or forecast certainty. Resolve untracked columns only through the approved migration map.

## Acceptance

Transfer effects, withdrawal limits, schedule projection, compounding fixtures, fractional periods, zero/negative-rate policy, currency, reversal, and ledger reconciliation pass.

