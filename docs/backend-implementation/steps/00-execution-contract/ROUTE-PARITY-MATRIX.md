# Step 00 — Legacy Route to Backend v1 Parity Matrix

**Verified legacy inventory:** 154 literal switch routes + 2 regex switch routes + 3 dynamic stock routes = **159 route patterns**.

## Status definitions

- **preserve:** behavior remains materially the same behind a REST contract.
- **replace:** capability remains but endpoint shape or audited behavior changes.
- **remove:** unsafe, broken, duplicate, or deliberately unsupported backend behavior.
- **frontend-only:** page composition/navigation belongs to Angular or Astro; any data appears under other API resources.
- **defer:** not part of backend v1.

All protected targets require the authenticated principal and resource-ownership policy. Admin targets require `admin`. Methods in the “Legacy” column describe executable PHP behavior, including unsafe behavior that will not be copied.

## Public, identity, onboarding, and settings

| # | Legacy route | Legacy method | Legacy handler/purpose | Disposition | Backend v1 target | Step |
|---:|---|---|---|---|---|---:|
| 1 | `/` | GET | landing or dashboard page | frontend-only | Astro `/`; Angular `/`; dashboard data below | 10 |
| 2 | `/register` | GET/POST | form and registration | replace | `POST /api/v1/auth/registrations` | 04 |
| 3 | `/verify-email` | GET/POST behavior in handler | verify token or resend | replace | `POST /api/v1/auth/email-verifications`; `POST /api/v1/auth/email-verification-requests` | 04 |
| 4 | `/login` | GET/POST | page and password login | replace | `POST /api/v1/auth/sessions` | 04 |
| 5 | `/logout` | GET | logout | replace | `DELETE /api/v1/auth/session`; GET logout removed | 04 |
| 6 | `/webauthn/options/register` | POST | passkey registration options | preserve | `POST /api/v1/auth/passkeys/registration-options` | 04 |
| 7 | `/webauthn/register` | POST | finish passkey registration | preserve | `POST /api/v1/auth/passkeys` | 04 |
| 8 | `/webauthn/options/login` | POST | passkey login options | preserve | `POST /api/v1/auth/passkey-sessions/options` | 04 |
| 9 | `/webauthn/login` | POST | finish passkey login | replace | `POST /api/v1/auth/passkey-sessions` with session rotation | 04 |
| 10 | `/onboard/next` | GET | calculate next onboarding step | replace | `GET /api/v1/users/me/onboarding` | 05 |
| 11 | `/onboard/theme` | GET/POST | theme onboarding | replace | `PATCH /api/v1/users/me/preferences/theme` | 05 |
| 12 | `/onboard/rules` | GET/POST | cash-flow rule onboarding | replace | `GET/PUT /api/v1/budget-rules` | 08 |
| 13 | `/onboard/currencies` | GET | onboarding currency list | replace | `GET /api/v1/users/me/currencies` | 07 |
| 14 | `/onboard/currencies/add` | POST | add user currency | replace | `POST /api/v1/users/me/currencies` | 07 |
| 15 | `/onboard/currencies/delete` | POST | remove user currency | replace | `DELETE /api/v1/users/me/currencies/{code}` | 07 |
| 16 | `/onboard/income` | GET/POST | list/add basic income | replace | `GET/POST /api/v1/basic-incomes` | 08 |
| 17 | `/onboard/income/delete` | POST | delete basic income | replace | `DELETE /api/v1/basic-incomes/{id}` | 08 |
| 18 | `/onboard/categories` | GET | onboarding categories | replace | `GET /api/v1/categories` | 08 |
| 19 | `/onboard/categories/add` | POST | add category | replace | `POST /api/v1/categories` | 08 |
| 20 | `/onboard/categories/delete` | POST | delete category | replace | `DELETE /api/v1/categories/{id}` | 08 |
| 21 | `/onboard/done` | GET | done page/state | replace | `GET /api/v1/users/me/onboarding` | 05 |
| 22 | `/tutorial` | GET | tutorial page | frontend-only | Angular tutorial route | 05 |
| 23 | `/tutorial/done` | GET/POST | view/complete tutorial | replace | `PATCH /api/v1/users/me/onboarding` | 05 |
| 24 | `/settings` | GET | settings page | frontend-only | Angular settings route | 05 |
| 25 | `/settings/profile` | GET/POST | profile view/update | replace | `GET/PATCH /api/v1/users/me` | 05 |
| 26 | `/settings/profile/password` | POST | password change | replace | `PUT /api/v1/users/me/password` with reauthentication/revocation | 04 |
| 27 | `/settings/passkeys/delete` | POST | delete passkey | replace | `DELETE /api/v1/auth/passkeys/{id}` | 04 |
| 28 | `/settings/theme` | GET/POST | theme view/update | replace | `GET/PATCH /api/v1/users/me/preferences/theme` | 05 |
| 29 | `/settings/currencies` | GET | currency settings page | replace | `GET /api/v1/users/me/currencies` | 07 |
| 30 | `/settings/currencies/add` | POST | add currency | replace | `POST /api/v1/users/me/currencies` | 07 |
| 31 | `/settings/currencies/remove` | POST | remove currency | replace | `DELETE /api/v1/users/me/currencies/{code}` | 07 |
| 32 | `/settings/currencies/main` | POST | set main currency | replace | `PUT /api/v1/users/me/main-currency` | 07 |
| 33 | `/settings/basic-incomes` | GET | list basic incomes | replace | `GET /api/v1/basic-incomes` | 08 |
| 34 | `/settings/basic-incomes/add` | POST | add basic income | replace | `POST /api/v1/basic-incomes` | 08 |
| 35 | `/settings/basic-incomes/edit` | POST | update basic income | replace | `PATCH /api/v1/basic-incomes/{id}` | 08 |
| 36 | `/settings/basic-incomes/delete` | POST | delete basic income | replace | `DELETE /api/v1/basic-incomes/{id}` | 08 |
| 37 | `/settings/categories` | GET | list categories | replace | `GET /api/v1/categories` | 08 |
| 38 | `/settings/categories/add` | POST | add category | replace | `POST /api/v1/categories` | 08 |
| 39 | `/settings/categories/edit` | POST | update category | replace | `PATCH /api/v1/categories/{id}` | 08 |
| 40 | `/settings/categories/delete` | POST | delete category | replace | `DELETE /api/v1/categories/{id}` | 08 |
| 41 | `/settings/cashflow` | GET | list rules | replace | `GET /api/v1/budget-rules` | 08 |
| 42 | `/settings/cashflow/add` | POST | add rule | replace | `POST /api/v1/budget-rules` | 08 |
| 43 | `/settings/cashflow/edit` | POST | edit rule | replace | `PATCH /api/v1/budget-rules/{id}` | 08 |
| 44 | `/settings/cashflow/delete` | POST | delete rule | replace | `DELETE /api/v1/budget-rules/{id}` | 08 |
| 45 | `/settings/cashflow/assign` | POST | assign category | replace | `PUT /api/v1/categories/{id}/budget-rule` | 08 |
| 46 | `/settings/privacy` | GET | privacy page | frontend-only | Astro/Angular privacy content; manifest status below | 19 |
| 47 | `/settings/privacy/export` | POST | synchronous partial JSON export | replace | `POST /api/v1/privacy/exports`; `GET /api/v1/privacy/exports/{id}` | 19 |
| 48 | `/settings/privacy/delete` | POST | account deletion | replace | `POST /api/v1/privacy/deletion-requests` with reauthentication | 19 |
| 49 | `/privacy` | GET | public privacy page | frontend-only | Astro `/privacy` | 19 |

## Ledger, reports, goals, reserve, loans, and recurrence

| # | Legacy route | Legacy method | Legacy handler/purpose | Disposition | Backend v1 target | Step |
|---:|---|---|---|---|---|---:|
| 50 | `/current-month` | GET | current month page/read model | replace | `GET /api/v1/reports/months/current` | 10 |
| 51 | `/transactions/add` | POST | add income/spending | replace | `POST /api/v1/journal/entries` | 06 |
| 52 | `/transactions/edit` | POST | destructive update | replace | `POST /api/v1/journal/entries/{id}/corrections` | 06 |
| 53 | `/transactions/delete` | POST | delete transaction | replace | `POST /api/v1/journal/entries/{id}/reversals` | 06 |
| 54 | `/years` | GET | year index | replace | `GET /api/v1/reports/years` | 10 |
| 55 | `/years/{year}` | GET | year details | replace | `GET /api/v1/reports/years/{year}` | 10 |
| 56 | `/years/{year}/{month}` | GET | month details | replace | `GET /api/v1/reports/months/{year}/{month}` | 10 |
| 57 | `/months/tx/add` | POST | month-context add | replace | `POST /api/v1/journal/entries`; client supplies effective date | 06 |
| 58 | `/months/tx/edit` | POST | month-context update | replace | `POST /api/v1/journal/entries/{id}/corrections` | 06 |
| 59 | `/months/tx/delete` | POST | month-context delete | replace | `POST /api/v1/journal/entries/{id}/reversals` | 06 |
| 60 | `/months/tx/list` | GET | HTML lazy-load list | replace | `GET /api/v1/journal/entries` with cursor/date filters | 06 |
| 61 | `/scheduled` | GET | scheduled list/projections | replace | `GET /api/v1/recurring-rules` | 09 |
| 62 | `/scheduled/add` | POST | add scheduled payment | replace | `POST /api/v1/recurring-rules` with economic type | 09 |
| 63 | `/scheduled/edit` | POST | edit schedule | replace | `PATCH /api/v1/recurring-rules/{id}` | 09 |
| 64 | `/scheduled/delete` | POST | delete schedule | replace | `DELETE /api/v1/recurring-rules/{id}` | 09 |
| 65 | `/goals` | GET | goals and contributions | replace | `GET /api/v1/goals` | 11 |
| 66 | `/goals/add` | POST | add goal | replace | `POST /api/v1/goals` | 11 |
| 67 | `/goals/edit` | POST | update goal | replace | `PATCH /api/v1/goals/{id}` | 11 |
| 68 | `/goals/archive` | POST | catch-up and false-income archive | replace | `POST /api/v1/goals/{id}/archive` without income | 11 |
| 69 | `/goals/unarchive` | POST | reverse archive/payout | replace | `POST /api/v1/goals/{id}/unarchive` without income deletion | 11 |
| 70 | `/goals/delete` | POST | delete goal | replace | `DELETE /api/v1/goals/{id}` subject to history policy | 11 |
| 71 | `/goals/create-schedule` | POST | create linked schedule | replace | `POST /api/v1/goals/{id}/recurring-rule` | 11 |
| 72 | `/goals/link-schedule` | POST | link schedule | replace | `PUT /api/v1/goals/{id}/recurring-rule` | 11 |
| 73 | `/goals/unlink-schedule` | POST | unlink schedule | replace | `DELETE /api/v1/goals/{id}/recurring-rule` | 11 |
| 74 | `/goals/tx/add` | POST | contribution and balance mutation | replace | `POST /api/v1/goals/{id}/contributions` as transfer | 11 |
| 75 | `/goals/tx/update` | POST | update contribution | replace | `POST /api/v1/goals/{goalId}/contributions/{id}/corrections` | 11 |
| 76 | `/goals/tx/delete` | GET | unsafe delete contribution | replace | `POST /api/v1/goals/{goalId}/contributions/{id}/reversals` | 11 |
| 77 | `/emergency` | GET | emergency reserve read model | replace | `GET /api/v1/emergency-reserve` | 12 |
| 78 | `/emergency/target` | POST | set target | replace | `PUT /api/v1/emergency-reserve/target` | 12 |
| 79 | `/emergency/add` | POST | deposit recorded as spending | replace | `POST /api/v1/emergency-reserve/contributions` as transfer | 12 |
| 80 | `/emergency/withdraw` | POST | withdrawal recorded as income | replace | `POST /api/v1/emergency-reserve/withdrawals` as transfer | 12 |
| 81 | `/emergency/tx/delete` | POST | delete reserve transaction | replace | `POST /api/v1/emergency-reserve/movements/{id}/reversals` | 12 |
| 82 | `/loans` | GET | loans, mutation/backfill, projections | replace | `GET /api/v1/loans`; read is side-effect free | 13 |
| 83 | `/loans/add` | POST | add loan | replace | `POST /api/v1/loans` | 13 |
| 84 | `/loans/edit` | POST | update loan | replace | `PATCH /api/v1/loans/{id}` | 13 |
| 85 | `/loans/archive` | POST | archive loan | replace | `POST /api/v1/loans/{id}/archive` | 13 |
| 86 | `/loans/delete` | POST | delete loan | replace | `DELETE /api/v1/loans/{id}` subject to history policy | 13 |
| 87 | `/loans/payment/add` | POST | add repayment | replace | `POST /api/v1/loans/{id}/payments` | 13 |
| 88 | `/loans/payment/update` | POST | mutate repayment | replace | `POST /api/v1/loans/{loanId}/payments/{id}/corrections` | 13 |
| 89 | `/loans/payment/delete` | POST | delete repayment | replace | `POST /api/v1/loans/{loanId}/payments/{id}/reversals` | 13 |
| 90 | `/loals/unlink-schedule` | POST | misspelled route calls goal unlink | remove | no target; correct loan recurring endpoint is `DELETE /api/v1/loans/{id}/recurring-rule` | 13 |

## Generic investments and securities

| # | Legacy route | Legacy method | Legacy handler/purpose | Disposition | Backend v1 target | Step |
|---:|---|---|---|---|---|---:|
| 91 | `/investments` | GET | generic investment overview | replace | `GET /api/v1/investments` | 14 |
| 92 | `/investments/add` | POST | add investment | replace | `POST /api/v1/investments` | 14 |
| 93 | `/investments/update` | POST | update investment | replace | `PATCH /api/v1/investments/{id}` | 14 |
| 94 | `/investments/adjust` | POST | deposit/withdraw and balance mutation | replace | `POST /api/v1/investments/{id}/movements` as transfer | 14 |
| 95 | `/investments/scheduled/create` | POST | create contribution schedule | replace | `POST /api/v1/investments/{id}/recurring-rule` | 14 |
| 96 | `/investments/delete` | POST | delete investment | replace | `DELETE /api/v1/investments/{id}` subject to history policy | 14 |
| 97 | `/stocks` | GET | portfolio overview | replace | `GET /api/v1/securities/portfolio` | 15 |
| 98 | `/stocks/transactions` | GET | trade/cash history | replace | `GET /api/v1/securities/activity` | 15 |
| 99 | `/stocks/trade` | POST | post buy/sell | replace | `POST /api/v1/securities/trades` with atomic oversell guard | 15 |
| 100 | `/stocks/import` | POST | CSV import | replace | `POST /api/v1/securities/imports` preview; `POST /api/v1/securities/imports/{id}/commit` | 15 |
| 101 | `/stocks/cash` | POST | signed cash movement | replace | `POST /api/v1/securities/cash-movements` | 15 |
| 102 | `/stocks/refresh` | POST | synchronous refresh | replace | `POST /api/v1/securities/refresh-jobs` | 15 |
| 103 | `/stocks/clear` | POST | clear entire history | replace | `POST /api/v1/securities/portfolio-clear-requests` with step-up confirmation | 15 |
| 104 | `/stocks/trade/delete` | POST | delete trade without cash reversal | replace | `POST /api/v1/securities/trades/{id}/reversals` | 15 |
| 105 | `/api/stocks/live` | GET | live quote JSON | replace | `GET /api/v1/securities/quotes?instrumentId=…` | 15 |
| 106 | `/stocks/{symbol}` | GET | instrument detail page | replace | `GET /api/v1/securities/instruments/{instrumentId}` and related read models | 15 |
| 107 | `/stocks/{symbol}/watch` | GET/POST | redirect/toggle watchlist | replace | `PUT/DELETE /api/v1/securities/watchlist/{instrumentId}` | 15 |
| 108 | `/api/stocks/{symbol}/history` | GET | daily history JSON | replace | `GET /api/v1/securities/instruments/{instrumentId}/prices` | 15 |

## Feedback, administration, billing, and operations

| # | Legacy route | Legacy method | Legacy handler/purpose | Disposition | Backend v1 target | Step |
|---:|---|---|---|---|---|---:|
| 109 | `/feedback` | GET | user feedback list/page | replace | `GET /api/v1/feedback` | 16 |
| 110 | `/feedback/add` | POST | create feedback | replace | `POST /api/v1/feedback` | 16 |
| 111 | `/feedback/status` | POST | user status update | replace | `PATCH /api/v1/feedback/{id}/status` | 16 |
| 112 | `/feedback/delete` | POST | delete feedback | replace | `DELETE /api/v1/feedback/{id}` | 16 |
| 113 | `/more` | GET | navigation hub | frontend-only | Angular route | 05 |
| 114 | `/admin` | GET | admin dashboard | replace | `GET /api/v1/admin/dashboard` | 16 |
| 115 | `/admin/analytics` | GET | admin metrics | replace | `GET /api/v1/admin/analytics` with defined metrics | 16 |
| 116 | `/admin/system` | GET | system/integration/email/channel page | replace | `GET /api/v1/admin/system` | 16 |
| 117 | `/admin/system/settings` | POST | update system settings | replace | `PATCH /api/v1/admin/system/settings` | 16 |
| 118 | `/admin/system/api/save` | POST | save encrypted API value | replace | `PUT /api/v1/admin/integrations/{service}`; write-only secret | 16 |
| 119 | `/admin/system/api/delete` | POST | delete integration | replace | `DELETE /api/v1/admin/integrations/{service}` | 16 |
| 120 | `/admin/system/email/save` | POST | save email settings/templates | replace | `PATCH /api/v1/admin/email-settings`; template resources below | 18 |
| 121 | `/admin/system/email/test` | POST | synchronous test send | replace | `POST /api/v1/admin/email-test-jobs` | 18 |
| 122 | `/admin/system/notifications/save` | POST | update channel config | replace | `PATCH /api/v1/admin/notification-channels/email` | 18 |
| 123 | `/admin/system/notifications/add` | POST | add arbitrary channel | remove | no target; SMS/push and arbitrary channels excluded | 18 |
| 124 | `/admin/emails` | GET | email template listing/editor page | replace | `GET /api/v1/admin/email-templates` | 18 |
| 125 | `/admin/emails/preview` | GET | HTML preview | replace | `POST /api/v1/admin/email-templates/{code}/preview` with synthetic data | 18 |
| 126 | `/admin/users` | GET | users index | replace | `GET /api/v1/admin/users` | 16 |
| 127 | `/admin/feedbacks` | GET | feedback admin list | replace | `GET /api/v1/admin/feedback` | 16 |
| 128 | `/admin/users/manage` | GET | user detail page | replace | `GET /api/v1/admin/users/{id}` | 16 |
| 129 | `/admin/users/role` | POST | assign role | replace | `PUT /api/v1/admin/users/{id}/role` limited to fixed roles | 16 |
| 130 | `/admin/users/reset-password` | POST | displays temporary password | replace | `POST /api/v1/admin/users/{id}/password-reset-request` | 16 |
| 131 | `/admin/users/resend-verification` | POST | resend verification | replace | `POST /api/v1/admin/users/{id}/email-verification-request` | 16 |
| 132 | `/admin/users/reset-email` | POST | reset email | replace | `POST /api/v1/admin/users/{id}/email-change-request` with verification | 16 |
| 133 | `/admin/users/status` | POST | activate/deactivate | replace | `PUT /api/v1/admin/users/{id}/status` | 16 |
| 134 | `/admin/users/invoices/update` | POST | update invoice | replace | `PATCH /api/v1/admin/invoices/{id}` | 17 |
| 135 | `/admin/users/payments/create` | POST | create payment record | replace | `POST /api/v1/admin/payments` | 17 |
| 136 | `/admin/users/payments/update` | POST | update payment record | replace | `PATCH /api/v1/admin/payments/{id}` | 17 |
| 137 | `/admin/users/feedback/update` | POST | update feedback | replace | `PATCH /api/v1/admin/feedback/{id}` | 16 |
| 138 | `/admin/users/feedback/respond` | POST | add feedback response | replace | `POST /api/v1/admin/feedback/{id}/responses` | 16 |
| 139 | `/admin/billing` | GET | billing dashboard | replace | `GET /api/v1/admin/billing/summary` | 17 |
| 140 | `/admin/billing/plans/create` | GET | plan form | frontend-only | Angular form; data from plan collection | 17 |
| 141 | `/admin/billing/plans/edit` | GET | plan edit form | frontend-only | Angular form; `GET /api/v1/admin/billing/plans/{id}` | 17 |
| 142 | `/admin/billing/plans` | POST | create plan | replace | `GET/POST /api/v1/admin/billing/plans` | 17 |
| 143 | `/admin/billing/plans/update` | POST | update plan | replace | `PATCH /api/v1/admin/billing/plans/{id}` | 17 |
| 144 | `/admin/billing/plans/delete` | POST | delete plan | replace | `DELETE /api/v1/admin/billing/plans/{id}` | 17 |
| 145 | `/admin/billing/promotions/create` | GET | promotion form | frontend-only | Angular form; data from promotion collection | 17 |
| 146 | `/admin/billing/promotions/edit` | GET | promotion edit form | frontend-only | Angular form; `GET /api/v1/admin/billing/promotions/{id}` | 17 |
| 147 | `/admin/billing/promotions` | POST | create promotion | replace | `GET/POST /api/v1/admin/billing/promotions` | 17 |
| 148 | `/admin/billing/promotions/update` | POST | update promotion | replace | `PATCH /api/v1/admin/billing/promotions/{id}` | 17 |
| 149 | `/admin/billing/promotions/delete` | POST | delete promotion | replace | `DELETE /api/v1/admin/billing/promotions/{id}` | 17 |
| 150 | `/admin/billing/promotions/generate-trial` | POST | create trial promotion | preserve | `POST /api/v1/admin/billing/promotions/trial` as administrative record | 17 |
| 151 | `/admin/billing/settings` | POST | stores plaintext Stripe secrets | remove | no target; no payment checkout/provider secret in v1 | 17 |
| 152 | `/admin/billing/user-plan` | POST | assign subscription/role | replace | `PUT /api/v1/admin/users/{id}/subscription` | 17 |
| 153 | `/admin/roles` | GET/POST | arbitrary role list/create | remove | no target; fixed roles/capabilities | 05 |
| 154 | `/admin/roles/create` | GET | arbitrary role form | remove | no target | 05 |
| 155 | `/admin/roles/edit` | GET | arbitrary role form | remove | no target | 05 |
| 156 | `/admin/roles/update` | POST | arbitrary role update | remove | no target | 05 |
| 157 | `/admin/roles/delete` | POST | arbitrary role delete | remove | no target | 05 |
| 158 | `/maintenance/migrations` | GET | public-ish migration runner | remove | migrations run through deployment/operations only | 02 |
| 159 | `/admin/migrations` | GET | admin migration runner | remove | migrations run through deployment/operations only | 02 |

## Coverage assertion

- Literal PHP switch routes represented: **154/154**.
- Regex switch routes represented: **2/2**.
- Dynamic pre-switch stock routes represented: **3/3**.
- Total executable route patterns represented: **159/159**.
- New endpoints appearing in this matrix are contract targets, not implemented endpoints. Their final DTOs and exact OpenAPI definitions belong to the owning step.

