# Step 00 — Execution Contract and Decision Record

## Objective

Create the authoritative backend scope, decisions, parity map, and correction map before generating NestJS code.

## Required evidence

- All five audit documents.
- `index.php` route inventory.
- `src/controllers/`, `src/helpers.php`, `src/auth.php`, `src/fx.php`, `src/recurrence.php`, and `src/stocks/`.
- All `migrations/*.sql` and the documented configured-schema drift.

## Deliverables

- Architecture decision records for every open choice listed in the master plan.
- Route-to-v1-endpoint parity matrix covering all 154 PHP routes, with `preserve`, `replace`, `remove`, `frontend-only`, or `defer` status.
- Table/column-to-target-model map.
- Audited-finding-to-step/test traceability matrix.
- Explicit first backend completion scope and feature flags.
- Golden-fixture inventory using synthetic/anonymized values.

## Prohibited

Do not scaffold packages, choose vendors, rename product concepts, or settle ambiguous financial behavior without owner approval.

## Acceptance

Every current backend behavior is owned by a later step or deliberately excluded, every Critical/High audit correction has a target test, and every open technology choice has an approved answer.

