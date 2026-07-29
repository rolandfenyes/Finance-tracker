# MyMoneyMap NestJS Backend — Comprehensive Implementation Plan

**Status:** execution plan, not implementation  
**Target:** PostgreSQL + NestJS modular monolith  
**Delivery order:** backend and database → automated backend tests → Postman/Newman acceptance → backend completion gate → Angular → Astro  
**Scope basis:** current PHP behavior, the five audit documents, and the subsequent agreed stack and delivery discussion

## 1. Objective

Build a new NestJS/PostgreSQL backend beside the PHP prototype. It must preserve supported existing product behavior while deliberately correcting the verified security, integrity, financial, scheduling, and privacy faults documented in:

- `docs/MYMONEYMAP-COMPLETE-PROJECT-DOCUMENTATION.md`
- `docs/INCORRECT-FACTS-AND-LOGIC-AUDIT.md`
- `docs/PRODUCTION-TECH-STACK-OPTIONS.md`
- `docs/AUDIT-SUMMARY-AND-RECOMMENDED-ROADMAP.md`

The PHP application remains the behavioral reference, not an unquestionable specification. When current behavior conflicts with an audited correction, the correction wins and must be covered by a regression test explaining the difference.

## 2. Fixed decisions

- NestJS, TypeScript, and PostgreSQL.
- Modular monolith; no microservices.
- Angular will consume a versioned API after backend completion.
- Astro will be limited to the public/marketing website.
- OpenAPI is the API contract and will generate the Angular client.
- Secure `HttpOnly` cookie-based browser sessions; no long-lived bearer token in `localStorage`.
- PostgreSQL `NUMERIC` and a decimal library in NestJS; monetary values must never pass through JavaScript `number`.
- Redis-compatible queue/cache and BullMQ for scheduled work, email, exports, FX, and market-data jobs.
- Forecast/planned records remain distinct from posted financial entries.
- Internal movements use balanced transfer entries and do not affect income or spending.
- Automated unit/integration tests are written during implementation. Postman is the final external API acceptance gate, not the only backend test suite.

## 3. Decisions that remain intentionally open

Step 00 must record the owner's selection before implementation:

- pnpm workspace versus Nx-managed workspace;
- PostgreSQL access layer: Drizzle, Kysely, or carefully configured TypeORM;
- exact maintained WebAuthn package;
- exact session persistence package/store;
- exact password-hashing package and parameters;
- exact transactional email, object-storage, FX, stock-data, error-tracking, and hosting vendors;
- whether the first backend completion gate includes the full stock/billing/admin parity scope or marks selected modules disabled behind feature flags.

Agents must not choose these silently.

## 4. Explicit exclusions

Do not implement:

- open banking or bank credential collection;
- household/shared workspaces;
- adviser/accountant access;
- native mobile applications;
- offline financial synchronization;
- AI assistant or automated financial advice;
- tax filing or jurisdiction-specific tax calculations;
- receipt OCR;
- new subscription tiers or capabilities beyond the current free/premium/admin behavior;
- new financial recommendations, scores, signals, account types, recurrence rules, or provider behavior not supported by current code or an approved correction decision;
- microservices, event sourcing, GraphQL, CQRS framework adoption, or data lakes merely as architectural preferences.

## 5. Source-of-truth order

For every behavior:

1. approved decision record produced in Step 00;
2. audited correction in `INCORRECT-FACTS-AND-LOGIC-AUDIT.md`;
3. current executable PHP controller/helper/service behavior;
4. current migrations and configured-schema audit;
5. current UI copy only when confirmed by backend behavior;
6. README or email copy last.

If evidence conflicts and the audit does not resolve it, stop and create a decision request. Do not guess.

## 6. Target module map

```text
apps/api/src/
  platform/
  identity/
  users/
  entitlements/
  ledger/
  currencies/
  fx/
  categories/
  budgeting/
  recurrence/
  reporting/
  goals/
  emergency-fund/
  loans/
  investments/
  stocks/
  feedback/
  administration/
  billing/
  notifications/
  privacy/
  audit/
  migration/
```

Modules may share the approved platform abstractions, money/currency value objects, authenticated principal, clock, idempotency support, and domain-event/job interfaces. They must not query another module's tables ad hoc.

## 7. Corrected financial model

### Ledger

- Posted financial activity is immutable.
- Corrections use reversal and replacement entries.
- An external income or expense affects one owned account and an income/expense classification.
- An internal transfer has balanced source and destination legs under one transfer identifier.
- A forecast is not a posted journal entry.
- Goal, emergency-fund, and investment funding is a transfer/allocation, not income or spending.
- Fees, interest, dividends, and adjustments have explicit semantics.

### Money and currency

- API monetary values are decimal strings plus ISO currency code.
- Database values use explicitly selected precision/scale appropriate to their role.
- Security quantities and FX rates use separate precision rules from currency amounts.
- Rounding policy is versioned and tested.
- FX conversion returns a structured success/unavailable/stale result.
- Every posted conversion records source currency, target currency, rate, provider/source, rate timestamp, retrieval timestamp, and rounding result.

### Forecasts

- Recurring rules generate forecast occurrences.
- A worker materializes only behaviors explicitly defined as posted.
- Job executions use idempotency keys and are safe to retry.
- Visiting an API endpoint never catches up or writes scheduled financial history.

## 8. Implementation steps and dependency order

| Step | Name | Depends on | Main outcome | Estimated elapsed time with Codex implementation and owner review |
|---:|---|---|---|---:|
| 00 | Execution contract and decisions | — | signed decision record, parity/correction register | 2–4 days |
| 01 | Workspace and NestJS foundation | 00 | runnable API, CI, OpenAPI, configuration | 3–5 days |
| 02 | PostgreSQL baseline and migration system | 01 | reproducible empty schema and DB test harness | 4–7 days |
| 03 | Shared kernel and platform primitives | 01–02 | decimal money, clock, errors, IDs, idempotency | 4–7 days |
| 04 | Identity, sessions, passkeys, authorization | 02–03 | hardened authentication and policy boundary | 8–12 days |
| 05 | Users, onboarding, settings, entitlements | 04 | profile/preferences and current plan limits | 5–8 days |
| 06 | Accounts, journal, transfers, transactions | 02–05 | corrected financial source of truth | 15–22 days |
| 07 | Currencies and FX | 03, 05–06 | dated, reproducible, fail-closed conversion | 7–11 days |
| 08 | Categories, basic income, budgeting | 05–07 | corrected category and cash-flow planning | 8–12 days |
| 09 | Recurrence and scheduled jobs | 06–08 | RRULE-subset forecasts and idempotent workers | 8–12 days |
| 10 | Month/year/dashboard reporting | 06–09 | explainable read models and aggregates | 10–15 days |
| 11 | Goals | 06–10 | contributions and payouts as transfers | 6–9 days |
| 12 | Emergency fund | 06–11 | reserve allocations and neutral guidance data | 5–8 days |
| 13 | Loans | 06–10 | versioned estimates and posted repayment ledger | 10–15 days |
| 14 | Generic investments | 06–10 | balance accounts and labeled return scenarios | 7–11 days |
| 15 | Securities portfolio | 06–07, 10, 14 | safe FIFO trading, cash linkage, valuations | 15–25 days |
| 16 | Feedback, administration, system settings | 04–05, domain modules | guarded operational APIs | 8–12 days |
| 17 | Billing and entitlements | 04–06, 16 | safe administrative parity; provider flow only if vendor approved | 8–12 days |
| 18 | Notifications and email | 04–17 | queued multilingual communications | 8–12 days |
| 19 | Privacy, export, deletion, audit | all data modules | complete manifest-driven lifecycle | 7–10 days |
| 20 | Legacy data migration and reconciliation | 02, 06–19 | repeatable PHP-schema transformation | 10–18 days |
| 21 | Production hardening and backend QA | 01–20 | security, performance, observability, operations gates | 10–15 days |
| 22 | Postman/Newman acceptance and backend freeze | 01–21 | versioned collection and Angular-ready v1 contract | 8–12 days |

These are active-step ranges, not a simple sum. Setup, fixture preparation, review, and safe independent work can overlap. The agreed planning envelope remains approximately **20–28 weeks for corrected backend feature parity**, assuming prompt owner decisions and reviews.

## 9. API rules

- Prefix public application endpoints with `/api/v1`.
- Use resource-oriented nouns and consistent HTTP semantics.
- Return typed error objects with stable machine code, human-safe message, request ID, and field violations where relevant.
- Never return stack traces, provider secrets, password hashes, remember-token hashes, WebAuthn internal challenge state, or encryption keys.
- Use cursor pagination for unbounded activity feeds and explicit filters for dates/status/domain.
- Use idempotency keys for imports, scheduled materialization, payments/webhooks, trade posting, exports, and destructive retryable commands.
- Generate and commit the OpenAPI artifact and generated Angular client only through the approved reproducible command.
- Do not mirror the PHP route shape when it embeds a defect such as `/loals/unlink-schedule`.

## 10. Test strategy

Every implementation step includes:

- unit tests for pure domain rules;
- PostgreSQL integration tests for repositories, constraints, transactions, and concurrency;
- HTTP tests for validation, authentication, authorization, and response contracts;
- negative ownership tests with two users;
- exact-decimal and currency tests for every financial operation;
- fixed-clock tests for dates/recurrence;
- provider contract tests using recorded/synthetic fixtures, never live dependencies in required CI;
- migration tests from empty database.

High-risk property/invariant tests:

- journal entries balance;
- transfers have zero income/spending effect;
- reversal plus original nets to zero;
- no sale exceeds available holdings;
- retrying an idempotent command does not duplicate results;
- unavailable FX never produces a converted amount;
- user A cannot reference or observe user B's objects;
- aggregate reports reconcile to posted entries.

## 11. Review and pull-request protocol

- One bounded vertical slice per pull request.
- Include schema/API/domain change summary, evidence links, tests, migration/rollback notes, and unresolved decisions.
- Never combine formatting or unrelated refactors with financial behavior.
- Owner approval is required for database contract changes, calculation semantics, authentication changes, provider selection, and deliberate parity breaks.
- A reviewed OpenAPI diff is required for every public contract change after Step 22.

## 12. Backend completion definition

The backend is ready for Angular development only when:

- Steps 00–22 are accepted or explicitly feature-flagged in the Step 00 scope record;
- PostgreSQL builds from empty and legacy migration rehearsal passes;
- required tests fail when PostgreSQL/Redis dependencies are unavailable;
- Postman/Newman passes against a production-like deployment;
- OpenAPI and generated Angular client are reproducible;
- all protected resources have authorization and cross-user tests;
- no monetary calculation uses JavaScript `number`;
- no API read causes background financial writes;
- export/deletion cover the complete data manifest;
- health, readiness, logs, metrics, backups, restore, queue failure, and rollback procedures are verified;
- known limitations and intentionally deferred behaviors are documented.

## 13. Step-agent files

Each directory under `docs/backend-implementation/steps/` contains an `AGENTS.md` execution brief. The parent `docs/backend-implementation/AGENTS.md` applies to every step.

