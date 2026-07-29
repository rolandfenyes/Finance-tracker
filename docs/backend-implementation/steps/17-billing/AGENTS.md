# Step 17 — Billing and Entitlements

## Objective

Preserve current plan/promotion/subscription/invoice/payment administrative data safely. Implement customer payment flows only if Step 00 approves a provider and paid-launch scope.

## Evidence

Billing portions of `src/controllers/admin.php`, migrations 031 and 034, current pricing/role helpers, and findings C-06, M-02 through M-04.

## Required parity

- billing plans and promotions;
- user subscription, invoice, and payment records;
- administrative plan assignment;
- entitlements derived from the authoritative plan/capability model.

## Conditional provider work

If approved: hosted checkout, customer portal, signed webhook inbox, idempotency, event replay/reconciliation, and secret references. If not approved: provider endpoints remain absent and billing is explicitly administrative/prototype functionality.

## Corrections

No plaintext provider secret storage or full-secret response. No advertised trial/cancellation behavior that is not provider-backed. Price currency/trial values have one source of truth.

## Acceptance

Entitlement transitions, admin authorization, promotion boundaries, webhook signature/idempotency/replay when included, secret masking, and ledger reconciliation tests pass.

