# API-to-UI Coverage

Baseline: **113 paths / 149 operations**

Coverage: **149 of 149 operations have one disposition**

Status: contract blockers and closures are cross-referenced to
`CONTRACT-GAPS.md`

`typed` means the successful result needed by the workflow has a usable
generated response shape. `intentional void` means the workflow can complete
from its status without a body. A blocked feature may still contain a
technically valid void delete operation; that row says both facts explicitly.
Health checks belong to deployment/CI operations and do not justify a public
Angular page.

|   # | Operation                                                          | Generated operation ID                        | UI/operational disposition                       | Response contract                |
| --: | ------------------------------------------------------------------ | --------------------------------------------- | ------------------------------------------------ | -------------------------------- |
|   1 | `POST /api/v1/auth/registrations`                                  | `IdentityController_register`                 | `/auth/register`                                 | intentional void                 |
|   2 | `POST /api/v1/auth/email-verifications`                            | `IdentityController_verify`                   | `/auth/verify-email`                             | intentional void                 |
|   3 | `POST /api/v1/auth/email-verification-requests`                    | `IdentityController_requestVerification`      | `/auth/verification-sent`                        | intentional void                 |
|   4 | `POST /api/v1/auth/sessions`                                       | `IdentityController_login`                    | `/auth/login`                                    | intentional void                 |
|   5 | `DELETE /api/v1/auth/session`                                      | `IdentityController_logout`                   | product/auth shell                               | intentional void                 |
|   6 | `PUT /api/v1/users/me/password`                                    | `IdentityController_changePassword`           | `/app/settings/security`                         | intentional void                 |
|   7 | `POST /api/v1/auth/passkeys/registration-options`                  | `IdentityController_registrationOptions`      | `/app/settings/security`                         | typed; CG-001 closed             |
|   8 | `POST /api/v1/auth/passkeys`                                       | `IdentityController_registerPasskey`          | `/app/settings/security`                         | typed; CG-001 closed             |
|   9 | `GET /api/v1/auth/passkeys`                                        | `IdentityController_listPasskeys`             | `/app/settings/security`                         | typed; CG-001 closed             |
|  10 | `POST /api/v1/auth/passkey-sessions/options`                       | `IdentityController_passkeyOptions`           | `/auth/passkey`                                  | typed; CG-001 closed             |
|  11 | `POST /api/v1/auth/passkey-sessions`                               | `IdentityController_passkeyLogin`             | `/auth/passkey`                                  | intentional void; CG-001 closed  |
|  12 | `DELETE /api/v1/auth/passkeys/{id}`                                | `IdentityController_deletePasskey`            | `/app/settings/security`                         | intentional void; CG-001 closed  |
|  13 | `GET /api/v1/users/me`                                             | `UsersController_currentUser`                 | `/app/settings/profile` + session routing        | typed                            |
|  14 | `PATCH /api/v1/users/me`                                           | `UsersController_updateCurrentUser`           | `/app/settings/profile` + session routing        | typed                            |
|  15 | `GET /api/v1/users/me/preferences/theme`                           | `UsersController_theme`                       | `/onboarding/theme` + `/app/settings/appearance` | typed                            |
|  16 | `PATCH /api/v1/users/me/preferences/theme`                         | `UsersController_updateTheme`                 | `/onboarding/theme` + `/app/settings/appearance` | typed                            |
|  17 | `GET /api/v1/users/me/onboarding`                                  | `UsersController_onboarding`                  | `/onboarding/**`                                 | typed                            |
|  18 | `PATCH /api/v1/users/me/onboarding`                                | `UsersController_completeTutorial`            | `/onboarding/**`                                 | typed                            |
|  19 | `GET /api/v1/currencies`                                           | `CurrencyController_catalogue`                | onboarding/settings currencies                   | typed                            |
|  20 | `GET /api/v1/users/me/currencies`                                  | `CurrencyController_userCurrencies`           | onboarding/settings currencies                   | typed                            |
|  21 | `POST /api/v1/users/me/currencies`                                 | `CurrencyController_add`                      | onboarding/settings currencies                   | typed                            |
|  22 | `DELETE /api/v1/users/me/currencies/{code}`                        | `CurrencyController_remove`                   | onboarding/settings currencies                   | intentional void                 |
|  23 | `PUT /api/v1/users/me/main-currency`                               | `CurrencyController_setMain`                  | onboarding/settings currencies                   | typed                            |
|  24 | `GET /api/v1/journal/entries`                                      | `LedgerController_list`                       | `/app/activity/**`                               | typed; CG-002 closed             |
|  25 | `POST /api/v1/journal/entries`                                     | `LedgerController_create`                     | `/app/activity/**`                               | typed; CG-002 closed             |
|  26 | `POST /api/v1/journal/entries/{id}/reversals`                      | `LedgerController_reverse`                    | `/app/activity/**`                               | typed; CG-002 closed             |
|  27 | `POST /api/v1/journal/entries/{id}/corrections`                    | `LedgerController_correct`                    | `/app/activity/**`                               | typed; CG-002 closed             |
|  28 | `GET /api/v1/budget-rules`                                         | `BudgetingController_rules`                   | onboarding rules + `/app/plan/budget`            | typed                            |
|  29 | `POST /api/v1/budget-rules`                                        | `BudgetingController_createRule`              | onboarding rules + `/app/plan/budget`            | typed                            |
|  30 | `PUT /api/v1/budget-rules`                                         | `BudgetingController_initialize`              | onboarding rules + `/app/plan/budget`            | typed                            |
|  31 | `PATCH /api/v1/budget-rules/{id}`                                  | `BudgetingController_updateRule`              | onboarding rules + `/app/plan/budget`            | typed                            |
|  32 | `DELETE /api/v1/budget-rules/{id}`                                 | `BudgetingController_deleteRule`              | onboarding rules + `/app/plan/budget`            | intentional void                 |
|  33 | `GET /api/v1/categories`                                           | `BudgetingController_categories`              | onboarding/plan/settings categories              | typed                            |
|  34 | `POST /api/v1/categories`                                          | `BudgetingController_createCategory`          | onboarding/plan/settings categories              | typed                            |
|  35 | `PATCH /api/v1/categories/{id}`                                    | `BudgetingController_updateCategory`          | onboarding/plan/settings categories              | typed                            |
|  36 | `DELETE /api/v1/categories/{id}`                                   | `BudgetingController_deleteCategory`          | onboarding/plan/settings categories              | intentional void                 |
|  37 | `PUT /api/v1/categories/{id}/budget-rule`                          | `BudgetingController_assignRule`              | onboarding/plan/settings categories              | typed                            |
|  38 | `GET /api/v1/basic-incomes`                                        | `BudgetingController_basicIncomes`            | onboarding/plan/settings income                  | typed                            |
|  39 | `POST /api/v1/basic-incomes`                                       | `BudgetingController_createBasicIncome`       | onboarding/plan/settings income                  | typed                            |
|  40 | `PATCH /api/v1/basic-incomes/{id}`                                 | `BudgetingController_updateBasicIncome`       | onboarding/plan/settings income                  | typed                            |
|  41 | `DELETE /api/v1/basic-incomes/{id}`                                | `BudgetingController_deleteBasicIncome`       | onboarding/plan/settings income                  | intentional void                 |
|  42 | `GET /api/v1/recurring-rules`                                      | `RecurrenceController_rules`                  | `/app/plan/schedules/**`                         | typed                            |
|  43 | `POST /api/v1/recurring-rules`                                     | `RecurrenceController_create`                 | `/app/plan/schedules/**`                         | typed                            |
|  44 | `PATCH /api/v1/recurring-rules/{id}`                               | `RecurrenceController_update`                 | `/app/plan/schedules/**`                         | typed                            |
|  45 | `DELETE /api/v1/recurring-rules/{id}`                              | `RecurrenceController_delete`                 | `/app/plan/schedules/**`                         | intentional void                 |
|  46 | `GET /api/v1/loans`                                                | `LoansController_list`                        | `/app/loans/**`                                  | blocked CG-003                   |
|  47 | `POST /api/v1/loans`                                               | `LoansController_create`                      | `/app/loans/**`                                  | blocked CG-003                   |
|  48 | `PATCH /api/v1/loans/{id}`                                         | `LoansController_update`                      | `/app/loans/**`                                  | blocked CG-003                   |
|  49 | `DELETE /api/v1/loans/{id}`                                        | `LoansController_delete`                      | `/app/loans/**`                                  | blocked CG-003                   |
|  50 | `POST /api/v1/loans/{id}/archive`                                  | `LoansController_archive`                     | `/app/loans/**`                                  | blocked CG-003                   |
|  51 | `POST /api/v1/loans/{id}/payments`                                 | `LoansController_payment`                     | `/app/loans/**`                                  | blocked CG-003                   |
|  52 | `POST /api/v1/loans/{loanId}/payments/{id}/corrections`            | `LoansController_correct`                     | `/app/loans/**`                                  | blocked CG-003                   |
|  53 | `POST /api/v1/loans/{loanId}/payments/{id}/reversals`              | `LoansController_reverse`                     | `/app/loans/**`                                  | blocked CG-003                   |
|  54 | `POST /api/v1/loans/{id}/recurring-rule`                           | `LoansController_createRule`                  | `/app/loans/**`                                  | blocked CG-003                   |
|  55 | `PUT /api/v1/loans/{id}/recurring-rule`                            | `LoansController_updateRule`                  | `/app/loans/**`                                  | blocked CG-003                   |
|  56 | `DELETE /api/v1/loans/{id}/recurring-rule`                         | `LoansController_deleteRule`                  | `/app/loans/**`                                  | blocked CG-003                   |
|  57 | `GET /api/v1/reports/months/current`                               | `ReportingController_current`                 | `/app/home`                                      | typed; CG-004 closed             |
|  58 | `GET /api/v1/reports/months/{year}/{month}`                        | `ReportingController_month`                   | `/app/reports/**`                                | typed; CG-004 closed             |
|  59 | `GET /api/v1/reports/years`                                        | `ReportingController_years`                   | `/app/reports/**`                                | typed                            |
|  60 | `GET /api/v1/reports/years/{year}`                                 | `ReportingController_year`                    | `/app/reports/**`                                | typed; CG-004 closed             |
|  61 | `GET /api/v1/goals`                                                | `GoalsController_list`                        | `/app/goals/**`                                  | typed                            |
|  62 | `POST /api/v1/goals`                                               | `GoalsController_create`                      | `/app/goals/**`                                  | typed                            |
|  63 | `PATCH /api/v1/goals/{id}`                                         | `GoalsController_update`                      | `/app/goals/**`                                  | typed                            |
|  64 | `DELETE /api/v1/goals/{id}`                                        | `GoalsController_delete`                      | `/app/goals/**`                                  | intentional void                 |
|  65 | `POST /api/v1/goals/{id}/archive`                                  | `GoalsController_archive`                     | `/app/goals/**`                                  | typed                            |
|  66 | `POST /api/v1/goals/{id}/unarchive`                                | `GoalsController_unarchive`                   | `/app/goals/**`                                  | typed                            |
|  67 | `POST /api/v1/goals/{id}/contributions`                            | `GoalsController_contribute`                  | `/app/goals/**`                                  | typed                            |
|  68 | `POST /api/v1/goals/{goalId}/contributions/{id}/corrections`       | `GoalsController_correct`                     | `/app/goals/**`                                  | typed                            |
|  69 | `POST /api/v1/goals/{goalId}/contributions/{id}/reversals`         | `GoalsController_reverse`                     | `/app/goals/**`                                  | typed                            |
|  70 | `POST /api/v1/goals/{id}/recurring-rule`                           | `GoalsController_createRule`                  | `/app/goals/**`                                  | typed                            |
|  71 | `PUT /api/v1/goals/{id}/recurring-rule`                            | `GoalsController_updateRule`                  | `/app/goals/**`                                  | typed                            |
|  72 | `DELETE /api/v1/goals/{id}/recurring-rule`                         | `GoalsController_deleteRule`                  | `/app/goals/**`                                  | intentional void                 |
|  73 | `GET /api/v1/emergency-reserve`                                    | `EmergencyReserveController_read`             | `/app/reserve`                                   | typed                            |
|  74 | `PUT /api/v1/emergency-reserve/target`                             | `EmergencyReserveController_updateTarget`     | `/app/reserve`                                   | typed                            |
|  75 | `POST /api/v1/emergency-reserve/contributions`                     | `EmergencyReserveController_contribution`     | `/app/reserve`                                   | typed                            |
|  76 | `POST /api/v1/emergency-reserve/withdrawals`                       | `EmergencyReserveController_withdrawal`       | `/app/reserve`                                   | typed                            |
|  77 | `POST /api/v1/emergency-reserve/movements/{id}/reversals`          | `EmergencyReserveController_reverse`          | `/app/reserve`                                   | typed                            |
|  78 | `GET /api/v1/investments`                                          | `InvestmentsController_list`                  | `/app/investments/**`                            | blocked CG-005                   |
|  79 | `POST /api/v1/investments`                                         | `InvestmentsController_create`                | `/app/investments/**`                            | blocked CG-005                   |
|  80 | `PATCH /api/v1/investments/{id}`                                   | `InvestmentsController_update`                | `/app/investments/**`                            | blocked CG-005                   |
|  81 | `DELETE /api/v1/investments/{id}`                                  | `InvestmentsController_delete`                | `/app/investments/**`                            | blocked CG-005                   |
|  82 | `POST /api/v1/investments/{id}/movements`                          | `InvestmentsController_movement`              | `/app/investments/**`                            | blocked CG-005                   |
|  83 | `POST /api/v1/investments/{investmentId}/movements/{id}/reversals` | `InvestmentsController_reverseMovement`       | `/app/investments/**`                            | blocked CG-005                   |
|  84 | `POST /api/v1/investments/{id}/recurring-rule`                     | `InvestmentsController_createRule`            | `/app/investments/**`                            | blocked CG-005                   |
|  85 | `GET /api/v1/securities/portfolio`                                 | `SecuritiesController_portfolio`              | `/app/securities/**`                             | blocked CG-006                   |
|  86 | `GET /api/v1/securities/activity`                                  | `SecuritiesController_activity`               | `/app/securities/**`                             | blocked CG-006                   |
|  87 | `POST /api/v1/securities/trades`                                   | `SecuritiesController_trade`                  | `/app/securities/**`                             | blocked CG-006                   |
|  88 | `POST /api/v1/securities/trades/{id}/reversals`                    | `SecuritiesController_reverse`                | `/app/securities/**`                             | blocked CG-006                   |
|  89 | `POST /api/v1/securities/cash-movements`                           | `SecuritiesController_cash`                   | `/app/securities/**`                             | blocked CG-006                   |
|  90 | `POST /api/v1/securities/imports`                                  | `SecuritiesController_preview`                | `/app/securities/**`                             | blocked CG-006                   |
|  91 | `POST /api/v1/securities/imports/{id}/commit`                      | `SecuritiesController_commit`                 | `/app/securities/**`                             | blocked CG-006                   |
|  92 | `POST /api/v1/securities/refresh-jobs`                             | `SecuritiesController_refresh`                | `/app/securities/**`                             | blocked CG-006                   |
|  93 | `GET /api/v1/securities/refresh-jobs/{id}`                         | `SecuritiesController_refreshStatus`          | `/app/securities/**`                             | blocked CG-006                   |
|  94 | `POST /api/v1/securities/portfolio-clear-requests`                 | `SecuritiesController_clear`                  | `/app/securities/**`                             | blocked CG-006                   |
|  95 | `GET /api/v1/securities/quotes`                                    | `SecuritiesController_quote`                  | `/app/securities/**`                             | blocked CG-006                   |
|  96 | `GET /api/v1/securities/instruments/{id}`                          | `SecuritiesController_instrument`             | `/app/securities/**`                             | blocked CG-006                   |
|  97 | `GET /api/v1/securities/instruments/{id}/prices`                   | `SecuritiesController_prices`                 | `/app/securities/**`                             | blocked CG-006                   |
|  98 | `PUT /api/v1/securities/watchlist/{id}`                            | `SecuritiesController_watch`                  | `/app/securities/**`                             | blocked CG-006                   |
|  99 | `DELETE /api/v1/securities/watchlist/{id}`                         | `SecuritiesController_unwatch`                | `/app/securities/**`                             | intentional void; feature CG-006 |
| 100 | `GET /api/v1/feedback`                                             | `FeedbackController_list`                     | `/app/feedback/**`                               | blocked CG-007                   |
| 101 | `POST /api/v1/feedback`                                            | `FeedbackController_create`                   | `/app/feedback/**`                               | blocked CG-007                   |
| 102 | `PATCH /api/v1/feedback/{id}/status`                               | `FeedbackController_status`                   | `/app/feedback/**`                               | blocked CG-007                   |
| 103 | `DELETE /api/v1/feedback/{id}`                                     | `FeedbackController_delete`                   | `/app/feedback/**`                               | intentional void; feature CG-007 |
| 104 | `GET /api/v1/admin/dashboard`                                      | `AdministrationController_dashboard`          | `/admin`                                         | blocked CG-008                   |
| 105 | `GET /api/v1/admin/analytics`                                      | `AdministrationController_analytics`          | `/admin/analytics`                               | blocked CG-008                   |
| 106 | `GET /api/v1/admin/users`                                          | `AdministrationController_users`              | `/admin/users/**`                                | blocked CG-008                   |
| 107 | `GET /api/v1/admin/users/{id}`                                     | `AdministrationController_user`               | `/admin/users/**`                                | blocked CG-008                   |
| 108 | `PUT /api/v1/admin/users/{id}/role`                                | `AdministrationController_role`               | `/admin/users/**`                                | blocked CG-008                   |
| 109 | `PUT /api/v1/admin/users/{id}/status`                              | `AdministrationController_status`             | `/admin/users/**`                                | blocked CG-008                   |
| 110 | `POST /api/v1/admin/users/{id}/password-reset-request`             | `AdministrationController_passwordReset`      | `/admin/users/**`                                | blocked CG-008                   |
| 111 | `POST /api/v1/admin/users/{id}/email-verification-request`         | `AdministrationController_verification`       | `/admin/users/**`                                | blocked CG-008                   |
| 112 | `POST /api/v1/admin/users/{id}/email-change-request`               | `AdministrationController_emailChange`        | `/admin/users/**`                                | blocked CG-008                   |
| 113 | `GET /api/v1/admin/feedback`                                       | `AdministrationController_feedback`           | `/admin/feedback`                                | blocked CG-008                   |
| 114 | `PATCH /api/v1/admin/feedback/{id}`                                | `AdministrationController_updateFeedback`     | `/admin/feedback`                                | blocked CG-008                   |
| 115 | `POST /api/v1/admin/feedback/{id}/responses`                       | `AdministrationController_respond`            | `/admin/feedback`                                | blocked CG-008                   |
| 116 | `GET /api/v1/admin/system`                                         | `AdministrationController_system`             | `/admin/system/**`                               | blocked CG-008                   |
| 117 | `PATCH /api/v1/admin/system/settings`                              | `AdministrationController_settings`           | `/admin/system/**`                               | blocked CG-008/CG-011            |
| 118 | `PUT /api/v1/admin/integrations/{service}`                         | `AdministrationController_integration`        | `/admin/system/**`                               | blocked CG-008/CG-012            |
| 119 | `DELETE /api/v1/admin/integrations/{service}`                      | `AdministrationController_deleteIntegration`  | `/admin/system/**`                               | intentional void; feature CG-008 |
| 120 | `GET /api/v1/admin/billing/summary`                                | `BillingController_summary`                   | `/admin/billing/**`                              | blocked CG-013                   |
| 121 | `GET /api/v1/admin/billing/plans`                                  | `BillingController_plans`                     | `/admin/billing/**`                              | blocked CG-013                   |
| 122 | `POST /api/v1/admin/billing/plans`                                 | `BillingController_createPlan`                | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 123 | `GET /api/v1/admin/billing/plans/{id}`                             | `BillingController_plan`                      | `/admin/billing/**`                              | blocked CG-013                   |
| 124 | `PATCH /api/v1/admin/billing/plans/{id}`                           | `BillingController_updatePlan`                | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 125 | `DELETE /api/v1/admin/billing/plans/{id}`                          | `BillingController_deletePlan`                | `/admin/billing/**`                              | intentional void; feature CG-013 |
| 126 | `GET /api/v1/admin/billing/promotions`                             | `BillingController_promotions`                | `/admin/billing/**`                              | blocked CG-013                   |
| 127 | `POST /api/v1/admin/billing/promotions`                            | `BillingController_createPromotion`           | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 128 | `GET /api/v1/admin/billing/promotions/{id}`                        | `BillingController_promotion`                 | `/admin/billing/**`                              | blocked CG-013                   |
| 129 | `PATCH /api/v1/admin/billing/promotions/{id}`                      | `BillingController_updatePromotion`           | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 130 | `DELETE /api/v1/admin/billing/promotions/{id}`                     | `BillingController_deletePromotion`           | `/admin/billing/**`                              | intentional void; feature CG-013 |
| 131 | `POST /api/v1/admin/billing/promotions/trial`                      | `BillingController_trial`                     | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 132 | `PUT /api/v1/admin/users/{id}/subscription`                        | `BillingController_assign`                    | `/admin/billing/**`                              | blocked CG-013                   |
| 133 | `PATCH /api/v1/admin/invoices/{id}`                                | `BillingController_invoice`                   | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 134 | `POST /api/v1/admin/payments`                                      | `BillingController_createPayment`             | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 135 | `PATCH /api/v1/admin/payments/{id}`                                | `BillingController_updatePayment`             | `/admin/billing/**`                              | blocked CG-013/CG-014            |
| 136 | `GET /api/v1/users/me/notification-preferences`                    | `NotificationsController_preference`          | `/app/settings/notifications`                    | blocked CG-009                   |
| 137 | `PATCH /api/v1/users/me/notification-preferences`                  | `NotificationsController_update`              | `/app/settings/notifications`                    | blocked CG-009                   |
| 138 | `GET /api/v1/admin/email-templates`                                | `NotificationsAdminController_templates`      | `/admin/system/email`                            | blocked CG-010                   |
| 139 | `POST /api/v1/admin/email-templates/{code}/preview`                | `NotificationsAdminController_preview`        | `/admin/system/email`                            | blocked CG-010                   |
| 140 | `POST /api/v1/admin/email-test-jobs`                               | `NotificationsAdminController_test`           | `/admin/system/email`                            | blocked CG-010                   |
| 141 | `GET /api/v1/admin/notification-channels/email`                    | `NotificationsAdminController_channel`        | `/admin/system/email`                            | blocked CG-010                   |
| 142 | `PATCH /api/v1/admin/notification-channels/email`                  | `NotificationsAdminController_updateChannel`  | `/admin/system/email`                            | blocked CG-010                   |
| 143 | `PATCH /api/v1/admin/email-settings`                               | `NotificationsAdminController_updateSettings` | `/admin/system/email`                            | blocked CG-010                   |
| 144 | `POST /api/v1/privacy/exports`                                     | `PrivacyController_createExport`              | `/app/settings/privacy`                          | typed                            |
| 145 | `GET /api/v1/privacy/exports/{id}`                                 | `PrivacyController_exportStatus`              | `/app/settings/privacy`                          | typed                            |
| 146 | `POST /api/v1/privacy/deletion-requests`                           | `PrivacyController_createDeletion`            | `/app/settings/privacy`                          | typed                            |
| 147 | `GET /api/v1/admin/operations/queues`                              | `AdminOperationsController_queues`            | `/admin/operations`                              | typed                            |
| 148 | `GET /api/v1/health/live`                                          | `HealthController_live`                       | operations/CI (no public route)                  | typed                            |
| 149 | `GET /api/v1/health/ready`                                         | `HealthController_ready`                      | operations/CI (no public route)                  | typed                            |

## Coverage invariants

- No row maps to an excluded feature.
- Admin billing remains record administration, not checkout or
  subscription self-service.
- Securities provider-disabled states use the same routes without claiming an
  enabled provider.
- The frontend does not expose a notification delivery-history page; queue
  status is an access-controlled admin operations concern.
- No route depends on a handwritten replacement for a blocked response.

## Step 02 consumption record

The API core and route-policy layer consumes only these frozen, generated
operations:

- `UsersController_currentUser` (`GET /api/v1/users/me`) bootstraps and refreshes
  authenticated user, verification, role, locale, and entitlement state.
- `UsersController_onboarding` (`GET /api/v1/users/me/onboarding`) supplies the
  server-owned onboarding route decision independently of the session store.

Health operations remain deployment/CI-only. No feature mutation or blocked
response contract was consumed in Step 02.
