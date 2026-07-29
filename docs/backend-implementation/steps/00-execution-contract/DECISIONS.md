# Step 00 — Approved Architecture and Product Decisions

**Status:** approved

**Approved by:** repository owner

**Approval date:** 2026-07-29

**Applies to:** NestJS/PostgreSQL backend v1

These decisions have priority over the audited correction, legacy PHP implementation, migrations, UI copy, and README in that order. A later change requires an explicit superseding decision and an impact review.

## ADR-001 — Delivery architecture

Use a NestJS/TypeScript modular monolith with PostgreSQL. Angular consumes a versioned REST/OpenAPI contract after backend completion. Astro is limited to public content. Do not introduce microservices, GraphQL, event sourcing, or a CQRS framework.

## ADR-002 — Workspace

Use pnpm with Nx. The intended repository applications are `apps/api`, later `apps/web-app`, and later `apps/marketing`. Shared packages may contain generated API clients, design tokens, and tool configuration. They must not create a second mutable copy of backend domain rules.

## ADR-003 — PostgreSQL access and migrations

Use Kysely with the PostgreSQL dialect and explicit SQL/Kysely migrations. Migrations are not executed implicitly during normal API startup. ORM schema synchronization is prohibited. PostgreSQL constraints remain authoritative for referential and row-level invariants that the database can express.

## ADR-004 — Authentication sessions

Use opaque server-side sessions through `express-session` with Redis persistence through `connect-redis`. Browser credentials use secure `HttpOnly` cookies. Rotate the session identifier after authentication and privilege changes. Support idle and absolute expiry, revocation, and logout. Do not place long-lived bearer credentials in browser storage.

The legacy remember-me feature becomes a longer-lived rotating server-side session. The legacy selector/token table is migration input, not the target model.

## ADR-005 — Password storage

Use the maintained `argon2` package with Argon2id. The initial minimum is memory 19 MiB, iterations 2, parallelism 1. Step 04 must benchmark the selected deployment hardware and may increase—not reduce—the effective work factor unless an explicit security decision supersedes this ADR.

Legacy bcrypt-compatible hashes may be verified during migration/login and rehashed to Argon2id after successful authentication. No password or reusable temporary password is displayed by an admin API.

## ADR-006 — Passkeys

Use `@simplewebauthn/server`. RP ID, allowed origins, trusted proxy behavior, and challenge expiry are explicit configuration. Preserve passkey registration, authentication, labels, counters, last-use data, and deletion. Do not migrate private keys because the server does not and must not possess them.

## ADR-007 — Background work and cache

Use BullMQ and a managed Redis-compatible service for recurrence processing, email, exports, FX refresh, and market-data jobs. Jobs require stable idempotency keys, bounded retry, dead-letter handling, status, metrics, and audit correlation. API reads never run catch-up work or silently post financial entries.

## ADR-008 — Provider baseline

Initial adapters and production candidates are:

| Capability | Approved initial provider | Constraint |
|---|---|---|
| Transactional email | Postmark | production sending disabled until domain, webhook, retention, and suppression configuration pass Step 21 |
| Private object storage | AWS S3 | private buckets and signed short-lived access only |
| FX | Frankfurter | observed dated rates only; unsupported/missing rates remain unavailable |
| Securities data | Finnhub | production use disabled until delay, market coverage, quota, and redistribution rights are verified |
| Error tracking | Sentry | PII/financial-data scrubbing required |
| Hosting | Render | final region, private networking, database, Redis, backup, and restore configuration must pass Step 21 |
| Payment checkout | none in v1 | administrative billing records only |

All providers sit behind application ports. Credentials are environment/secret-manager references and are never returned after write.

## ADR-009 — Backend v1 scope

Backend v1 is full corrected parity for current backend behavior: identity, onboarding, settings, entitlements, transactions, currencies/FX, categories/budgeting, recurrence, reporting, goals, emergency fund, loans, generic investments, securities, feedback, administration, billing records, email, privacy, export, deletion, audit, and legacy migration.

Customer checkout, payment-provider webhooks, SMS, push, bank connections, households, native apps, offline sync, tax engines, receipt OCR, AI, adviser access, and new product tiers are excluded.

Provider-backed behaviors may be disabled by configuration until their production gates pass; their domain contract and deterministic fake adapter must still be testable.

## ADR-010 — Roles and entitlements

Retain only `free`, `premium`, and `admin`.

| Capability | Free | Premium | Admin |
|---|---:|---:|---:|
| currencies | 1 | unlimited | no personal-finance access |
| active goals | 2 | unlimited | no personal-finance access |
| active loans | 2 | unlimited | no personal-finance access |
| categories | 10 | unlimited | no personal-finance access |
| active scheduled items | 2 | unlimited | no personal-finance access |
| cash-flow rule editing | no | yes | no personal-finance access |
| administration | no | no | yes |

This reproduces the capabilities in `src/helpers.php`. Remove arbitrary custom-role assignment and do not invent support/operator roles.

## ADR-011 — Corrected journal and accounts

Use immutable posted journal entries with balanced legs and reversal/replacement correction. Introduce only the accounts needed for corrected parity:

- one default cash account for migrated users;
- module-owned goal buckets;
- one emergency-reserve bucket;
- generic-investment buckets;
- loan liability accounts;
- securities cash/holding subledgers.

Do not add bank/card synchronization, household accounts, reconciliation UX, or other future account products.

External income/expense changes cash flow. Internal transfers do not. Fees, interest, dividends, adjustments, repayments, and trade cash flows have explicit source semantics. API exact values are decimal strings with currency.

## ADR-012 — Scheduling

Scheduled economic types are `income`, `expense`, and `transfer`. Existing generic scheduled payments migrate as expenses. Basic incomes are forecast income. Goal, reserve, and investment contributions are transfers. Loan schedules are repayments. Forecast occurrences remain distinct from posted entries.

Preserve only the implemented RRULE subset: daily, weekly, monthly, yearly, interval, `BYDAY`, `BYMONTHDAY`, `BYMONTH`, `COUNT`, and `UNTIL`.

## ADR-013 — Budgeting

Preserve percentage rules and category assignments. Remove the equal per-category cap as a true budget. Report the rule-level planned value, assigned-category spending, and signed variance. Allocation above 100% is represented as explicit over-allocation, not silently normalized or rejected.

## ADR-014 — Goals and emergency reserve

A goal locks further contributions when it reaches its target. Archive controls lifecycle/visibility only. Contributions and release of funds are transfers; archive/unarchive never manufactures or deletes income.

Preserve a manually configured emergency target, current allocation, history, and optional generic-investment linkage. Remove the automatic claim that every scheduled payment is a “need” and remove prescriptive adequacy/investment guidance. Backend v1 may return raw scheduled totals but does not invent an essential-expense classification.

## ADR-015 — Loans

Preserve the standard fixed nominal-rate monthly annuity estimate and zero-rate case as a versioned illustration. It is not APR. Posted payments are separate from projections. Manual and scheduled cross-currency payments use the same dated conversion contract or are rejected if the approved currency requirement is not met. No read endpoint backfills or mutates payment history.

## ADR-016 — Investments and securities

Generic fixed-rate investment output is a user-authored scenario, not expected return or automatically accrued balance.

Securities preserve FIFO realized P/L, lots, positions, quotes, history, allocation, cash movements, import, and watchlist. Reject oversells atomically. Trade reversal reverses linked cash. Missing market value remains stale/unavailable; cost is not a substitute. Keep descriptive SMA/RSI/concentration values without buy/trim advice. Do not add TWR, MWR, tax lots, corporate actions, or dividend calendars.

## ADR-017 — Administrative billing

Preserve plan, promotion, subscription, invoice, payment, and manual assignment records. There is no customer checkout or provider webhook in v1. Do not store Stripe/provider secrets in billing tables. Do not advertise trial, cancellation, or charging behavior as operational.

## ADR-018 — Privacy and legal boundary

Export and deletion use a complete versioned data manifest and exclude credential/session internals. The backend must not claim GDPR, tax, accounting, credit, or investment compliance. Retention periods, policy effective dates, data residency, and legal wording require separately supplied owner/counsel decisions.

## Superseding a decision

A superseding ADR must state the replaced ADR, reason, schema/API/migration/test impact, compatibility behavior, and owner approval. Implementation agents may not treat a package upgrade or provider convenience as implicit approval to change product semantics.
