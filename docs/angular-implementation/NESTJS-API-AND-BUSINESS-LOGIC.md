# MyMoneyMap NestJS API and Business Logic

## 1. Purpose and status

This is the frontend-facing reference for the completed MyMoneyMap backend v1.
It explains what the Angular application may call, what each domain means, and
which invariants must remain server-owned.

The frozen contract contains **113 paths, 149 operations, and 155 schemas** at
`/api/v1`. It has automated OpenAPI, generated-client, Postman, route-coverage,
database, and integration checks. The backend completion report is technically
green but still records owner acceptance as pending. Angular development may
use the contract; declaring the backend baseline formally accepted remains an
owner action.

### Source-of-truth order

1. Approved decisions in `steps/00-execution-contract/DECISIONS.md`
2. Frozen OpenAPI at `apps/api/openapi/openapi.json`
3. Implemented NestJS controllers, services, read models, and database
   constraints
4. Corrected audit and backend implementation step records
5. This explanatory document
6. Legacy PHP behavior, only where it was intentionally preserved

Do not hand-edit generated files under `libs/generated/api-client/src`.

## 2. System boundary

The backend is a NestJS/TypeScript modular monolith using PostgreSQL through
Kysely, Redis for sessions and durable work, BullMQ for background jobs, and a
versioned REST/OpenAPI contract. Angular owns presentation and interaction, not
a second copy of financial rules.

Astro remains the public marketing/content application. The Angular application
is the authenticated product and administration UI.

### Included backend domains

Identity, users, onboarding, entitlements, journal, currencies and FX,
categories, planning income, budgeting, recurrence, reporting, goals,
emergency reserve, loans, generic investments, securities, feedback,
administration, administrative billing records, email preferences, privacy
workflows, health, and internal operational diagnostics are implemented.

### Explicit v1 exclusions

Do not expose UI implying support for open banking, connected bank credentials,
households, adviser access, native apps, offline synchronization, automatic
categorization, subscription detection, receipt OCR, tax filing, AI advice,
SMS, push notifications, payment checkout, provider webhooks, self-service
cancellation, corporate actions, dividend calendars, TWR/MWR, or arbitrary
roles. These are not hidden features; they are outside the contract.

## 3. API agreement

### 3.1 Base URL and browser topology

- API prefix: `/api/v1`
- OpenAPI document: `apps/api/openapi/openapi.json`
- Authentication scheme: `mymoneymap.sid` cookie
- Content type: JSON, except broker import content is still represented by its
  defined JSON preview DTO rather than an arbitrary upload endpoint

The API currently does not enable cross-origin CORS. Development should proxy
`/api` from the Angular dev server to NestJS. Production should serve both
applications under the same site, normally with a reverse proxy routing
`/api/v1/*` to NestJS. A separate cross-origin SPA deployment would require a
deliberate backend security and CORS change.

The generated client does not add credentials itself. A same-origin request
sends the cookie naturally; the Angular HTTP policy may additionally clone API
requests with `withCredentials: true` to make the session requirement explicit.
Never put a session identifier or long-lived bearer credential in browser
storage.

There is no CSRF-token header or token-fetch operation in the frozen contract.
Do not invent one in Angular. Keep mutating calls same-origin, retain the
backend’s `SameSite=Lax` cookie policy, and treat any future cross-origin
deployment as a backend security decision rather than a proxy-only change.

### 3.2 Session behavior

- Sessions are server-side and Redis-backed.
- Authentication and privilege changes rotate or revoke sessions.
- Cookies are `HttpOnly`, `SameSite=Lax`, path `/`, and `Secure` in production.
- Sessions have idle and absolute expiry. “Remember me” selects a longer
  absolute server-session lifetime; it is not a persistent browser token.
- `401` means the session is absent, expired, revoked, or no longer valid.
- Most personal financial writes additionally require verified email.
- Admin endpoints require authenticated, verified, fixed-role `admin` access.
- Admin users have administration access but no personal-finance access.

Registration and verification requests use non-enumerating accepted responses.
The UI must not reveal whether an email already exists.

### 3.3 Validation and error envelope

Request bodies are allow-listed, transformed, and validated globally. Unknown
fields are rejected. API failures use:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Request validation failed",
    "requestId": "4d69ed37-7f7f-4318-8b9a-62b3a2469ed5",
    "violations": [
      {
        "field": "amount",
        "code": "matches",
        "message": "must be an exact decimal string"
      }
    ]
  }
}
```

Stable codes are `VALIDATION_FAILED`, `BAD_REQUEST`, `UNAUTHORIZED`,
`FORBIDDEN`, `NOT_FOUND`, `CONFLICT`, `UNPROCESSABLE_ENTITY`,
`TOO_MANY_REQUESTS`, `IDEMPOTENCY_CONFLICT`, `IDEMPOTENCY_IN_PROGRESS`,
`SERVICE_NOT_READY`, `SERVICE_UNAVAILABLE`, and `INTERNAL_SERVER_ERROR`.
Payload-limit failures may return `PAYLOAD_TOO_LARGE`.

Frontend behavior:

- show field violations beside their controls where a field mapping exists;
- show a safe page/form message for the remaining error;
- retain `requestId` for support diagnostics;
- route `401` to sign-in after clearing only client state;
- explain `403` as verification, role, or entitlement denial rather than
  treating it as a missing record;
- present `409` as a state conflict and refresh the authoritative read model;
- present `422` as a valid request that violates a financial invariant;
- respect `Retry-After` on `429`;
- never display raw server stacks or provider responses.

### 3.4 Exact financial and date types

All exact amounts, percentages, FX rates, interest rates, security quantities,
and calculated financial outputs are **decimal strings**, for example
`"1250.40"` or `"0.075"`. Never coerce them to JavaScript `number` for
calculation. Use an approved arbitrary-precision decimal utility in frontend
formatting/adapters and send the exact string to the API.

- Currency codes are uppercase supported codes.
- Calendar dates are `YYYY-MM-DD`.
- Instants are ISO 8601 UTC timestamps.
- The current-month endpoint uses the backend’s Budapest calendar boundary.
- Currency metadata supplies `minorUnit` and `roundingMode`.
- Missing, stale, delayed, and unavailable conversion/market values are domain
  states. Do not replace them with zero, cost, or the source amount.

The backend remains authoritative for totals, conversion, allocation,
amortization, FIFO, balances, progress, and forecasts. Frontend calculations
may only support display, input previews clearly labeled as estimates, or chart
coordinates derived from returned values.

### 3.5 Idempotent financial commands

The following commands require an `Idempotency-Key` header:

- journal create, correction, and reversal;
- loan payment, payment correction, and reversal;
- goal contribution, contribution correction, and reversal;
- emergency-reserve contribution, withdrawal, and movement reversal;
- generic-investment movement and reversal;
- privacy export and deletion request.

Generate a UUID per user intent and retain it while the same submission is
pending or retried. Generate a new key only after the user deliberately starts
a new command. `IDEMPOTENCY_IN_PROGRESS` means poll/refresh instead of issuing a
different duplicate command. `IDEMPOTENCY_CONFLICT` means the same key was
reused with a different payload.

Securities trades/imports use their own implemented transactional and
fingerprint semantics; do not add a header that the frozen operation does not
declare.

### 3.6 Pagination, filtering, and refresh

Cursor pages return `items` and `nextCursor`. Cursors are opaque: store and send
them unchanged. Do not decode them or infer ordering from them.

Main filtered reads:

- journal: `dateFrom`, `dateTo`, `limit`, `cursor`;
- month reports: `kind`, `categoryId`, `currency`, `query`, `minAmount`,
  `maxAmount`, `limit`, `cursor`;
- recurrence: optional `from` and `to` forecast range;
- user/admin feedback and admin users: cursor plus documented filters;
- securities prices: required `from` and `to`;
- securities quote: required `instrumentId`.

Report totals cover the complete filtered source set and remain stable while
the activity cursor advances.

### 3.7 Generated Angular client

Use the generated services and DTOs from `libs/generated/api-client/src`.
Create handwritten feature facades around them for UI orchestration; do not
copy generated DTOs into feature folders.

```ts
providers: [
  provideHttpClient(withInterceptors([apiSessionInterceptor, apiErrorInterceptor])),
  provideApiConfiguration(''),
];
```

`rootUrl: ''` is correct for the same-origin proxy/deployment. Regenerate after
an approved contract change:

```bash
pnpm openapi:generate
pnpm api-client:generate
pnpm contracts:check
```

### 3.8 Known generated-contract blockers

Operation and route coverage does not by itself guarantee that a generated
client is usable. The current frozen artifact has frontend-blocking response
typing gaps:

- CG-001 is closed: WebAuthn registration/authentication options, nested browser
  credentials, registration success, owned-passkey listing, and UUID deletion
  are explicitly typed.
- Notification-preference reads and updates have no generated response schema.
- Securities, administration, and billing operations use wrappers whose
  generated `data` property is `{}`.

Angular must not solve these by hand-editing generated code, casting to
application-owned interfaces, or copying backend TypeScript types. Step 00 of
the Angular plan must create a complete contract-gap register. Each affected
feature remains blocked until an explicitly approved backend/OpenAPI-only
correction supplies concrete response schemas, regenerates the client, and
passes the backend contract freeze checks again.

## 4. Roles and entitlements

| Capability             | Free |   Premium |       Admin |
| ---------------------- | ---: | --------: | ----------: |
| Personal finance       |  yes |       yes |          no |
| Administration         |   no |        no |         yes |
| Cash-flow rule editing |   no |       yes |          no |
| Currencies             |    1 | unlimited | unavailable |
| Active goals           |    2 | unlimited | unavailable |
| Active loans           |    2 | unlimited | unavailable |
| Categories             |   10 | unlimited | unavailable |
| Active scheduled items |    2 | unlimited | unavailable |

Use `GET /users/me` as the UI capability source. Route guards and hidden or
disabled controls improve UX, but the server remains authoritative. A free-user
limit is not a reason to hide existing resources; it blocks creation after the
limit is reached. The UI must not invent upgrade/checkout behavior because v1
contains administrative billing records only.

Supported locales are `en`, `es`, and `hu`, with English fallback for email
templates. Supported persisted palette identifiers are:

`polar-quartz`, `verdant-horizon`, `celestial-tide`, `blush-nocturne`,
`ember-vanguard`, `lilac-eclipse`, `solaris-bloom`, and `dune-mirage`.

The backend stores a palette identifier, not a light/dark/system mode.

## 5. Business logic by domain

### 5.1 Identity and users

Registration hashes passwords with Argon2id and queues verification. Verification
tokens are expiring and single-use; resends are throttled. Password and passkey
logins establish rotated server sessions and are rate-limited. Password change
requires the current password and revokes the user’s sessions.

Passkeys use WebAuthn with explicit relying-party and allowed-origin
configuration. Registration requires an authenticated, verified session.
Authentication can begin with an optional email. Credential payloads must pass
unchanged between the browser WebAuthn API and generated service.

`GET /users/me` returns profile, role, verification, palette, locale, and
entitlements. Onboarding is server-directed through `currentStep` and `next`;
the frontend must follow the returned destination instead of deriving progress
from whether lists happen to be empty.

### 5.2 Journal

The journal is the financial source of truth. Every posted entry is immutable
and balanced. Manual types are external income, external expense, internal
transfer, adjustment, fee, interest, and dividend. Module services additionally
own loan repayment and trade cash semantics.

“Edit” means reverse and replace. “Delete” means reverse. Internal transfers
move value between owned buckets and have zero income/expense effect. The UI
must never mutate or visually erase posted history.

### 5.3 Currency and FX

The catalogue supplies supported currencies and rounding metadata. Each
personal-finance user has currency memberships and exactly one main currency.
The main currency cannot be removed, and entitlement limits apply.

FX conversion is dated and reproducible, with provider, rate observation,
fetch time, and status. Historical journal conversions retain snapshots.
Unavailable conversion blocks or marks the affected operation/report; it is not
a successful 1:1 conversion. Forecast assumptions are separate from observed
rates.

### 5.4 Categories, planning income, and budgeting

Categories are owned, typed as income or spending, color-coded, and may be
protected system categories. A protected or referenced category cannot be
deleted.

Basic income is a planning input with a validity range; creating it does not
post income. Percentage cash-flow rules are premium-editable. Spending
categories may be assigned to a rule. The backend returns rule-level planned
amount, assigned spending, and signed variance. Percentages above 100% are
reported as `over_allocated`; they are not normalized or silently rejected.
Negative variance must remain visible.

### 5.5 Recurrence

Supported RRULE behavior is limited to daily, weekly, monthly, yearly,
`INTERVAL`, `BYDAY`, `BYMONTHDAY`, `BYMONTH`, `COUNT`, and `UNTIL`. Every rule
has an explicit economic type: income, expense, or transfer.

Read-time forecasts are side-effect free. Posting is performed by bounded,
idempotent BullMQ work, never by a page load. The UI can manage rules and show
forecast occurrences but must not present them as confirmed transactions.

### 5.6 Reporting

Reporting is the authoritative Angular dashboard boundary. A month contains:

- a period with calendar boundaries and timezone;
- posted cash-flow summary;
- forecast summary and explainable sources;
- combined projection;
- rule-level budget read model;
- paged posted activity with source drill-down IDs.

Summary fields are income, expense, transfer, adjustment net, trade cash net,
and net cash flow. **Net cash flow is not account balance or net worth.**
Posted, forecast, and combined values must remain visually distinct. Conversion
completeness and freshness must be visible when incomplete or stale.

Year reporting returns explainable month-by-month and annual aggregates.

### 5.7 Goals

Goals have target amount/currency, optional deadline/category, priority,
lifecycle status, archive state, contribution history, and optional recurring
transfer forecast. Progress and remaining amount are ledger-derived.

Contributions are internal transfers. A contribution exceeding the exact
remaining target is rejected, not capped. A completed goal locks further
contributions. A target cannot be reduced below current progress. Reversing a
contribution can reopen a completed goal. Archive/unarchive changes
visibility/lifecycle only; it never creates or deletes income. Only empty goals
without financial history may be deleted.

### 5.8 Emergency reserve

There is one user reserve with a manually defined target, ledger-derived
allocation, movement history, optional generic-investment linkage, and raw
scheduled-activity context. Contributions and withdrawals are internal
transfers, so withdrawals are not income.

The methodology is explicitly `manual_user_defined` and educational only. Raw
scheduled activity is not classified as “needs,” and the backend does not claim
the user is safe or should invest. The UI must retain this neutral wording.

### 5.9 Loans

Loans keep configuration, outstanding principal, posted repayment history,
optional recurrence, completion/archive state, and a separate projected
schedule. A posted payment has explicit principal, interest, and fee
components.

The calculation is a versioned standard fixed nominal-rate monthly annuity
illustration, including the zero-rate case. It is not APR and not a lender
quote. Projections are not confirmed payments. Reads never create backfilled
payments. Reversal restores the derived loan state; history-bearing loans are
archived instead of deleted.

### 5.10 Generic investments

Generic investments are `savings`, `etf`, or `stock` records separate from the
securities portfolio. Deposits and withdrawals are internal transfers and the
balance is ledger-derived. Optional recurrence is a transfer forecast.

The optional nominal compound result is a user-authored scenario. Zero rate is
allowed, negative rate is rejected, and missing rate disables it. It is neither
guaranteed nor expected return and never changes the posted balance.

### 5.11 Securities

Securities use canonical instrument identity including symbol and market.
Trades post linked cash/fee journals, rebuild FIFO lots, and return positions,
allocation, realized P/L, quotes, history, and descriptive indicators.

Overselling is rejected atomically. Reversing a trade reverses linked cash and
fee effects and rebuilds FIFO. Broker import is preview-first, validated,
fingerprinted, and committed atomically. Quotes explicitly distinguish
available, delayed, stale, and unavailable. Missing market value must not be
replaced with cost. Indicators are descriptive, never buy/trim advice.

Market data can remain disabled until its production gate is approved. A
refresh job is observable and retry-safe; a refresh request is not evidence that
new data exists.

### 5.12 Feedback and administration

Users create bug or idea feedback, view only their own records and staff
responses, close/reopen their own item, and delete it. Admin workflows use a
separate guarded surface with masked identities, bounded analytics, feedback
workflow fields, attributed responses, and audit events.

Admin user management supports only fixed role assignment, active/inactive
status, secure password-reset request, verification resend, and verified email
change request. Tokens and temporary passwords are never returned.

System settings expose non-secret values. Integration secrets are write-only,
encrypted, and later represented as masked/configured state. No migration
runner or impersonation endpoint exists.

### 5.13 Administrative billing

Billing endpoints manage plan, promotion, subscription, invoice, and payment
records for administrators. They do not charge a card, start hosted checkout,
serve a customer portal, process provider webhooks, or implement self-service
cancellation. UI copy must describe administrative records, not operational
payment-provider behavior.

### 5.14 Notifications

Users may read and update educational email preference. Transactional security
mail remains mandatory. Templates are versioned for EN/ES/HU with English
fallback. Delivery is durable, retryable, idempotent, suppressible, and
observable without logging financial payloads or addresses.

Admin can inspect template contracts, render synthetic previews, enqueue
synthetic test jobs, and configure the single approved email channel. There are
no SMS or push channels. Postmark delivery remains disabled unless every
production approval and configuration gate passes.

### 5.15 Privacy

Exports and deletion are asynchronous, idempotent workflows based on a
versioned data manifest. Exports include useful JSON/CSV datasets but exclude
password/session hashes, WebAuthn internals, and secrets. Status is
owner-isolated and may eventually expose a short-lived private URL.

Exports remain disabled until private S3 storage and owner-approved TTLs exist.
Deletion requires password reauthentication and cleans domain data, jobs,
caches, and external state according to the approved workflow. The product must
not claim GDPR compliance or invent legal retention periods.

## 6. Complete endpoint catalogue

All paths below are relative to the API origin.

### Identity, profile, and onboarding

| Method    | Path                                         | Frontend use                                   |
| --------- | -------------------------------------------- | ---------------------------------------------- |
| POST      | `/api/v1/auth/registrations`                 | Register; accepted/non-enumerating             |
| POST      | `/api/v1/auth/email-verifications`           | Consume verification token                     |
| POST      | `/api/v1/auth/email-verification-requests`   | Request throttled resend                       |
| POST      | `/api/v1/auth/sessions`                      | Password login and optional remember session   |
| DELETE    | `/api/v1/auth/session`                       | Logout                                         |
| PUT       | `/api/v1/users/me/password`                  | Change password and revoke sessions            |
| POST      | `/api/v1/auth/passkeys/registration-options` | Begin passkey enrollment                       |
| POST      | `/api/v1/auth/passkeys`                      | Finish passkey enrollment                      |
| GET       | `/api/v1/auth/passkeys`                      | List the current user's safe passkey summaries |
| POST      | `/api/v1/auth/passkey-sessions/options`      | Begin passkey authentication                   |
| POST      | `/api/v1/auth/passkey-sessions`              | Finish passkey authentication                  |
| DELETE    | `/api/v1/auth/passkeys/{id}`                 | Delete owned passkey                           |
| GET/PATCH | `/api/v1/users/me`                           | Read/update profile and locale                 |
| GET/PATCH | `/api/v1/users/me/preferences/theme`         | Read/update palette                            |
| GET/PATCH | `/api/v1/users/me/onboarding`                | Read progress/complete tutorial                |

### Currency, planning, and journal

| Method       | Path                                       | Frontend use                            |
| ------------ | ------------------------------------------ | --------------------------------------- |
| GET          | `/api/v1/currencies`                       | Supported catalogue                     |
| GET/POST     | `/api/v1/users/me/currencies`              | List/add membership                     |
| DELETE       | `/api/v1/users/me/currencies/{code}`       | Remove non-main membership              |
| PUT          | `/api/v1/users/me/main-currency`           | Select main currency                    |
| GET/POST/PUT | `/api/v1/budget-rules`                     | List/create/atomically initialize rules |
| PATCH/DELETE | `/api/v1/budget-rules/{id}`                | Update/delete rule                      |
| GET/POST     | `/api/v1/categories`                       | List/create categories                  |
| PATCH/DELETE | `/api/v1/categories/{id}`                  | Update/delete category                  |
| PUT          | `/api/v1/categories/{id}/budget-rule`      | Assign/clear spending rule              |
| GET/POST     | `/api/v1/basic-incomes`                    | List/create forecast-only income        |
| PATCH/DELETE | `/api/v1/basic-incomes/{id}`               | Update/delete planning income           |
| GET/POST     | `/api/v1/journal/entries`                  | List/post immutable entries             |
| POST         | `/api/v1/journal/entries/{id}/corrections` | Reverse and replace                     |
| POST         | `/api/v1/journal/entries/{id}/reversals`   | Reverse entry                           |

### Recurrence and reporting

| Method       | Path                                    | Frontend use                  |
| ------------ | --------------------------------------- | ----------------------------- |
| GET/POST     | `/api/v1/recurring-rules`               | List with forecast/create     |
| PATCH/DELETE | `/api/v1/recurring-rules/{id}`          | Update/delete rule            |
| GET          | `/api/v1/reports/months/current`        | Main dashboard report         |
| GET          | `/api/v1/reports/months/{year}/{month}` | Filtered historic month       |
| GET          | `/api/v1/reports/years`                 | Available report years        |
| GET          | `/api/v1/reports/years/{year}`          | Annual and monthly aggregates |

### Goals, reserve, loans, and investments

| Method          | Path                                                          | Frontend use                          |
| --------------- | ------------------------------------------------------------- | ------------------------------------- |
| GET/POST        | `/api/v1/goals`                                               | List/create goals                     |
| PATCH/DELETE    | `/api/v1/goals/{id}`                                          | Update/delete empty goal              |
| POST            | `/api/v1/goals/{id}/archive`                                  | Archive                               |
| POST            | `/api/v1/goals/{id}/unarchive`                                | Unarchive                             |
| POST            | `/api/v1/goals/{id}/contributions`                            | Post contribution transfer            |
| POST            | `/api/v1/goals/{goalId}/contributions/{id}/corrections`       | Correct contribution                  |
| POST            | `/api/v1/goals/{goalId}/contributions/{id}/reversals`         | Reverse contribution                  |
| POST/PUT/DELETE | `/api/v1/goals/{id}/recurring-rule`                           | Create/replace/remove forecast        |
| GET             | `/api/v1/emergency-reserve`                                   | Reserve read model                    |
| PUT             | `/api/v1/emergency-reserve/target`                            | Set target/linkage                    |
| POST            | `/api/v1/emergency-reserve/contributions`                     | Transfer into reserve                 |
| POST            | `/api/v1/emergency-reserve/withdrawals`                       | Transfer out                          |
| POST            | `/api/v1/emergency-reserve/movements/{id}/reversals`          | Reverse movement                      |
| GET/POST        | `/api/v1/loans`                                               | List/create loans                     |
| PATCH/DELETE    | `/api/v1/loans/{id}`                                          | Update/delete history-free loan       |
| POST            | `/api/v1/loans/{id}/archive`                                  | Archive repaid loan                   |
| POST            | `/api/v1/loans/{id}/payments`                                 | Post repayment                        |
| POST            | `/api/v1/loans/{loanId}/payments/{id}/corrections`            | Correct payment                       |
| POST            | `/api/v1/loans/{loanId}/payments/{id}/reversals`              | Reverse payment                       |
| POST/PUT/DELETE | `/api/v1/loans/{id}/recurring-rule`                           | Manage repayment schedule             |
| GET/POST        | `/api/v1/investments`                                         | List/create generic investments       |
| PATCH/DELETE    | `/api/v1/investments/{id}`                                    | Update/delete history-free investment |
| POST            | `/api/v1/investments/{id}/movements`                          | Deposit/withdraw                      |
| POST            | `/api/v1/investments/{investmentId}/movements/{id}/reversals` | Reverse movement                      |
| POST            | `/api/v1/investments/{id}/recurring-rule`                     | Create contribution forecast          |

### Securities

| Method     | Path                                          | Frontend use                                |
| ---------- | --------------------------------------------- | ------------------------------------------- |
| GET        | `/api/v1/securities/portfolio`                | FIFO portfolio/read models                  |
| GET        | `/api/v1/securities/activity`                 | Trades, cash, realized results              |
| POST       | `/api/v1/securities/trades`                   | Buy/sell atomically                         |
| POST       | `/api/v1/securities/trades/{id}/reversals`    | Reverse linked trade effects                |
| POST       | `/api/v1/securities/cash-movements`           | Move portfolio cash                         |
| POST       | `/api/v1/securities/imports`                  | Preview broker CSV data                     |
| POST       | `/api/v1/securities/imports/{id}/commit`      | Commit valid preview                        |
| POST       | `/api/v1/securities/refresh-jobs`             | Queue market refresh                        |
| GET        | `/api/v1/securities/refresh-jobs/{id}`        | Poll refresh status                         |
| POST       | `/api/v1/securities/portfolio-clear-requests` | Step-up destructive reversal workflow       |
| GET        | `/api/v1/securities/quotes`                   | Stored quote; requires `instrumentId` query |
| GET        | `/api/v1/securities/instruments/{id}`         | Instrument metadata                         |
| GET        | `/api/v1/securities/instruments/{id}/prices`  | Trading-day prices/indicators               |
| PUT/DELETE | `/api/v1/securities/watchlist/{id}`           | Watch/unwatch                               |

### Feedback, notifications, and privacy

| Method    | Path                                        | Frontend use                      |
| --------- | ------------------------------------------- | --------------------------------- |
| GET/POST  | `/api/v1/feedback`                          | List/create owned feedback        |
| PATCH     | `/api/v1/feedback/{id}/status`              | Close/reopen                      |
| DELETE    | `/api/v1/feedback/{id}`                     | Delete owned feedback             |
| GET/PATCH | `/api/v1/users/me/notification-preferences` | Educational email preference      |
| POST      | `/api/v1/privacy/exports`                   | Queue export                      |
| GET       | `/api/v1/privacy/exports/{id}`              | Poll export                       |
| POST      | `/api/v1/privacy/deletion-requests`         | Reauthenticate and queue deletion |

### Administration

| Method     | Path                                                  | Frontend use                            |
| ---------- | ----------------------------------------------------- | --------------------------------------- |
| GET        | `/api/v1/admin/dashboard`                             | Defined operational counts              |
| GET        | `/api/v1/admin/analytics`                             | Defined registration/account metrics    |
| GET        | `/api/v1/admin/users`                                 | Filtered cursor page                    |
| GET        | `/api/v1/admin/users/{id}`                            | Masked detail and login activity        |
| PUT        | `/api/v1/admin/users/{id}/role`                       | Assign fixed role                       |
| PUT        | `/api/v1/admin/users/{id}/status`                     | Activate/deactivate and revoke          |
| POST       | `/api/v1/admin/users/{id}/password-reset-request`     | Secure recovery request                 |
| POST       | `/api/v1/admin/users/{id}/email-verification-request` | Verification request                    |
| POST       | `/api/v1/admin/users/{id}/email-change-request`       | Verified change request                 |
| GET        | `/api/v1/admin/feedback`                              | Search feedback workflow                |
| PATCH      | `/api/v1/admin/feedback/{id}`                         | Update feedback workflow                |
| POST       | `/api/v1/admin/feedback/{id}/responses`               | Attributed staff response               |
| GET        | `/api/v1/admin/system`                                | Non-secret settings/masked integrations |
| PATCH      | `/api/v1/admin/system/settings`                       | Validated settings                      |
| PUT/DELETE | `/api/v1/admin/integrations/{service}`                | Write-only secret lifecycle             |
| GET        | `/api/v1/admin/email-templates`                       | Template contracts                      |
| POST       | `/api/v1/admin/email-templates/{code}/preview`        | Synthetic preview                       |
| POST       | `/api/v1/admin/email-test-jobs`                       | Synthetic queued test                   |
| GET/PATCH  | `/api/v1/admin/notification-channels/email`           | Email channel configuration             |
| PATCH      | `/api/v1/admin/email-settings`                        | Compatibility alias for email settings  |
| GET        | `/api/v1/admin/operations/queues`                     | PII-safe queue/circuit diagnostics      |

### Administrative billing

| Method           | Path                                     | Frontend use                          |
| ---------------- | ---------------------------------------- | ------------------------------------- |
| GET              | `/api/v1/admin/billing/summary`          | Administrative record summary         |
| GET/POST         | `/api/v1/admin/billing/plans`            | List/create plans                     |
| GET/PATCH/DELETE | `/api/v1/admin/billing/plans/{id}`       | Plan detail/update/delete             |
| GET/POST         | `/api/v1/admin/billing/promotions`       | List/create promotions                |
| GET/PATCH/DELETE | `/api/v1/admin/billing/promotions/{id}`  | Promotion detail/update/delete        |
| POST             | `/api/v1/admin/billing/promotions/trial` | Create administrative trial promotion |
| PUT              | `/api/v1/admin/users/{id}/subscription`  | Assign subscription record            |
| PATCH            | `/api/v1/admin/invoices/{id}`            | Update invoice record                 |
| POST             | `/api/v1/admin/payments`                 | Create payment record                 |
| PATCH            | `/api/v1/admin/payments/{id}`            | Update payment record                 |

### Health

| Method | Path                   | Use                        |
| ------ | ---------------------- | -------------------------- |
| GET    | `/api/v1/health/live`  | Process liveness           |
| GET    | `/api/v1/health/ready` | PostgreSQL/Redis readiness |

Health is operational infrastructure, not an authenticated user page.

## 7. Production gates visible to the frontend

| Capability             | Contract behavior                                                              |
| ---------------------- | ------------------------------------------------------------------------------ |
| Email delivery         | UI may manage preference/settings; real delivery remains gated                 |
| FX refresh             | Deterministic/test and optional provider behavior; unavailable is explicit     |
| Securities market data | Portfolio works; live refresh may be disabled                                  |
| Privacy export         | Request can report service unavailable until private storage/TTLs are approved |
| Billing                | Admin records only; no checkout                                                |
| Legacy migration       | No application endpoint                                                        |
| Operations metrics     | Internal authenticated operations concern                                      |

Feature-gate failures must be presented honestly. Do not turn a disabled provider
into optimistic success copy.

## 8. Frontend integration checklist

- Use the generated client and keep `pnpm contracts:check` green.
- Proxy `/api` in development and use same-site deployment in production.
- Bootstrap the session with `GET /users/me`.
- Route by `entitlements`, not by hardcoded plan guesses.
- Preserve exact decimals as strings.
- Use opaque cursors unchanged.
- Add stable idempotency keys only where declared.
- Distinguish posted, forecast, and combined values.
- Display stale/unavailable data states.
- Implement correction/reversal language instead of edit/delete for history.
- Keep admin and personal-finance shells separate.
- Never log request bodies containing credentials or financial detail.
- Do not imply unavailable product/provider capabilities.
