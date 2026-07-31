# Step 22 — Backend Completion and Angular v1 Handoff

## Frozen contract

- OpenAPI: `apps/api/openapi/openapi.json`
- Generated Angular client: `libs/generated/api-client/src`
- Postman collection: `postman/MyMoneyMap-backend-v1.postman_collection.json`
- Legacy accounting: `ENDPOINT-COVERAGE-MATRIX.md`

The OpenAPI contract contains 148 operations across 113 paths. The Postman
Contract catalogue contains every operation. The Angular client is generated
from the same document and must pass its build and drift checks.

## Acceptance evidence

| Boundary                        | Newman acceptance                                                                                                                        | Required deeper evidence                                                                                  |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Identity/session                | registration, verification-request enumeration safety, password login/cookie, passkey options, logout, unverified denial, login throttle | `identity-http.integration-spec.ts`, `identity-access.integration-spec.ts`, passkey unit tests            |
| Users/settings                  | current user, theme, currencies, notification preferences                                                                                | `users-settings-http.integration-spec.ts`, `currency-http.integration-spec.ts`                            |
| Ledger                          | exact-decimal post, stable idempotency replay, reversal, pagination                                                                      | ledger HTTP/PostgreSQL suites for atomicity, rollback, concurrent replay, FX snapshots                    |
| Budgeting                       | owned category creation and two-user isolation                                                                                           | budgeting HTTP/PostgreSQL suites for rules, basic income, quotas, related-ID isolation                    |
| Recurrence                      | complete contract catalogue                                                                                                              | recurrence HTTP/PostgreSQL suites for date/DST/RRULE, worker retry, overlap, no-write reads               |
| Reporting                       | current-month read-model request                                                                                                         | reporting HTTP/PostgreSQL suites for reconciliation, FX provenance, pagination-stable totals              |
| Goals/reserve/loans/investments | representative create/target operations                                                                                                  | domain HTTP/PostgreSQL suites for corrections, reversals, ownership, calculations and read-only reads     |
| Securities                      | portfolio read and disabled-provider refresh gate                                                                                        | securities suites for trades/imports, exact quantities, oversell concurrency, atomic reversal, jobs       |
| Feedback/admin/billing          | user list, admin dashboard/queues, billing summary                                                                                       | admin/feedback and billing suites for RBAC, related-ID isolation, quotas and fixed roles                  |
| Notifications                   | preferences, email-channel status, disabled-provider test job                                                                            | notification unit/PostgreSQL suites for suppression, locale, retry/dead-letter, idempotency, safe logs    |
| Privacy                         | disabled export production gate                                                                                                          | privacy HTTP/PostgreSQL suites for manifest, export/deletion state machines, reauthentication and erasure |
| Operations                      | liveness, readiness, queue observability                                                                                                 | migration, drift, restore, load, security, and dependency-outage CI gates                                 |

This split is intentional: Newman proves a deploy-shaped synthetic journey while
the integration suite retains deterministic control of concurrency, retries,
transactions, clocks, provider fakes, and failure injection.

## Verification record

The final local verification used the repository's Docker PostgreSQL 17 and
Redis 7 services.

| Command/gate                                          | Result                                                                            |
| ----------------------------------------------------- | --------------------------------------------------------------------------------- |
| `pnpm format:check`                                   | pass                                                                              |
| `pnpm lint`                                           | pass                                                                              |
| `pnpm typecheck`                                      | pass, API plus generated Angular client                                           |
| `pnpm test`                                           | pass, 40 suites / 147 tests                                                       |
| `pnpm test:integration`                               | pass, 39 suites / 151 tests                                                       |
| `pnpm build`                                          | pass, API plus generated Angular client                                           |
| `pnpm db:migrations:check`                            | pass                                                                              |
| isolated migration apply / rollback / reapply / drift | pass, 23 applied, latest rolled back and reapplied, committed fingerprint matched |
| `pnpm contracts:check`                                | pass, OpenAPI / Angular client / Postman / route matrix                           |
| `pnpm postman:acceptance`                             | pass, 66 requests / 140 assertions, no failures                                   |
| `pnpm security:audit`                                 | pass, no known high/critical production dependency vulnerability                  |

The isolated runner always uses a validated random database name, disables live
providers, writes credentials only to a mode-0600 temporary environment, and
drops the database plus temporary directory in `finally`.

Graphify refresh was attempted with `graphify update .` and failed with
`[graphify watch] Rebuild failed: [Errno 1] Operation not permitted`. The
repository-owned generators and checks all passed; this is the same
filesystem-watcher sandbox restriction, not a product-code failure. An
outside-sandbox retry was not permitted, and no permissions or unrelated
tooling were weakened.

## Critical/high audit closure

The authoritative finding-to-test register is
`../00-execution-contract/AUDIT-TRACEABILITY.md`. Every Critical/High finding is
owned by a completed backend step or an explicit frontend/legal gate. Step 22
adds these freeze assertions:

- no known administrator or provider credential appears in fixtures;
- compiled-metadata OpenAPI generation exposes complete request/query schemas;
- exact financial values remain decimal strings in OpenAPI, generated client,
  Postman requests, and runtime assertions;
- internal movements remain transfers and immutable corrections/reversals;
- unavailable/stale FX and market data are explicit rather than fabricated;
- no checkout, webhook, customer cancellation, arbitrary role/channel,
  month-close, general CSV-import, or financial-advice endpoint was invented;
- all 159 executable legacy route patterns are accounted for;
- disabled production gates prevent Postmark, market-data, privacy-object-store,
  and legacy-cutover calls during acceptance.

## Feature flags and production gates

| Capability             | Backend v1 state                                                                   |
| ---------------------- | ---------------------------------------------------------------------------------- |
| Recurrence worker      | implemented; production deployment must explicitly enable                          |
| FX refresh             | implemented behind explicit enablement/provider configuration                      |
| Securities market data | disabled until owner approval and provider terms/configuration                     |
| Email delivery         | disabled unless delivery, Postmark, sender, and production approval gates all pass |
| Privacy exports        | disabled until private S3 storage and owner-approved TTLs are configured           |
| Legacy migration       | rehearsal-only by default; cutover requires separate explicit approval             |
| Sentry/metrics         | production requires explicit PII-safe configuration and approval                   |
| Billing                | administrative records only; no payment-provider checkout/webhooks/self-service    |

## Known handoff limitations

- The generated client is a transport contract, not Angular UI or state
  management.
- Staging environment values and all credentials are intentionally blank.
- Legal copy, DPA/subprocessor approval, retention decisions, production
  credentials, provider commercial terms, and deployment approvals remain owner
  or legal/operations responsibilities.
- The final freeze is not owner-accepted merely because automated checks pass.
  The owner must explicitly accept this report and contract as the Angular v1
  backend baseline.

## Owner acceptance

Status: **awaiting explicit owner acceptance**.

Record acceptance only after the complete repository verification and Newman
report pass. An implementation agent must not infer acceptance from running this
step or silently edit this status.
