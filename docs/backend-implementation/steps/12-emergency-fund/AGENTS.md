# Step 12 — Emergency Fund

## Objective

Preserve reserve target, deposit, withdrawal, history, and optional investment linkage without false income/spending.

## Evidence

`src/controllers/emergency.php`, `src/helpers_ef.php`, migrations 014, 015, 021, 026, and findings F-17, F-19, F-20.

## Required behavior

- one user reserve configuration;
- target and current allocation derived/reconciled from transfers;
- deposit/withdraw as internal transfer;
- linked investment behavior without duplicate economic posting;
- target methodology returned as labeled, configurable educational data.

## Corrections

Do not call every scheduled payment a “need.” Do not state that a user is safe or should invest after a fixed milestone. Do not generate income on withdrawal.

## Acceptance

Deposit/withdraw zero cash-flow effect, insufficient source funds if enforced by approved account policy, linked-investment atomicity, deletion/reversal, FX, target inputs, and ownership tests pass.

