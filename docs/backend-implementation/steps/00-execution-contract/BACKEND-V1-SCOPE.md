# Step 00 — Backend v1 Scope and Feature Gates

**Scope decision:** full corrected parity

**API namespace:** `/api/v1`

**Frontend:** excluded until backend completion

**Landing site:** excluded

## Included domains

| Domain | Included behavior | Corrected behavior | Step |
|---|---|---|---:|
| Platform | configuration, health/readiness, errors, logs, OpenAPI | fail-closed secrets; production operations | 01, 21 |
| PostgreSQL | migrations, constraints, transactions | no default admin; drift-free baseline | 02 |
| Shared kernel | identifiers, clock, decimal types, idempotency | no JS floating point for exact values | 03 |
| Identity | registration, verification, login/logout, persistent sessions, passkeys | verification gate, rotation, expiry, throttling, recovery | 04 |
| Users | profile, language, onboarding/tutorial, theme ID, status | fixed roles/capabilities | 05 |
| Ledger | income, expense, transfers, corrections | immutable journal and balanced internal movements | 06 |
| Currency/FX | catalogue, user currencies, main currency, conversion | provenance, snapshots, unavailable/stale states | 07 |
| Planning | categories, basic income, percentage rules | over-allocation and signed variance; no equal category cap | 08 |
| Recurrence | existing RRULE subset and linked schedules | queue workers, explicit economic type, idempotency | 09 |
| Reporting | current month, year/month, filters and aggregates | posted/forecast separation and explainable totals | 10 |
| Goals | CRUD, contributions, schedules, complete/archive | target lock and transfer semantics | 11 |
| Emergency | target, allocation, history, investment link | transfers and neutral/raw guidance data | 12 |
| Loans | CRUD, schedules, payments, estimates, archive | consistent FX, no read writes, estimate labels | 13 |
| Generic investments | CRUD, adjustments, schedules, projection | transfer funding and scenario wording | 14 |
| Securities | portfolio, trades, cash, import, quotes, charts, watchlist, signals | oversell rejection, cash reversal, market identity, freshness | 15 |
| Feedback/admin | feedback lifecycle, current admin user/system operations | secure recovery, audit, masked secrets | 16 |
| Billing records | plans, promotions, subscriptions, invoices, payments | administrative only; no plaintext secrets | 17 |
| Email | current evidenced email categories and preferences | queued delivery; factual template data | 18 |
| Privacy | export, deletion and audit | complete manifest, no credential internals | 19 |
| Legacy migration | supported current schema | quarantine and reconciliation | 20 |
| QA/operations | security, performance, monitoring, backups | tested restore/rollback/failure handling | 21 |
| Acceptance | Postman/Newman and OpenAPI freeze | all legacy routes accounted for | 22 |

## Configuration gates

| Gate | Default outside tests | Enable only when |
|---|---|---|
| `email_delivery` | disabled/log fake | Postmark domain, secret, webhook, suppression, privacy and retry checks pass |
| `fx_refresh` | enabled with deterministic fake in test; Frankfurter optional in development | outbound access, supported-currency coverage, timeout/retry and provenance configured |
| `market_data` | disabled/fake | Finnhub credential, quota, delay, market coverage and licensing verified |
| `administrative_billing` | enabled for authorized admins | schema, audit, entitlements and secret-free configuration pass |
| `customer_checkout` | absent | not part of backend v1 |
| `sms` | absent | excluded |
| `push` | absent | excluded |
| `legacy_migration` | disabled | explicit read-only source and approved rehearsal/cutover runbook |
| `maintenance_mode` | supported as platform configuration | admin authorization and operational bypass documented |

## Explicitly removed legacy behavior

- fixed/default administrator creation or reset;
- hard-coded DB/provider secret fallbacks;
- GET logout;
- GET mutation such as loan payment backfill;
- request-bound scheduled processing;
- arbitrary custom-role CRUD/assignment;
- public application migration runner;
- full secret retrieval/display;
- generic CSV-export claim without implementation;
- false income/expense records for goals, emergency fund, and investments;
- unchanged-number FX fallback;
- future rates stored as historical observations;
- stock overselling and orphaned cash after trade deletion;
- cost substituted for unavailable market price;
- prescriptive buy/trim and emergency-adequacy language;
- enabled SMS/push configuration without implementations.

## Explicit exclusions

Open banking, bank credentials, household workspaces, adviser access, native apps, offline sync, AI, tax filing, receipt OCR, new tiers, support/operator role, automatic transaction categorization, subscription detection, dividend calendar, corporate actions, TWR/MWR, payment checkout, and provider webhooks for payment are not part of v1.

## Completion policy

All included domains must be implemented and accepted. Provider-dependent operations may remain disabled by configuration, but their ports, deterministic fakes, failure contracts, and tests must exist. A disabled provider is not permission to fabricate data.
