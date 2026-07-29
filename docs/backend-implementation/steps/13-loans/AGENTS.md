# Step 13 — Loans

## Objective

Preserve loan CRUD, scheduled linkage, payment history, archive/completion, and illustrative amortization while correcting inconsistent and misleading behavior.

## Evidence

`src/controllers/loans.php`, `src/services/loan_completion.php`, migrations 009–011, 023–025, and findings F-12 through F-16.

## Required behavior

- principal, currency, outstanding balance, nominal annual rate, term, payment assumptions, insurance/fee fields only where current schema supports them;
- versioned standard annuity estimate with zero-rate handling;
- posted repayments linked to journal entries;
- principal/interest/fee components;
- same currency policy or dated conversion applied consistently to manual and scheduled payments;
- projected schedule separate from confirmed payments;
- explicit completion/archive transition.

## Corrections

No GET/read backfill. No synthesized payment described as confirmed. No APR label unless contract inputs support APR. Extra payment remains a scenario until posted.

## Acceptance

Golden annuity fixtures, zero rate, rounding, irregular/manual dates, cross-currency consistency, overpayment, reversal, schedule retry, completion, and reconciliation tests pass.

