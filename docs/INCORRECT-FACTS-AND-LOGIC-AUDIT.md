# MyMoneyMap — Incorrect Facts and Logic Audit

**Severity:** Critical = credible account/data/financial corruption or unsafe launch condition; High = materially wrong result/security/privacy promise; Medium = misleading or unreliable behavior; Low = polish, dead code, or maintainability defect.

## 1. Critical findings

| ID | Finding | Evidence | Impact | Required correction |
|---|---|---|---|---|
| C-01 | Default-admin migration creates/resets a fixed administrator identity and password hash | `migrations/028_default_admin.sql` | predictable privileged access on any environment that applies it | remove seed/reset behavior; rotate all affected credentials; audit deployments; bootstrap first admin through a one-time secure process |
| C-02 | Production secrets have hard-coded fallbacks | `config/config.php` and stock configuration | source disclosure or unchanged deployment creates DB/provider compromise | fail closed outside local development; rotate exposed values; use a secret manager; add secret scanning |
| C-03 | Stock oversell corrupts holdings and cash | `src/Stocks/TradeService.php` sale/lot/cash flow | lot consumption stops at holdings but proceeds use full requested quantity | lock position, reject quantity above available, atomically create trade/lots/cash, add concurrency tests |
| C-04 | Internal savings movements are income/spending | goal archive, emergency add/withdraw, investment/virtual month flows | income, spending, savings rate, category and monthly reports become false | introduce balanced transfers and separate posted/forecast semantics; migrate/reclassify history |
| C-05 | FX failure silently returns unchanged amount as target currency | `includes/fx.php` conversion fallback and callers | totals can be wrong by orders of magnitude while appearing valid | return an error/unknown result; block or visibly exclude affected totals; store rate provenance |
| C-06 | Billing secrets are plaintext and displayed in full | billing settings migration/admin billing views | provider account compromise through DB dump, logs, shoulder-surfing or admin XSS | secret manager/envelope encryption; masked write-only UI; rotation and access audit |

## 2. Security, authentication, and authorization

| ID | Sev. | Finding / incorrect assumption | Remediation |
|---|---:|---|---|
| S-01 | High | Password, passkey and remembered-login success do not consistently regenerate the PHP session ID; registration immediately authenticates without rotation. | regenerate on every privilege/auth transition; invalidate prior session state; test fixation |
| S-02 | High | Email verification exists but is not required for account access, while email copy implies protection before dashboard access. Tokens have no explicit expiry. | enforce verified-email middleware; expiring single-use tokens; resend throttling |
| S-03 | High | No login rate limiting, risk throttling or robust failed-login audit was found. | per-account/IP/device limits, progressive delay, alerting and safe logs |
| S-04 | High | Password change does not require the current password and does not revoke other sessions/remember tokens. | re-authentication, session inventory, revoke-all option and notifications |
| S-05 | High | Generated data key can live inside project `storage/data_key.php`; safety depends on web-root configuration. | external secret store/KMS; rotate; prohibit public storage; deployment test |
| S-06 | High | DB connection errors can expose exception details to the client. | generic response, structured private logging, correlation ID |
| S-07 | High | Host/proxy/origin construction trusts request headers without a documented trusted-proxy/allowed-origin boundary. | explicit canonical URL, trusted proxies, WebAuthn RP/origin allowlist |
| S-08 | Medium | Session and remember-cookie `Secure` detection use different rules, especially behind TLS termination. | one trusted proxy-aware cookie policy; production boot assertion |
| S-09 | Medium | CSRF comparison is not constant-time; logout is reachable by GET. | `hash_equals`; POST-only logout; SameSite and origin checks |
| S-10 | High | No CSP, HSTS, clickjacking, referrer or coherent permissions policy was found; runtime CDN compilation expands exposure. | locally built/versioned assets and strict security headers |
| S-11 | High | Authorization is procedural and not uniformly proven for related IDs such as categories/currencies; no tenant DB policy exists. | policy layer, tenant-aware constraints, negative authorization tests |
| S-12 | Medium | Custom role UI accepts arbitrary roles, but `users.role` DB check accepts only free/premium/admin. | adopt real entitlements/RBAC migration or remove custom role UI |
| S-13 | Medium | Admin temporary password can be displayed in a flash/UI. | send expiring reset link; never reveal a reusable password |
| S-14 | Medium | API credentials are app-encrypted, but most customer financial data is plaintext and application code often treats that as “encrypted storage.” | correct copy; threat model; field/disk encryption based on risk |

## 3. Privacy and legal copy

| ID | Sev. | Claim / behavior | Reality and fix |
|---|---:|---|---|
| P-01 | High | “Your financial data is encrypted with your private key.” | Most finance columns are plaintext; the app holds one server-side key for limited fields. Remove claim and describe actual controls. |
| P-02 | High | Export implies “all records.” | Export omits investments, investment transactions, stock portfolio data, subscriptions/invoices/payments, activity, feedback responses, and preferences. Create versioned complete export tests. |
| P-03 | Medium | Export includes remember-token selectors/hashes and passkey credential identifiers. | These are not useful portability data and increase exposure; omit security internals. |
| P-04 | High | Privacy copy describes purge within 30 days. | Deletion is immediate/cascade-oriented, has no demonstrated 30-day purge job, and non-FK rows may remain. Align policy and implementation, including backups. |
| P-05 | Medium | Privacy page's “last updated” date is generated as the current request date. | Store an immutable policy version/effective date and consent record. |
| P-06 | Medium | HTTPS is described as required. | App configuration allows HTTP/localhost and no production HTTPS/HSTS enforcement was found. Add enforcement/boot checks. |
| P-07 | Medium | Passkeys/tokens are broadly described as stored with strong cryptography. | A passkey public key and credential ID are normally stored as public material; be precise and avoid implying private-key custody. |
| P-08 | High | Data deletion relies on cascades, but some ownership columns have no FK. | add constraints and a deletion manifest/test covering primary DB, cache, logs, exports and backups |
| P-09 | Medium | Logs and filesystem cache may contain email or finance summaries. | classify/redact, restrict permissions, set retention, centralize encrypted logs/cache |
| P-10 | High | No evidenced retention schedule, DPA/subprocessor registry, consent/version ledger, or data-subject request operations. | legal/product decision plus operational implementation before launch |

## 4. Financial calculations and data semantics

| ID | Sev. | Finding | Why it is wrong / correction |
|---|---:|---|---|
| F-01 | Critical | FX missing-rate behavior returns the input number. | “100 USD = 100 HUF” can appear. Use an explicit unavailable result. |
| F-02 | High | Future FX cache copies today's rate onto future dates. | creates false dated provenance; forecasts should use a separately labeled assumption and never become historical facts |
| F-03 | High | Ordinary transactions do not consistently snapshot FX despite schema support. | historical reports may change when rate cache changes; snapshot rate/provider/time at posting |
| F-04 | High | PHP casts PostgreSQL `NUMERIC` values to float. | binary rounding causes drift; use decimal/money value objects and exact rounding policy |
| F-05 | High | Two-decimal formatting is treated as universal. | currencies and security quantities have different precisions; use currency/security metadata |
| F-06 | High | Negative/zero transaction amounts are not uniformly rejected. | sign semantics can reverse kind and break aggregates; DB + domain checks |
| F-07 | High | No account or transfer type exists. | dashboard “net” is cash-flow arithmetic, not balance or net worth; implement accounts/journal |
| F-08 | High | Virtual and posted items coexist with inconsistent materialization. | users cannot reliably distinguish forecast from actual; separate states and show them explicitly |
| F-09 | Medium | Cash-flow rule percentages are individually capped but can exceed 100% in aggregate. | plan can promise more income than exists; show/validate intentional over-allocation |
| F-10 | Medium | Rule budget is divided equally among assigned categories. | arbitrary assumption appears like a category cap; let user allocate or label allocation logic |
| F-11 | Medium | Remaining budget clamps at zero. | hides overspend magnitude; show negative variance |
| F-12 | High | Manual cross-currency loan payments subtract principal without conversion, while scheduled payments convert. | balance is corrupted depending on entry path; enforce loan currency or convert with snapshot |
| F-13 | High | Loan interest uses annual/12 regardless of exact dates. | not accurate for many contracts; label nominal monthly estimate or model contract terms |
| F-14 | Medium | Loan “history confirmed” synthesizes due payments and blends estimates with actual ledger. | can imply payments happened; keep projected and posted schedules separate |
| F-15 | High | Loan GET/list backfills payment breakdown records. | read mutates financial history using estimates; run explicit audited migration/recalculation |
| F-16 | Medium | `extra_payment` affects forecast but does not automatically become posted/scheduled payment. | label as scenario and never imply it occurred |
| F-17 | Critical | Emergency deposit/withdrawal and goal payout are spending/income. | internal transfers inflate cash-flow metrics; balanced transfer legs |
| F-18 | Medium | Completed goals remain writable until archived. | progress can exceed completion unexpectedly; define status transitions and invariants |
| F-19 | Medium | Emergency “needs” use all next-month scheduled items, including savings/investments. | not a needs calculation; let user classify essentials and select methodology |
| F-20 | Medium | Emergency guidance prescriptively says the user is “good” after a fixed milestone. | individualized adequacy depends on risk, insurance, dependents and jurisdiction; use neutral education/disclaimer |
| F-21 | High | Generic ETF/stock investment projects a fixed interest rate. | market securities do not accrue deterministic interest; call it a user-authored return scenario |
| F-22 | High | Stock oversell records proceeds beyond owned quantity. | cash and realized results become impossible; reject atomically |
| F-23 | High | Deleting a stock trade does not reverse its linked cash movement. | cash ledger remains wrong; FK cash entry to trade and reverse/rebuild atomically |
| F-24 | High | Market value falls back to average cost when quote is missing. | makes unknown value look current and unrealized P/L look zero; show unavailable/stale separately |
| F-25 | High | Cost basis is converted at current FX for some portfolio totals. | blends security return and FX incorrectly; use acquisition-date FX and present attribution |
| F-26 | Medium | History charts include weekends without robust forward filling and exact-date coverage checks. | false drops/gaps and repeated fetches; use exchange calendars/trading sessions |
| F-27 | Medium | “Average cost” label can obscure FIFO-lot accounting. | define remaining-position average separately from realized FIFO method |
| F-28 | High | Signals output “consider buy/trim” style suggestions from simple SMA/RSI/concentration rules. | could be understood as advice without suitability or risk context; use descriptive analytics, disclaimers, legal review |

## 5. Data model and migration defects

| ID | Sev. | Finding | Remediation |
|---|---:|---|---|
| D-01 | Critical | `028_default_admin.sql` resets a default admin. | remove and rotate/audit as C-01 |
| D-02 | High | Configured DB contains `investments.stock_id` and `units` not represented in repository migrations. | write an idempotent reconciliation migration and schema snapshot test |
| D-03 | High | `036_goal_category.sql` is not recorded as applied although the local schema contains the column. | reconcile migration ledger before any deployment |
| D-04 | Medium | Migration numbers have duplicate prefixes. | immutable timestamp/sequence naming and CI ordering test |
| D-05 | High | Legacy/duplicate tables (`goal_transactions`, `emergency_transactions`) coexist with active ledgers. | migrate, archive and remove after reconciliation |
| D-06 | High | Ownership FKs are missing on some user-scoped rows. | not-null tenant keys, FKs, composite tenant integrity where appropriate |
| D-07 | High | Shared `stocks` delete can cascade user trades. | restrict deletion; soft-retire reference data; preserve financial history |
| D-08 | Medium | Exactly one main user currency is an application convention only. | partial unique index plus transaction-safe selection and existence check |
| D-09 | Medium | Symbol+market is unique, but lookup paths use symbol alone. | canonical instrument/exchange identifier throughout |
| D-10 | Medium | Duplicate transaction indexes and missing query-shaped indexes indicate no systematic query plan review. | production-like `EXPLAIN ANALYZE`, index budget, slow-query monitoring |
| D-11 | High | Amount/date/kind invariants are incompletely enforced by DB. | checks, not-null changes after cleanup, typed enums/domains |
| D-12 | Medium | `v_fx_latest` generates a long date series per currency dynamically. | benchmark or replace with indexed as-of query/materialization |

## 6. Product and marketing inaccuracies

| ID | Sev. | Claim / UI | Actual implementation |
|---|---:|---|---|
| M-01 | High | “CSV and JSON exports available.” | privacy JSON exists; CSV is stock import, not a general export |
| M-02 | High | Pro/Premium trial, cancel-anytime and pricing imply self-service billing. | only manual billing/admin records exist; no checkout, portal, webhook or cancellation flow |
| M-03 | Medium | Landing uses EUR while seeded billing plans are USD. | one canonical, localized provider-backed catalog is needed |
| M-04 | Medium | All plans show 14-day trial while seeded yearly trial differs. | use entitlements/catalog as the single source |
| M-05 | High | “Open or close months.” | no month-close/reopen state exists |
| M-06 | High | “Payoff forecasts you can trust.” | calculations are simplified and inconsistent across manual/scheduled cross-currency paths |
| M-07 | High | “Daily FX for accurate reports.” | missing rates silently preserve numbers and future cache copies current rates |
| M-08 | High | “Encrypted storage by default” / private-key language. | only selected fields are app-encrypted; finance data is predominantly plaintext |
| M-09 | Medium | Welcome email says to connect accounts. | there is no bank/account connection |
| M-10 | Medium | SMS and push appear configurable. | no end-to-end delivery implementation was found |
| M-11 | Medium | Landing links `/demo`, `/terms`, `/contact`, `/about`. | routes do not exist and return the default not-found behavior |
| M-12 | Low | Landing footer year is hard-coded to 2025. | derive from a release/content value |
| M-13 | Medium | Stock configuration README says provider default is null. | configuration defaults to Finnhub |
| M-14 | Medium | README test coverage implies direct FX helper coverage. | current script is narrow stock/FIFO/signals coverage |
| M-15 | High | README quick-start applies only migration `001`. | the modern application requires the complete ordered migration set |
| M-16 | Medium | Baby Steps is presented as a feature/data concept. | table/seed exists but no active user route/controller was found |
| M-17 | Low | Greek language file exists. | Greek is not in the configured available locale list |
| M-18 | Low | `android-chrome-192x192.png` name. | image canvas inspected as 256×256 |
| M-19 | Medium | “Real-time/live” stock wording. | ten-second application cache does not prove exchange-real-time entitlement or freshness |

## 7. Code, UX, and operational defects

| ID | Sev. | Finding | Fix |
|---|---:|---|---|
| O-01 | High | Linked scheduled processing executes on ordinary authenticated requests. | dedicated idempotent scheduler/queue; status/alerting |
| O-02 | High | Scheduler catches some exceptions silently. | structured job failure, retry/dead-letter, user-visible status |
| O-03 | High | No CI/CD, dependency manifests, deterministic asset build or deployment definition. | reproducible build and gated pipeline |
| O-04 | High | One stock test exits successfully when DB is unavailable. | unavailable dependency must fail required integration job |
| O-05 | High | No clean-schema migration rehearsal. | ephemeral PostgreSQL CI applying every migration plus drift check |
| O-06 | Medium | Tailwind CDN/JIT, inline CSS and an unused Tailwind source stylesheet coexist. | compiled assets and component tokens |
| O-07 | High | Zoom is disabled and pinch/double-tap are suppressed. | remove restrictions; WCAG 2.2 AA testing |
| O-08 | High | Mobile landscape is blocked by a full-screen overlay. | responsive landscape layouts; never block device orientation |
| O-09 | Medium | Data tables mostly overflow horizontally on phones. | mobile-prioritized cards/rows, column controls |
| O-10 | Medium | Admin bottom navigation has nine horizontally scrolling items. | admin hub plus 4–5 primary destinations |
| O-11 | High | No queue, health checks, structured monitoring, restore drill or rollback plan. | production operations baseline |
| O-12 | Medium | File cache/log state prevents safe horizontal scaling and may contain PII. | shared secured services, minimization and retention |
| O-13 | Medium | Route `/loals/unlink-schedule` is misspelled. | add correct route, compatibility redirect, tests |
| O-14 | Medium | Home-grown SMTP lacks durable retries, delivery events and suppression. | managed provider + queued delivery + signed webhooks |
| O-15 | Medium | Finnhub IPO date is mapped into `industry`. | correct provider mapping and contract tests |

## 8. Verified and unverified areas

### Verified during this audit

- source inventory and static dependency/relationship graph;
- every PHP file linted successfully;
- route switch and controller guards;
- configured local PostgreSQL tables, columns and applied migration ledger;
- stock service integration script passed against that database;
- brand asset dimensions and design variants;
- mobile behavior inferred from shared layouts, CSS and JavaScript.

### Not verified

- production environment configuration, deployed migrations or real data;
- TLS, reverse proxy, filesystem/document root, DB backup, restore or key rotation;
- live email delivery, DNS records, bounce handling;
- live Stripe/payment processing (none is implemented in code);
- provider entitlements, live quote delay, data redistribution rights or FX SLA;
- browser behavior across the full device/browser matrix and authenticated data states;
- accessibility with assistive technologies;
- load, concurrency, failure recovery and race behavior;
- legal compliance in any target jurisdiction;
- numeric agreement with lender/broker statements;
- whether the hard-coded/default credentials were ever deployed.

No issue marked “verified” should be interpreted as an independent security, legal, accounting, or accessibility certification.

## 9. Classification cross-reference

| Classification required by audit | Findings |
|---|---|
| Factually wrong | P-01, M-01, M-05, M-09, M-11, M-18 |
| Mathematically wrong | F-01 in its failure state; F-22 when proceeds exceed lots |
| Financially misleading | F-08–F-28, M-06, M-07, M-19 |
| Legally risky | P-01–P-10, F-20, F-28, M-02, M-08, M-19 |
| Outdated | M-12, README/config drift in M-13–M-15 |
| Oversimplified | F-13, F-14, F-19–F-21, F-26–F-28 |
| Ambiguous | “balance,” “net,” “interest,” “real-time,” “needs,” and “encrypted” labels across the cited views/copy |
| Internally inconsistent | F-12, D-02–D-05, M-02–M-04, M-13 |
| Technically incorrect | C-01–C-06, S-01–S-14, D-01–D-12, O-01–O-15 |
| Potentially correct but unverifiable | lender results, provider freshness/entitlements, legal compliance, security of deployed storage/proxy, market/tax claims |

## 10. Safer wording and expert-review register

| Current wording/behavior | Safer replacement | User impact if unchanged | External review |
|---|---|---|---|
| “Your financial data is encrypted with your private key.” | “We use transport and infrastructure encryption. Selected profile and integration fields receive additional application-level encryption. See our security page for scope.” | users make privacy decisions on a false premise | privacy/security counsel: Yes |
| “CSV and JSON exports available.” | “A JSON account export is available. It is being expanded to cover all product data; general CSV export is not currently available.” | users may join based on nonexistent portability | privacy counsel: Yes |
| “Daily FX for accurate reports.” | “Reports use the available dated exchange-rate source. Missing or stale rates are shown and excluded until resolved.” | false confidence in multi-currency totals | accounting/FX specialist: Recommended |
| “Payoff forecasts you can trust.” | “Illustrative payoff estimate using a nominal monthly-rate model. Compare with your lender statement; fees and rate changes may not be included.” | credit decisions based on incomplete schedule | consumer-credit counsel: Yes by market |
| “Real-time/live prices.” | “Latest available provider quote,” plus timestamp, exchange and delayed/stale state | trading decisions based on misunderstood freshness | market-data counsel/provider: Yes |
| “Consider buy” / “trim” | “Price is above/below the selected moving-average threshold” and “position is X% of tracked portfolio.” | product may appear to provide individualized investment advice | financial-regulatory counsel: Yes |
| “You’re good now; focus on investments.” | “You reached the selected reserve target. Review whether it remains appropriate for your expenses, dependents, insurance and risk.” | inappropriate generalized emergency guidance | financial education/compliance: Recommended |
| Goal payout as income | “Transfer from goal reserve to available cash” | inflated income/tax-like interpretation | accounting/product: Recommended |
| Emergency deposit as spending | “Transfer to emergency reserve” | inflated spending and false savings rate | accounting/product: Recommended |
| Investment fixed interest for ETF/stock | “User-defined annual return scenario; not a forecast or guaranteed return” | users may interpret deterministic growth as expected performance | investment counsel: Yes |
| Pro/Premium trial/cancel copy | Do not advertise until provider-backed checkout, portal, price/currency and trial terms are active and tested. | commercial/consumer-law exposure | consumer/e-commerce counsel: Yes |
| Privacy policy date equals today | publish a version ID, approval date and effective date | no reliable notice/consent history | privacy counsel: Yes |

## 11. Formula replacement requirements

### Currency conversion

Current failure behavior:

`convert(amount, source, target, date) -> amount` when a required rate is missing.

Required contract:

`convert(...) -> {status, converted_amount?, source_rate?, target_rate?, provider, rate_at, fetched_at, precision}`

`converted_amount` must be absent when status is unavailable. A forecast assumption must have a distinct status and may not be stored as an observed historical rate.

### Transfers

Current behavior creates one income or spending entry. Required posting:

`debit destination account/bucket = amount`  
`credit source account/bucket = amount`

Both legs share a transfer ID and currency/FX snapshot. Net income and net spending effect is zero. Fees, if any, are separate expense entries.

### Loan schedule

Retain the annuity formula only when the contract uses a fixed nominal annual rate with equal monthly periods. Otherwise use the lender/contract schedule or a versioned day-count model:

`interest for period = opening principal × applicable annual rate × day-count fraction`

Fees, insurance, rate changes and rounding must be separately represented. Display projected and posted schedules independently.

### Securities sale

Before posting:

`requested sell quantity <= settled/available long quantity`

Trade, consumed lots, realized P/L and trade-linked cash movement must commit in one transaction. A correction produces a reversal/replacement; it must not leave the cash ledger intact while deleting the trade.

### Portfolio performance

Do not use cost as an invisible substitute for missing market value. Preserve:

- local-currency security return;
- acquisition/sale or period FX effect;
- fees/taxes;
- cash flows;
- quote timestamp/status.

Choose and document time-weighted or money-weighted methodology before displaying “portfolio performance.”
