# Step 22 — Postman/Newman Acceptance and Backend Completion

## Objective

Validate the complete external API as Angular will consume it, then freeze the reviewed v1 contract.

## Deliverables

- version-controlled Postman collection generated/checked against OpenAPI where practical;
- local, CI, staging environment templates with no committed secrets;
- synthetic seed/fixture setup and cleanup;
- Newman CI report;
- reviewed OpenAPI artifact and generated Angular client;
- endpoint coverage matrix mapped to Step 00 parity map;
- backend completion report and documented feature flags/limitations.

## Required scenarios

- registration, verification, login, session rotation/expiry/logout, passkeys, and rate limits;
- every current-user CRUD domain;
- free/premium/admin permission and quota boundaries;
- two-user isolation attacks;
- validation/error shapes;
- money, FX, date, recurrence, reversal, idempotency, pagination, and concurrency-sensitive endpoints;
- job-trigger/status flows;
- export/deletion;
- admin, billing/provider behavior only when included in approved scope.

## Completion gate

Newman passes against production-like PostgreSQL/Redis; OpenAPI has no unexplained diff; generated Angular client builds; all 154 legacy routes are accounted for by replacement/removal/frontend-only/defer status; no Critical/High correction lacks evidence; and the owner explicitly accepts the backend as Angular v1.

