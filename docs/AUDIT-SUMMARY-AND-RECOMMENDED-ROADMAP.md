# MyMoneyMap — Audit Summary and Recommended Roadmap

**Audit date:** 2026-07-29  
**Current classification:** **Prototype / internal alpha**  
**Recommended target:** Laravel + Inertia/Vue modular monolith, PostgreSQL, Redis-backed jobs/cache, compiled assets, managed providers

## 1. Bottom line

MyMoneyMap has meaningful product breadth, a recognizable visual identity, multilingual content, and enough implemented workflow to support product discovery. It is not ready to hold real customer financial data or accept payment.

The launch decision should be based on trust gates, not feature completion. The current system can produce materially wrong cross-currency totals, misclassify savings transfers, accept impossible stock sales, and expose deployments to predictable privileged access. Privacy and marketing pages also overstate encryption, export completeness, billing, and financial accuracy.

Recommended strategy:

1. immediately neutralize credential/admin hazards in every environment;
2. freeze public launch and real-data use;
3. define correct ledger, FX, loan, and securities semantics;
4. build a production foundation and migrate incrementally;
5. launch a smaller, correct mobile-first MVP before rebuilding every prototype feature.

## 2. Top five critical findings

1. **Predictable default administrator:** migration `028_default_admin.sql` creates/resets a fixed privileged identity and password hash.
2. **Unsafe configuration fallbacks:** database/provider secrets can fall back to hard-coded values.
3. **False financial categorization:** emergency saving, goal payouts and related internal movements become income/spending rather than transfers.
4. **Impossible stock sale accounting:** overselling is accepted and full proceeds are recorded even when lots cover only the available holding.
5. **Silent FX corruption:** a missing rate can preserve the numeric amount while relabeling it in the target currency.

Plaintext/full-display billing secrets are an additional launch blocker of equal urgency.

## 3. Risk dashboard

| Area | State | Reason |
|---|---|---|
| Account security | Red | predictable admin seed, fixation/rate-limit/verification gaps |
| Privacy/compliance | Red | inaccurate claims, incomplete export, unproven deletion/retention |
| Finance correctness | Red | transfer, FX, loan and stock defects |
| Data integrity | Red | schema drift, missing constraints, float arithmetic |
| Billing | Red | administrative records only; plaintext secrets; no real checkout/webhooks |
| Mobile/accessibility | Amber/Red | usable responsive shell, but zoom/orientation blocking and dense tables |
| Testing | Red | one narrow optional-DB script; no CI or clean migration test |
| Operations | Red | no reproducible deployment, queue, monitoring, backup/restore or rollback |
| Product UX/brand | Amber/Green | coherent theme and broad workflows, but claims and IA need correction |

## 4. Release gates

### Gate A — Safe internal prototype

- default admin migration removed and all credentials rotated;
- hard-coded secret fallbacks eliminated;
- access limited to approved testers and synthetic data;
- billing secrets removed/rotated;
- security headers and generic error handling;
- database backup and restore tested;
- known misleading public claims removed.

### Gate B — Private beta with real data

- verified-email gate, session rotation, rate limiting, recovery, admin audit;
- account/transfer-safe ledger and explicit posted/forecast states;
- decimal-safe calculations and fail-closed FX;
- complete export/deletion tests and policy versioning;
- reproducible schema and resolved drift;
- queue-based schedules/email/providers;
- CI with unit, integration, browser, security and accessibility gates;
- managed hosting, TLS, secrets, logs, monitoring, PITR and runbooks;
- independent security review.

### Gate C — Paid public launch

- hosted checkout/customer portal and signed idempotent webhooks;
- entitlement reconciliation and invoice/tax decisions;
- support, incident response, status communication and SLA decisions;
- privacy/terms/cookie/subprocessor documents reviewed for target markets;
- load and resilience tests;
- market-data licensing and delay disclosures;
- regulated-advice boundary reviewed;
- rollback/canary plan and production restore drill.

## 5. Recommended roadmap

### Phase 0 — Contain and decide (1–2 weeks)

**Outcome:** the prototype is safe enough for controlled internal use.

- remove/reset default admin bootstrap and rotate credentials;
- remove hard-coded fallbacks and rotate provider/DB values;
- restrict access and prohibit production financial data;
- remove billing secrets from UI/DB or replace with managed references;
- correct the most misleading landing/privacy/email copy;
- snapshot and reconcile current schema/migrations;
- decide target jurisdictions, data residency, market-data scope, payment model, and advice boundary.

### Phase 1 — Specification and golden tests (3–5 weeks)

**Outcome:** disputed finance behavior becomes an explicit contract.

- define accounts, buckets, balanced transfers, posted/forecast/reconciled states;
- define money precision and rounding per currency/security;
- define FX provenance and unavailable/stale behavior;
- define loan estimate versus contractual schedule;
- define stock lot, cash, corporate-action and performance semantics;
- create anonymized golden fixtures from representative current data;
- design complete export/deletion manifests;
- mobile IA and accessibility prototype.

### Phase 2 — Production foundation (4–7 weeks)

**Outcome:** a deployable secure shell exists.

- Laravel/Inertia/Vue foundation and module boundaries;
- Composer/npm lockfiles and compiled assets;
- dev/staging/prod environments and infrastructure definition;
- CI/CD, migrations from empty DB, static analysis and secret scanning;
- identity, verified email, passkeys, session rotation/revocation, rate limits;
- observability, health checks, secret manager, backup/PITR and restore runbook;
- queue/scheduler and provider clients.

### Phase 3 — MVP ledger and planning (8–13 weeks)

**Outcome:** a small set of daily workflows is correct and mobile-ready.

- accounts, journal, transfers and immutable reversal;
- manual transactions and CSV import/idempotency;
- categories, rules/budgets and recurring forecast;
- month/activity/reporting with explainable totals;
- multi-currency FX snapshots;
- mobile Home/Activity/Plan/More and fast add;
- complete JSON/CSV export and tested deletion.

### Phase 4 — Goals, reserves and loans (6–10 weeks)

**Outcome:** planning modules use the same correct ledger.

- migrate goals and emergency fund as allocations/transfers;
- configurable guidance without prescriptive claims;
- loan schedule versioning, posted payment reconciliation and scenarios;
- job-based due processing;
- parity/reconciliation reports against old data.

### Phase 5 — Grow and monetize (8–14 weeks)

**Outcome:** optional investment and paid features are safe to expose.

- decide generic-investment versus securities product boundary;
- fix oversell, trade-cash linkage, exchange identifiers, FX attribution and quote freshness;
- implement hosted billing and webhook inbox;
- entitlements replace string-role logic;
- queued multilingual communications and delivery events;
- admin audit, job/provider health, support operations.

### Phase 6 — Launch hardening (4–7 weeks)

**Outcome:** release gates are evidenced, not assumed.

- full migration rehearsal and per-user/currency/domain reconciliation;
- browser/device/accessibility matrix;
- load, concurrency, chaos/provider-failure tests;
- independent penetration test and legal/privacy review;
- incident simulation, canary, rollback and restore drill;
- documentation and support training.

## 6. Delivery range

| Milestone | Engineer-days | Small 3-person product team |
|---|---:|---:|
| Safe internal prototype | 10–20 | 1–2 weeks |
| Correct production-foundation MVP | 140–220 | 3–5 months |
| Current feature parity, corrected | 375–610 | 7–12 months |
| Extended SaaS (households/open banking/deep investing/AI) | 550–900+ | 12–20+ months |

The range assumes experienced full-stack, product/design, and QA/security contribution. A solo engineer should expect roughly 7–11 months for the MVP and 19–31 months for corrected feature parity. Add 25–35% contingency until product semantics and schema drift are resolved.

## 7. Preferred stack summary

- current supported Laravel and PHP at implementation time;
- Inertia + Vue 3 + TypeScript;
- PostgreSQL;
- Redis-compatible queue/cache;
- Vite and compiled Tailwind/component system;
- managed object storage, email, billing, FX and market data;
- Pest/PHPUnit, Vitest, Playwright, axe and property tests;
- structured logging, error tracing, metrics, uptime and alerting;
- managed deployment initially; modular monolith before microservices.

Indicative infrastructure: $120–$450/month for private beta and $450–$1,800/month for early production, excluding usage-heavy market data, email/SMS and payment fees.

## 8. What should be preserved

- the core “money map” positioning;
- recognizable euro/map visual identity and theme palette work;
- server-first performance/SEO for public pages;
- multilingual communication inventory;
- PostgreSQL as the system of record;
- useful domain concepts in FIFO lot and recurrence implementations;
- user workflows and copy that pass factual/legal review;
- the broad product prototype as a discovery reference.

Preservation does not mean copying defects. Current outputs should become fixtures so every changed result is intentional and explained.

## 9. What should be retired or replaced

- fixed default admin and secret fallbacks;
- route-switch/procedural controller core;
- runtime CDN Tailwind and inline style proliferation;
- request-bound background processing;
- float money arithmetic;
- income/spending-only ledger;
- silent FX fallback;
- synthetic-payment writes during GET;
- custom SMTP delivery as the primary production path;
- partial custom-role UI;
- manual-only pseudo-billing;
- public claims not backed by tested capability;
- disabled zoom and forced orientation.

## 10. Audit limitations

The audit verified repository source, assets, the configured local database schema/migration ledger, PHP syntax, and the stock integration script. It did not verify any production deployment, production data, provider account, live email or payment delivery, legal compliance, real lender/broker agreement, full assistive-technology/browser matrix, load behavior, backups, or incident response.

The local database contains schema drift and therefore may not represent any other environment. External pricing is indicative and must be rechecked at procurement. This report is technical/product analysis, not legal, tax, accounting, credit, or investment advice.

## 11. Decision

**Decision: rebuild while reusing selected components.**

Do not launch the current application publicly. Use it as an internal product prototype, perform Phase 0 immediately, and fund a ledger-first production MVP. Build the new modular Laravel/Vue application beside the prototype and migrate domain by domain. Reuse PostgreSQL data after reconciliation, visual identity, vetted copy/translations, golden calculation fixtures, and selected stock/recurrence concepts; do not preserve the procedural runtime, float money model, or flawed ledger semantics. Release only after Gate B is satisfied. Paid billing and securities recommendations remain behind Gate C.

## 12. Top ten strengths

1. Broad working prototype that exposes many real product decisions.
2. PostgreSQL and `NUMERIC` provide a stronger starting point than file/float-only persistence.
3. Consistent current-user filtering is present across most personal controllers.
4. Admin controller functions consistently invoke the admin guard.
5. Password, remember-me, email verification and passkey concepts all exist.
6. The stock module has recognizable provider/service boundaries and FIFO lot logic.
7. Multiple currencies and dated FX were considered in the schema.
8. Mobile bottom navigation and responsive layouts demonstrate mobile intent.
9. Coherent brand mark, themes, dark mode and multilingual content.
10. Goals, loans, schedules, reserves and investments give strong user-research scope for a focused MVP.

## 13. Top ten weaknesses

1. Predictable default-admin migration and weak secret fallbacks.
2. No account/transfer journal, causing false finance categorization.
3. Silent FX fallback and incomplete historical provenance.
4. Stock oversell and trade/cash reversal defects.
5. Procedural front controller and cross-domain side effects.
6. Request-bound scheduling and external calls.
7. Schema drift, duplicate/legacy tables and weak tenant/domain constraints.
8. Incomplete privacy export/deletion with inaccurate public claims.
9. No CI, reproducible build/deploy, quality test pyramid or production operations.
10. Mobile accessibility is undermined by disabled zoom, forced orientation and dense tables.

## 14. First release scope and deferrals

### First release

- secure verified identity, passkeys and recovery;
- accounts/buckets, balanced transfer journal and reversals;
- manual activity plus safe CSV import;
- categories and one clear budgeting model;
- recurring forecasts separated from posted entries;
- current month, activity search and explainable reports;
- multi-currency with dated source provenance;
- mobile-first Home/Activity/Plan/More UX;
- complete export/delete;
- support/admin basics and production operations.

### Defer

- real-time securities, technical buy/trim signals and tax lots;
- generic investment fixed-return projections;
- promotions engine and complex billing analytics;
- household collaboration;
- open banking;
- SMS/push;
- native mobile apps;
- AI assistant;
- baby steps and unvalidated financial guidance.

## 15. Time-horizon plan

### First 30 days

- complete Phase 0 containment and credential rotation;
- publish corrected internal-only copy;
- reconcile schema/migration drift;
- approve finance semantics, target jurisdictions and MVP scope;
- create golden fixtures and mobile IA prototype;
- select hosting/providers and threat-model the target.

### First 90 days

- production foundation running in development/staging;
- hardened identity and audit;
- clean migration and data transformation pipeline;
- accounts/journal/transfer core with property tests;
- compiled mobile design system;
- queue, observability, backup and restore rehearsal.

### Six months

- private-beta MVP covering activity, imports, budgets, recurrence, FX, reports, export/delete;
- goals/reserves and initial loan reconciliation migrating behind feature flags;
- accessibility and browser journeys passing;
- canary migration and per-user reconciliation reporting;
- independent security review underway or completed.

### Twelve-month vision

- paid public product after Gate C;
- goals/reserves/loans at corrected parity;
- hosted subscription billing and mature support operations;
- evidence-based decision on lightweight holdings versus full investment accounting;
- household/open-banking discovery completed, with only validated capabilities entering delivery;
- old prototype read-only or decommissioned after retention/reconciliation sign-off.
