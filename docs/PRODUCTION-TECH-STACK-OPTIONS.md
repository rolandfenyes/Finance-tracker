# MyMoneyMap — Production Tech Stack Options

**Prepared:** 2026-07-29  
**Cost currency:** USD/month, excluding VAT, payment-provider fees, SMS, market-data licensing, and engineering labor. Pricing changes frequently; validate before purchase.

## 1. Decision criteria

The target must support decimal-safe finance calculations, a multi-currency ledger, background jobs, secure identity, auditability, responsive/PWA UX, admin operations, provider webhooks, test automation, gradual migration, and a small team's ability to operate it.

The current PHP/PostgreSQL domain knowledge is worth preserving. The recommended change is not “rewrite because the code is old”; it is to introduce explicit domains and production controls while reusing PostgreSQL, validated calculation fixtures, copy, themes, and selected stock service concepts.

## 2. Option comparison

| Option | Stack | Strengths | Trade-offs | Feature-parity effort |
|---|---|---|---|---|
| A. Conservative PHP modernization | Laravel, Blade + Livewire, PostgreSQL, Redis, Tailwind build, Horizon/queues | least language churn; fast migration; server-first; strong built-in auth/validation/jobs | complex interactive finance screens can become Livewire-heavy; less clean web/mobile API boundary | 240–360 engineer-days |
| **B. Modular Laravel + Vue (preferred)** | Laravel modular monolith, Inertia, Vue 3 + TypeScript, PostgreSQL, Redis, Tailwind, Vite | preserves PHP knowledge while giving typed, app-like UI; one deployable; gradual domain extraction | two-language frontend/backend; discipline needed to prevent a new monolith | **300–460 engineer-days** |
| C. TypeScript SaaS platform | NestJS, Next.js, TypeScript, PostgreSQL, Redis/BullMQ, React | end-to-end TypeScript; mature API/UI ecosystem; strong hiring pool | largest rewrite; fewer reusable PHP components; more deployment surfaces | 380–600 engineer-days |
| D. API-first PWA variant | Laravel API, Nuxt/Vue PWA, PostgreSQL, Redis | strongest offline/mobile path; clean future-native-client API | synchronization and offline conflict handling add major complexity; two independently versioned apps | 390–620 engineer-days |

Ranges include current feature parity, remediation of known P0/P1 issues, baseline automated tests, data migration, and production deployment. They exclude open banking, native apps, tax filing, AI coaching, and regulated investment advice.

## 3. Preferred architecture

Choose **Option B: a Laravel modular monolith with Inertia/Vue**.

### Core components

- **Backend:** current supported Laravel release at implementation time; PHP 8.3+; Composer.
- **Frontend:** Vue 3, TypeScript, Inertia, Vite, Tailwind compiled at build time, a documented component library.
- **Database:** managed PostgreSQL; decimal minor-unit or arbitrary-precision money value objects; migrations owned by application modules.
- **Queue/cache:** managed Redis-compatible service; queues for schedules, emails, quote refresh, exports, webhooks, and long reports.
- **Object storage:** S3-compatible private storage for exports/imports and audit artifacts.
- **Identity:** Laravel's maintained session/auth primitives plus WebAuthn library, verified-email gate, recovery, session inventory, and rate limiting.
- **Billing:** hosted Stripe Checkout and Customer Portal; signed webhook inbox with idempotency.
- **Email:** managed transactional provider with queued sends, signed event webhooks, bounce/suppression tracking.
- **Observability:** structured logs, Sentry-like exception tracing, OpenTelemetry-compatible metrics/traces, uptime checks.
- **Testing:** Pest/PHPUnit, Vitest, Playwright, axe, mutation/property tests for money and recurrence, clean-schema integration tests.

### Domain boundaries

```text
Identity & Access
Ledger & Accounts
Budgeting & Planning
Recurring Obligations
Goals & Reserves
Loans
Investments & Securities
FX & Market Data
Notifications
Billing & Entitlements
Administration & Audit
```

Keep these modules in one repository and one primary database initially. Communicate through application services and recorded domain events. Do not split into microservices before real load or organizational ownership requires it.

### Money model

The rebuild should introduce:

- account/bucket and transaction/journal models;
- explicit external income, external expense, internal transfer, adjustment, fee, interest, dividend, and trade semantics;
- immutable posted entries, with reversal instead of destructive edits;
- currency-aware decimal types and currency minor-unit metadata;
- FX quote provenance, rate time, provider, retrieval time, and conversion snapshot;
- balanced transfer legs;
- forecast entries separate from posted entries;
- reconciliation and import idempotency;
- calculation versioning for projections.

This one change resolves many current goal, emergency-fund, investment, and reporting defects.

## 4. Deployment topology

### Early production

```text
CDN/WAF
  └─ web application (2 instances when availability matters)
       ├─ managed PostgreSQL
       ├─ managed Redis-compatible queue/cache
       ├─ background worker
       ├─ scheduled dispatcher
       ├─ private object storage
       └─ email, billing, FX, and market-data providers
```

Use separate development, staging, and production projects/accounts. Production DB must be private-networked where the platform permits, encrypted in transit and at rest, automatically backed up, and tested through restore drills.

### Scaling path

1. Cache read models and move all external calls to jobs.
2. Add read replicas only after query evidence.
3. Partition high-volume quote/audit tables if measured volume requires it.
4. Separate market-data ingestion or reporting only when it has an independent scaling/operational profile.
5. Consider a native client only after the API and mobile web task completion metrics are healthy.

## 5. Infrastructure options and indicative costs

Official price references consulted:

- [Supabase pricing](https://supabase.com/pricing) lists Pro from $25/month, including one Micro compute credit, 8 GB disk and seven-day daily backups.
- [Railway pricing](https://railway.com/pricing) lists Hobby at a $5 minimum and Pro at a $20 minimum, with usage-based consumption.
- [Render service documentation](https://render.com/docs/service-types) provides web services, workers, cron, managed PostgreSQL and Redis-compatible key/value services; free services are explicitly unsuitable for production.
- [Amazon RDS for PostgreSQL pricing](https://aws.amazon.com/rds/postgresql/pricing/) is region/configuration based, on-demand or committed, with Multi-AZ options.

The following are planning envelopes, not quotes:

| Stage | Suggested topology | Estimated monthly infrastructure |
|---|---|---:|
| Development/demo | one small app, small DB, low-volume email; no real financial data | $25–$90 |
| Private beta (≤1k active users) | app + worker, managed Postgres, Redis, backups, monitoring | $120–$450 |
| Early production (1k–10k active users) | redundant app, worker, DB with PITR, Redis, object storage, WAF/monitoring | $450–$1,800 |
| Growth (10k–100k active users) | autoscaled web/workers, stronger DB, replicas as needed, enhanced observability | $2,000–$12,000+ |

Market-data licensing can exceed the application infrastructure budget, especially for real-time multi-exchange redistribution. Obtain explicit redistribution rights before setting retail pricing.

### Platform choices

- **Render/Railway:** best for speed and small-team operation. Validate PHP worker/cron topology, private networking, regional data residency, PITR, and predictable egress.
- **Supabase Postgres plus app host:** cost-effective managed PostgreSQL and useful operational tooling. Avoid adopting a second auth model unless intentionally replacing Laravel auth.
- **AWS:** best for control, data residency, network/security depth, and a long scaling runway; highest operational complexity. A production baseline commonly needs ECS/App Runner or equivalent, RDS, ElastiCache, S3, SES, WAF/CloudFront, Secrets Manager, and monitoring.

## 6. Migration strategy

Use a **strangler migration**, not a big-bang cutover.

### Phase 0 — Freeze semantics

- fix/remove the default admin migration and secret fallbacks;
- inventory and classify every monetary event;
- capture golden fixtures from representative current calculations;
- decide which current behaviors are defects versus compatibility requirements;
- define data retention, jurisdictions, advice boundaries, and supported currencies.

### Phase 1 — Foundation

- create Laravel/Vue application, CI/CD, environments, secret management, observability, and test harness;
- reproduce identity with forced email verification, session rotation, rate limits, passkey recovery, and audit logs;
- write a clean, reproducible schema baseline plus explicit transformations from every old migration state.

### Phase 2 — Ledger and planning

- introduce accounts/buckets, posted journal, transfers, forecasts, FX snapshots, imports, and reconciliation;
- migrate transactions, categories, basic incomes, schedules, and cash-flow rules;
- run dual-read reconciliation reports against the old app.

### Phase 3 — Goals, reserves, loans

- migrate ledgers as transfers/allocations without false income/spending;
- version amortization calculations and label estimates;
- replace request-bound schedules with idempotent queue jobs.

### Phase 4 — Investing and stocks

- unify or clearly separate cash investments and securities accounts;
- reject oversells; add trade-linked cash entries and reversals;
- model market/exchange identifiers, splits, distributions, and valuation freshness;
- preserve old outputs as fixtures and explain intentional differences.

### Phase 5 — Admin, billing, communications

- build entitlements from plans, not string-role checks;
- implement hosted checkout/portal and webhook inbox;
- move email to queue/provider events;
- replace settings screens that advertise unimplemented channels.

### Phase 6 — Cutover

- rehearsal on anonymized production-shaped data;
- read-only old system window;
- final delta migration;
- reconciliation totals by user/currency/domain;
- canary users and rollback window;
- old app retained read-only for the agreed audit period.

## 7. Delivery estimates

Assumptions: one engineer-day is a focused implementation/test/review day; ranges include 25–35% uncertainty for undocumented behavior and schema drift.

| Workstream | Engineer-days |
|---|---:|
| Product decisions, finance semantics, UX research | 25–45 |
| Platform, CI/CD, environments, observability | 25–40 |
| Identity, privacy, security and audit | 30–50 |
| Ledger, accounts, transactions, FX | 55–85 |
| Budgeting, months, reports, scheduling | 40–65 |
| Goals, emergency reserve, loans | 40–65 |
| Investments and stock portfolio | 55–90 |
| Admin, billing, notifications | 35–55 |
| Data migration, reconciliation, cutover | 35–60 |
| Accessibility, performance, QA hardening | 35–55 |
| **Production-grade feature parity** | **375–610** |

Calendar translations:

| Team | MVP rebuild | Feature parity | Production-ready extended SaaS |
|---|---:|---:|---:|
| 1 senior full-stack engineer | 7–11 months | 19–31 months | 28–45 months |
| 3-person product team | 3–5 months | 7–12 months | 11–17 months |
| 5-person cross-functional team | 2.5–4 months | 5–8 months | 8–13 months |

An **MVP rebuild** should contain verified identity/privacy, accounts and transfer-safe ledger, transaction entry/import, monthly budget/reporting, currencies/FX provenance, recurring items, responsive mobile UX, export, and operations. Defer stocks, generic investments, full billing promotions, and broad admin analytics unless they are essential to the market test.

## 8. Full option specification

| Concern | A. Laravel/Livewire | **B. Laravel/Inertia/Vue** | C. NestJS/Next.js | D. Laravel API/Nuxt PWA |
|---|---|---|---|---|
| Languages | PHP, JS | PHP, TypeScript | TypeScript | PHP, TypeScript |
| UI/rendering | Blade + Livewire | Vue 3 through Inertia; SSR where needed | Next.js React SSR/app router | Nuxt SSR/client PWA |
| Backend | Laravel modular monolith | Laravel modular monolith | NestJS modular backend | Laravel API |
| Data access | Eloquent + query builder; SQL for reports | Eloquent + query builder; SQL for reports | Drizzle/Prisma plus SQL for reports | Eloquent/query builder |
| API style | server actions + selected JSON | Inertia actions + versioned JSON integrations | REST/OpenAPI initially | versioned REST/OpenAPI |
| Validation/forms | Laravel requests + Livewire forms | Laravel requests + typed Vue form schema | Zod/class-validator + React Hook Form | Laravel requests + Vue form schema |
| Authentication | Laravel sessions/WebAuthn | Laravel sessions/WebAuthn | secure BFF sessions or maintained IdP | secure cookie API sessions/WebAuthn |
| Authorization | policies/gates + entitlements | policies/gates + entitlements | guards/policies + entitlements | policies/scopes + entitlements |
| Jobs/queue | Laravel Queue/Horizon + Redis | Laravel Queue/Horizon + Redis | BullMQ + Redis | Laravel Queue/Horizon |
| Cache | Redis-compatible | Redis-compatible | Redis-compatible | Redis-compatible + cautious device cache |
| Files | private S3-compatible | private S3-compatible | private S3-compatible | private S3-compatible |
| Email | managed provider through queued adapter | same | managed provider/queue | same |
| Payment/billing | Stripe Checkout/Portal/webhooks | same | same | same |
| Market/FX | provider ports, queued ingestion | same | typed provider services/jobs | API provider services/jobs |
| Charts | Chart.js/ECharts | ECharts/Chart.js Vue wrappers | ECharts/Recharts | ECharts/Chart.js |
| State | server/Livewire | server props + Pinia only for client state | server components/query cache; minimal client store | Pinia/query cache + sync state |
| UI/design | compiled Tailwind + Blade components | compiled Tailwind + Vue components/Storybook | Tailwind + React components/Storybook | Tailwind + Vue components/Storybook |
| Mobile | responsive web; install shell | mobile-first responsive web; optional service worker | mobile-first responsive web/PWA | offline-aware PWA is core |
| Testing | Pest, Dusk/Playwright, axe | Pest, Vitest, Playwright, axe | Jest/Vitest, Supertest, Playwright, axe | Pest, Vitest, Playwright, offline/sync suites |
| Monitoring | OpenTelemetry, Sentry-compatible, metrics | same | OpenTelemetry and error tracing | both API and PWA sync telemetry |
| Logging | structured JSON with redaction/request IDs | same | structured Pino-like logging | structured server logs; no sensitive device logs |
| Analytics | consented privacy-aware product events | same | same | offline event consent/sync controls |
| CI/CD | Composer/npm gates, container/image deploy | same | pnpm/npm monorepo, container deploy | independent API/web release train |
| Hosting | Render/Railway/AWS container | Render/Railway/AWS container | Vercel optional frontend + container backend or unified AWS | CDN web + managed API/workers |
| Secrets | platform secret manager/KMS | same | same | same plus device credential policy |
| Backup | managed PG PITR + restore drills | same | same | same; offline state is not backup |
| Deployment | rolling/canary with backwards-compatible migrations | same | coordinated frontend/API canary | versioned API and PWA update/rollback |
| Lock-in | low–medium framework | low–medium framework | medium if Vercel/BaaS features used | medium due offline sync/client contracts |
| Scale expectation | tens of thousands active users before decomposition | same, with clearer interactive UX | strong horizontal scale, more surfaces | strong read/offline UX; sync complexity dominates |
| Low-use infra | $120–$350/month production baseline | $140–$450 | $180–$600 | $180–$650 |
| Moderate infra | $700–$3,500 plus data vendors | $800–$4,000 plus data vendors | $1,000–$5,500 plus data vendors | $1,100–$6,000 plus data vendors |

Vendor price pages support component pricing, but totals above add the minimum operational set: web, worker, database, queue/cache, backup, object storage and monitoring. They are estimates.

## 9. Option-specific effort and migration risk

| Work phase | A | **B** | C | D |
|---|---:|---:|---:|---:|
| Discovery/specification | 20–35 | 25–45 | 30–50 | 35–55 |
| UX/design refinement | 20–35 | 25–45 | 25–45 | 35–60 |
| Design system + responsive mobile | 25–40 | 35–55 | 35–60 | 50–80 |
| Identity/users/privacy | 25–40 | 30–50 | 35–55 | 40–60 |
| Accounts/transactions/categories | 45–70 | 55–85 | 65–100 | 70–110 |
| Budgets/goals/emergency | 35–55 | 45–70 | 55–80 | 55–85 |
| Loans/recurrence/reports/FX | 45–70 | 55–85 | 65–100 | 70–110 |
| Investments/market data | 45–75 | 55–90 | 65–105 | 65–110 |
| Import/export/billing/admin/notifications | 45–70 | 55–85 | 65–100 | 65–105 |
| Security/tests/data migration/DevOps/stabilization | 65–100 | 75–120 | 90–140 | 100–155 |
| **Feature-parity envelope** | **325–490** | **410–630** | **495–735** | **585–930** |

These expanded envelopes are intentionally higher than the early comparison because they itemize discovery, mobile design, compliance and stabilization. Scope can be brought back to the earlier parity range only by reusing more current UI, deferring stock/billing/admin breadth, or accepting less migration automation.

### Calendar range by option

| Option | One experienced full-stack engineer | Small team (3–5 people) |
|---|---:|---:|
| A | 16–25 months | 6–10 months |
| **B** | **21–32 months** | **7–12 months** |
| C | 25–37 months | 9–15 months |
| D | 29–47 months | 10–18 months |

Calendar estimates assume 18–20 effective engineer-days/month per person and do not scale linearly because product decisions, reviews and migration cutover have serial dependencies.

## 10. Scope assumptions

- Existing screenshots/views inform UX, but production components are rebuilt.
- PostgreSQL data is migrated after reconciliation; it is not discarded.
- Finance behavior is rewritten where this audit identifies incorrect semantics.
- Responsive web is the first client; native iOS/Android apps are excluded.
- Option D includes an offline-aware PWA; the other options initially support an installable online-first shell.
- Product/UX/accessibility refinement is included.
- Open banking, bank credentials, tax filing, AI assistant and household tenants are excluded.
- Payment provider fees, market-data licenses and legal/security audits are excluded from engineer-day estimates.
- A 25–35% risk contingency applies until golden fixtures, target jurisdictions and schema repair are approved.
- Full launch requires product/design, QA, security/operations and legal/privacy input; the “one engineer” line is a planning comparison, not a staffing recommendation.

## 11. Milestone estimates across product scopes

| Scope | Included | Engineer-days | One engineer | Small team |
|---|---|---:|---:|---:|
| MVP rebuild | identity, ledger/import, budget/month, recurrence, FX, mobile, export/delete, ops | 140–220 | 7–11 months | 3–5 months |
| Feature parity | corrected current user features, admin, communications and stocks | 375–610 | 19–31 months | 7–12 months |
| Production SaaS | parity plus paid billing, high-assurance operations/compliance and scale tests | 450–720 | 23–36 months | 9–15 months |
| Fully extended | household, open banking, advanced securities/tax, bounded AI and platform API | 700–1,100+ | 35–55+ months | 15–26+ months |

## 12. Why Option B wins

Option A is the fastest conservative path but risks recreating complex finance interactions as server-component state. Option C provides a strong TypeScript ecosystem but discards the most reusable language/runtime knowledge and introduces an additional deployment boundary. Option D is appropriate only if validated offline use is a primary differentiator; sync and device-data risk are otherwise premature.

Option B keeps a single transactional backend close to the current PHP/PostgreSQL system, supports an app-like mobile UI without forcing a public API for every screen, and allows selected JSON APIs later. Laravel policies, validation, queues and migrations replace home-grown cross-cutting code, while Vue/TypeScript makes dense interactive forms and charts more maintainable. It has the best balance of migration risk, team productivity, mobile experience, operational cost and future scalability.

## 13. Production engineering requirements

### Security

- no fallback production secrets;
- secret manager and rotation;
- Argon2id/bcrypt parameters reviewed;
- verified-email gate, rate limits, session rotation/revocation, recovery;
- CSP/HSTS/frame/referrer/permissions headers;
- dependency and container scanning, SBOM, secret scanning;
- tenant-aware authorization policies and tests;
- threat model for import, webhook, passkey, export, and admin flows;
- independent penetration test before public launch.

### Data and reliability

- immutable financial journal and compensating entries;
- decimal-safe calculations end to end;
- clean migrations from empty DB and every supported prior version;
- PITR and encrypted backups;
- restore drills and documented RPO/RTO;
- job idempotency, retries, dead-letter handling;
- provider timeout/circuit-breaker/staleness behavior;
- reconciliation dashboards and audit trail.

### Quality gates

- unit and property tests for money, FX, recurrence, amortization, FIFO;
- integration tests against real PostgreSQL;
- browser tests for the top mobile and desktop journeys;
- accessibility testing to WCAG 2.2 AA target;
- performance budgets and load test for dashboard, month, reports, and quote refresh;
- no test may pass merely because a dependency is unavailable.
