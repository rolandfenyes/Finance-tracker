# Angular Route Map

Status: **approved by Decision Set A on 2026-07-31**

All routes are client routes. API paths remain relative same-origin `/api/v1`.
`SessionGuard`, `OnboardingGuard`, `EntitlementGuard`, and `AdminGuard` improve
navigation but never replace backend authorization. Each feature owner is a
route-level lazy boundary and may import only core, shared, design system, and
the generated client.

## Shell and redirect rules

| Route            | Shell      | Guard                                  | Owner          | Lazy | Purpose and API dependency                                                                                                                   |
| ---------------- | ---------- | -------------------------------------- | -------------- | ---- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `/`              | Bootstrap  | none                                   | core routing   | no   | Resolve session through `GET /users/me`; route to login, onboarding `next`, `/app/home`, or `/admin` only after authoritative state is known |
| `/auth/**`       | Auth       | `AnonymousOnlyGuard` where appropriate | identity       | yes  | Anonymous authentication and verification                                                                                                    |
| `/onboarding/**` | Onboarding | `SessionGuard`, `OnboardingGuard`      | onboarding     | yes  | Server-driven `next`; no list-emptiness inference                                                                                            |
| `/app/**`        | Product    | `SessionGuard`, completed onboarding   | product shell  | yes  | Personal-finance application                                                                                                                 |
| `/admin/**`      | Admin      | `SessionGuard`, `AdminGuard`           | administration | yes  | Fixed `admin` role only                                                                                                                      |
| `/**`            | Minimal    | none                                   | core routing   | no   | Localized not-found page; never redirects a 403 to a misleading 404                                                                          |

## Anonymous identity

| Route                     | Shell | Guard          | Owner / step  | Lazy | API dependencies                                                                          |
| ------------------------- | ----- | -------------- | ------------- | ---- | ----------------------------------------------------------------------------------------- |
| `/auth/login`             | Auth  | anonymous-only | identity / 03 | yes  | `POST /auth/sessions`, `GET /users/me`                                                    |
| `/auth/register`          | Auth  | anonymous-only | identity / 03 | yes  | `POST /auth/registrations`                                                                |
| `/auth/verify-email`      | Auth  | none           | identity / 03 | yes  | `POST /auth/email-verifications`; remove token from URL/history immediately after reading |
| `/auth/verification-sent` | Auth  | none           | identity / 03 | yes  | `POST /auth/email-verification-requests`                                                  |
| `/auth/passkey`           | Auth  | anonymous-only | identity / 03 | yes  | passkey option/login operations; blocked by CG-001                                        |

Password reset is deliberately absent: no public reset-consumption contract is
in the frozen OpenAPI. The admin request action is not a user reset page.

## Onboarding

| Route                    | Shell      | Guard                | Owner / step    | Lazy   | API dependencies                                             |
| ------------------------ | ---------- | -------------------- | --------------- | ------ | ------------------------------------------------------------ |
| `/onboarding`            | Onboarding | session + onboarding | onboarding / 03 | parent | `GET /users/me/onboarding`; redirect only to returned `next` |
| `/onboarding/theme`      | Onboarding | session + onboarding | onboarding / 03 | child  | theme GET/PATCH                                              |
| `/onboarding/rules`      | Onboarding | session + onboarding | onboarding / 03 | child  | budget-rules GET/PUT                                         |
| `/onboarding/currencies` | Onboarding | session + onboarding | onboarding / 03 | child  | currencies catalogue/membership/main-currency                |
| `/onboarding/categories` | Onboarding | session + onboarding | onboarding / 03 | child  | category CRUD and budget-rule assignment                     |
| `/onboarding/income`     | Onboarding | session + onboarding | onboarding / 03 | child  | basic-income CRUD                                            |
| `/onboarding/tutorial`   | Onboarding | session + onboarding | onboarding / 03 | child  | onboarding GET/PATCH; completion is one-way                  |

## Personal-finance application

| Route                             | Navigation    | Guard                 | Owner / step        | Lazy  | Purpose and API dependencies                                         |
| --------------------------------- | ------------- | --------------------- | ------------------- | ----- | -------------------------------------------------------------------- |
| `/app/home`                       | primary       | session               | dashboard / 04      | yes   | Current-month report; provider/stale/partial distinctions            |
| `/app/activity`                   | primary       | session               | journal / 05        | yes   | Cursor/date-filtered journal                                         |
| `/app/activity/new`               | contextual    | session               | journal / 05        | child | Create supported manual entry with declared idempotency key          |
| `/app/activity/:id`               | contextual    | session               | journal / 05        | child | Detail from list/read-model data; no invented by-id endpoint         |
| `/app/activity/:id/correct`       | hidden action | session               | journal / 05        | child | Correction command                                                   |
| `/app/activity/:id/reverse`       | hidden action | session               | journal / 05        | child | Reversal command                                                     |
| `/app/plan`                       | primary       | session               | planning / 06       | yes   | Rules, baseline income, recurrence summary                           |
| `/app/plan/budget`                | contextual    | session               | planning / 06       | child | Budget-rule plan; CG-006 applies                                     |
| `/app/plan/categories`            | contextual    | session               | planning / 06       | child | Category CRUD/assignment                                             |
| `/app/plan/income`                | contextual    | session               | planning / 06       | child | Validity-ranged planning income                                      |
| `/app/plan/schedules`             | contextual    | session               | planning / 06       | child | Recurrence list and forecast; CG-007 applies                         |
| `/app/plan/schedules/new`         | contextual    | session               | planning / 06       | child | Create supported RRULE                                               |
| `/app/plan/schedules/:id/edit`    | contextual    | session               | planning / 06       | child | Update supported RRULE                                               |
| `/app/goals`                      | primary       | session + entitlement | goals / 07          | yes   | Active/completed/archived and quota                                  |
| `/app/goals/new`                  | contextual    | session + entitlement | goals / 07          | child | Zero-balance goal creation                                           |
| `/app/goals/:id`                  | contextual    | session + entitlement | goals / 07          | child | Ledger-derived values, contributions, recurrence; CG-012 applies     |
| `/app/goals/:id/edit`             | contextual    | session + entitlement | goals / 07          | child | Update open goal                                                     |
| `/app/reports`                    | more          | session               | reporting / 05      | yes   | Available report years                                               |
| `/app/reports/:year`              | contextual    | session               | reporting / 05      | child | Annual/month aggregates                                              |
| `/app/reports/:year/:month`       | contextual    | session               | reporting / 05      | child | URL-backed filters and cursor activity; CG-011 applies               |
| `/app/reserve`                    | more          | session + entitlement | reserve / 07        | yes   | Target, ledger allocation, movements, read-model projection          |
| `/app/loans`                      | more          | session + entitlement | loans / 08          | yes   | Active/completed/archived and quota                                  |
| `/app/loans/new`                  | contextual    | session + entitlement | loans / 08          | child | Loan configuration                                                   |
| `/app/loans/:id`                  | contextual    | session + entitlement | loans / 08          | child | Estimate, schedule, payments, recurrence; CG-008 applies             |
| `/app/loans/:id/edit`             | contextual    | session + entitlement | loans / 08          | child | Configuration update without history rewrite                         |
| `/app/investments`                | more          | session + entitlement | investments / 08    | yes   | Generic investment records                                           |
| `/app/investments/new`            | contextual    | session + entitlement | investments / 08    | child | Create generic investment                                            |
| `/app/investments/:id`            | contextual    | session + entitlement | investments / 08    | child | Read-model balance, movements, scenarios, recurrence; CG-013 applies |
| `/app/investments/:id/edit`       | contextual    | session + entitlement | investments / 08    | child | Update configuration                                                 |
| `/app/securities`                 | more          | session + entitlement | securities / 09     | yes   | Portfolio, positions, valuation and refresh; blocked by CG-002       |
| `/app/securities/activity`        | contextual    | session + entitlement | securities / 09     | child | Securities activity; blocked by CG-002                               |
| `/app/securities/trade`           | contextual    | session + entitlement | securities / 09     | child | Buy/sell command; blocked by CG-002                                  |
| `/app/securities/cash`            | contextual    | session + entitlement | securities / 09     | child | Cash movement; blocked by CG-002                                     |
| `/app/securities/import`          | contextual    | session + entitlement | securities / 09     | child | Preview/commit import; blocked by CG-002                             |
| `/app/securities/instruments/:id` | contextual    | session + entitlement | securities / 09     | child | Instrument identity/price history; blocked by CG-002                 |
| `/app/securities/watchlist`       | contextual    | session + entitlement | securities / 09     | child | Watch/unwatch canonical instrument; blocked by CG-002                |
| `/app/feedback`                   | more          | session               | feedback / 10       | yes   | Owned feedback list; blocked by CG-003                               |
| `/app/feedback/new`               | contextual    | session               | feedback / 10       | child | Create feedback; blocked by CG-003                                   |
| `/app/settings`                   | more          | session               | settings / 10       | yes   | Grouped settings hub                                                 |
| `/app/settings/profile`           | contextual    | session               | settings / 10       | child | Current user profile/language                                        |
| `/app/settings/security`          | contextual    | session               | settings / 10       | child | Password and passkeys; passkeys blocked by CG-001                    |
| `/app/settings/appearance`        | contextual    | session               | settings / 10       | child | Server palette + device-local display mode                           |
| `/app/settings/currencies`        | contextual    | session               | settings / 10       | child | Currency membership/main currency                                    |
| `/app/settings/categories`        | contextual    | session               | planning reuse / 10 | child | Reuse category feature                                               |
| `/app/settings/income`            | contextual    | session               | planning reuse / 10 | child | Reuse income feature                                                 |
| `/app/settings/notifications`     | contextual    | session               | notifications / 10  | child | Educational preference; blocked by CG-004                            |
| `/app/settings/privacy`           | contextual    | session               | privacy / 10        | child | Export request/status and deletion request                           |
| `/app/more`                       | primary       | session               | product shell / 04  | yes   | Navigation hub only; no additional API                               |

## Administration

| Route                           | Shell | Guard | Owner / step        | Lazy  | API dependencies                                                       |
| ------------------------------- | ----- | ----- | ------------------- | ----- | ---------------------------------------------------------------------- |
| `/admin`                        | Admin | admin | administration / 11 | yes   | Dashboard; blocked by CG-005                                           |
| `/admin/analytics`              | Admin | admin | administration / 11 | child | Analytics; blocked by CG-005                                           |
| `/admin/users`                  | Admin | admin | administration / 11 | child | User list; blocked by CG-005                                           |
| `/admin/users/:id`              | Admin | admin | administration / 11 | child | Masked detail and fixed actions; blocked by CG-005                     |
| `/admin/feedback`               | Admin | admin | administration / 11 | child | Staff feedback workflow; blocked by CG-005                             |
| `/admin/system`                 | Admin | admin | administration / 11 | child | Non-secret settings; CG-005 and CG-014                                 |
| `/admin/system/integrations`    | Admin | admin | administration / 11 | child | Write-only integration secret workflow; CG-005/CG-015                  |
| `/admin/system/email`           | Admin | admin | administration / 11 | child | Templates, synthetic preview/test, channel/settings; blocked by CG-016 |
| `/admin/operations`             | Admin | admin | administration / 11 | child | PII-safe queue observability                                           |
| `/admin/billing`                | Admin | admin | billing / 11        | child | Administrative summary; blocked by CG-017                              |
| `/admin/billing/plans`          | Admin | admin | billing / 11        | child | Plan records; blocked by CG-017/CG-018                                 |
| `/admin/billing/plans/:id`      | Admin | admin | billing / 11        | child | Plan edit; blocked by CG-017/CG-018                                    |
| `/admin/billing/promotions`     | Admin | admin | billing / 11        | child | Promotion records; blocked by CG-017/CG-019                            |
| `/admin/billing/promotions/:id` | Admin | admin | billing / 11        | child | Promotion edit; blocked by CG-017/CG-019                               |

## Navigation rules

- Mobile bottom navigation contains exactly Home, Activity, Plan, Goals, More.
- Desktop navigation exposes the same primary destinations. Secondary finance
  features remain under More unless future evidence approves a change.
- Admin navigation is never displayed in the product shell. A fixed `admin`
  role may enter the separate admin shell.
- Back navigation and deep links preserve safe URL filters, never tokens,
  emails, notes, or financial payloads.
- Provider-disabled, tier-gated, 403, and unavailable are distinct states; the
  router does not conceal them with invented redirects.

## Excluded routes

There are no routes for connected banks, checkout, subscription self-service,
SMS, push, provider webhooks, AI advice, tax workflows, custom roles,
impersonation, arbitrary CSV mappings, or user-facing notification delivery
history. Adding one requires a new approved product/API contract.
