# OpenAPI and Generated-Client Contract Audit

Audit date: 2026-07-31  
Baseline: `apps/api/openapi/openapi.json`  
Generated client: `libs/generated/api-client/src`  
Status: **correction workflow approved; CG-001 closed; remaining gaps stay
frontend-blocking until closed**

## Audit result

- OpenAPI paths: **113**
- OpenAPI operations: **149**
- Generated operation functions: **149**
- Every operation has one UI/operational disposition in
  `API-UI-COVERAGE.md`.
- Successful operations that intentionally return no body (principally 204
  commands and accepted anti-enumeration identity commands) may correctly
  generate `void`.
- Every other successful response was inspected through its generated function
  return type and recursively through its referenced generated models.

The client is complete by operation count but is not fully usable by response
shape. The gaps below cannot be resolved with handwritten frontend DTOs, casts,
`any`, source-code duplication, or raw-response parsing.

## Required correction workflow

For each approved correction:

1. correct the authoritative NestJS DTO/controller OpenAPI decorators without
   changing runtime business behavior unless separately approved;
2. regenerate `apps/api/openapi/openapi.json`;
3. regenerate `libs/generated/api-client/src`;
4. run backend HTTP/OpenAPI tests, the 113/149 coverage check (or approve and
   record a new baseline), generated-client drift, Postman/Newman freeze checks,
   and frontend contract checks;
5. record closure here before starting the dependent feature.

Step 00 does not perform those corrections.

## Blocking gaps

### CG-001 — Passkey request and response contracts — CLOSED

Affected operations:

- `IdentityController_registrationOptions`;
- `IdentityController_registerPasskey`;
- `IdentityController_passkeyOptions`;
- `IdentityController_passkeyLogin`;
- `IdentityController_deletePasskey`;
- missing passkey-list operation required to obtain a server-owned deletion ID.

Original observation:

- both option operations return runtime WebAuthn option objects but generate
  `void`;
- successful registration returns an identifier at runtime but generates
  `void`;
- `PasskeyLabelDto.credential` and
  `PasskeyAuthenticationDto.credential` generate `{}`;
- no list response exposes registered passkey identifiers/labels.

Implemented 2026-07-31:

- registration and authentication option operations now return explicit,
  recursively typed WebAuthn DTOs;
- registration and authentication credential requests use explicit nested
  credential/response/extension DTOs instead of `{}`;
- registration returns the stable server-owned passkey UUID;
- authenticated `GET /api/v1/auth/passkeys` returns only owned passkey UUIDs,
  labels, authenticator metadata, and timestamps; it never exposes credential
  IDs, public keys, or counters;
- authenticated `DELETE /api/v1/auth/passkeys/{id}` validates a UUID, filters by
  current-user ownership, is idempotent for an absent/non-owned identifier, and
  records a PII-safe immutable security-audit event on an actual deletion;
- registration records the corresponding PII-safe immutable security-audit
  event, and PostgreSQL constrains WebAuthn device/transport values.

Evidence: `webauthn.dto.ts`, `identity.controller.ts`,
`identity.repository.ts`, `webauthn-openapi.spec.ts`,
`identity-http.integration-spec.ts`, `identity-access.integration-spec.ts`, and
migration `20260731120000_passkey_security_audit.ts`. The approved client
generation workflow produced typed functions/models with no handwritten output
changes. Contract, migration, PostgreSQL isolation, Postman, generated-client,
and complete repository freeze checks passed before closure.

Formerly blocked: Step 03 passkey login and Step 10 passkey
enrollment/deletion. This dependency is now closed.

### CG-002 — Journal conversion provenance

Affected operations:

- `LedgerController_list`;
- `LedgerController_create`;
- `LedgerController_reverse`;
- `LedgerController_correct`.

Observed: `JournalEntryResponseDto.conversion` generates `{}`. Exact converted
amount, rate/provenance, stale/unavailable status, or other authoritative fields
cannot be consumed.

Owner/action: backend ledger/reporting OpenAPI owner must describe the conversion
object using an explicit DTO.

Blocks: Step 05 entry detail and any view that requires journal conversion
provenance. Basic journal fields may be scaffolded only after Step 05 confirms
the affected feature is not silently relying on this object.

### CG-003 — Loan calculation/read-model substructures

Affected operations: all 11 loan operations.

Observed:

- `LoanPaymentResponseDto.conversion` generates `{}`;
- `LoanResponseDto.estimate` generates `{}`;
- `LoanResponseDto.projectedSchedule` generates `Array<{}>`;
- `LoanResponseDto.recurringRule` generates `{}`.

Owner/action: backend loan/OpenAPI owner must reference explicit existing
estimate, schedule-row, recurrence, and conversion DTOs without changing the
approved calculation boundary.

Blocks: Step 08 loans.

### CG-004 — Report activity source provenance

Affected operations:

- `ReportingController_current`;
- `ReportingController_month`;
- `ReportingController_year`.

Observed: `ReportActivityItemDto.source` generates `{}`.

Owner/action: backend reporting/OpenAPI owner must describe the existing source
provenance shape. The frontend must not derive it from identifiers or raw
journal state.

Blocks: Step 04/05 provenance-dependent report activity. Summary and aggregate
fields that are already typed remain usable.

### CG-005 — Generic investment read-model substructures

Affected operations: all 7 investment operations.

Observed:

- `InvestmentResponseDto.scenario` generates `{}`;
- `recurringContributionForecast` generates `{}`;
- `movements` generates `Array<{}>`;
- `recurringRule` generates `{}`.

Owner/action: backend investments/OpenAPI owner must reference explicit existing
scenario, forecast, movement, and recurrence DTOs. No calculation may move to
Angular.

Blocks: Step 08 investments.

### CG-006 — Securities response envelope

Affected operations: all 15 securities operations.

Observed: `SecuritiesResponseDto.data` generates `{}` for portfolio, activity,
commands, import preview/commit, refresh jobs, quotes, instruments, prices,
watchlist, and clear requests.

Owner/action: backend securities/OpenAPI owner must replace the generic envelope
with operation-specific response DTOs representing existing runtime shapes.

Blocks: Step 09 in full.

### CG-007 — Feedback response envelope

Affected operations:

- `FeedbackController_list`;
- `FeedbackController_create`;
- `FeedbackController_status`.

Observed: `FeedbackResponseDto.data` generates `{}`. The delete operation's
intentional 204 remains usable.

Owner/action: backend feedback/OpenAPI owner must publish operation-specific
owned feedback list/item response DTOs.

Blocks: Step 10 feedback.

### CG-008 — Administration response envelope

Affected operations: the 15 administration-controller operations that return
`AdministrationResponseDto`; integration deletion intentionally returns 204.

Observed: `AdministrationResponseDto.data` generates `{}` for dashboard,
analytics, users, user actions, feedback, system settings, and integration
writes.

Owner/action: backend administration/OpenAPI owner must publish
operation-specific, masked, PII-safe response DTOs.

Blocks: the corresponding Step 11 admin pages.

### CG-009 — Notification preference response

Affected operations:

- `NotificationsController_preference`;
- `NotificationsController_update`.

Observed: the successful response schemas contain examples but no declared
properties, producing untyped/empty generated results.

Owner/action: backend notifications/OpenAPI owner must add an explicit
preference response DTO with the existing educational-email state.

Blocks: Step 10 notification preferences.

### CG-010 — Administration email response contracts

Affected operations:

- `NotificationsAdminController_templates`;
- `NotificationsAdminController_preview`;
- `NotificationsAdminController_test`;
- `NotificationsAdminController_channel`;
- `NotificationsAdminController_updateChannel`;
- `NotificationsAdminController_updateSettings`.

Observed: all six successful inline response schemas generate `{}`.

Owner/action: backend notifications/OpenAPI owner must publish explicit,
PII-safe templates, synthetic preview/test-job, channel, and setting response
DTOs.

Blocks: Step 11 email administration.

### CG-011 — System-settings nullable fields

Affected operation: `AdministrationController_settings`.

Observed: generated `UpdateSystemSettingsDto` types `primaryUrl`,
`supportEmail`, `contactEmail`, `logoUrl`, `faviconUrl`, and
`maintenanceMessage` as `{}` or `{} | null` rather than their existing scalar
types.

Owner/action: backend administration/OpenAPI owner must declare the scalar
type plus nullability/format for each field.

Blocks: Step 11 system settings.

### CG-012 — Integration metadata

Affected operation: `AdministrationController_integration`.

Observed: `PutIntegrationDto.metadata` generates `{}` without a documented
property/value contract.

Owner/action: backend administration owner must either define the accepted
metadata schema or remove the input from the public contract. The write-only
secret stays write-only.

Blocks: metadata editing in Step 11. Name/status/secret fields remain typed.

### CG-013 — Administrative billing response envelope

Affected operations: the 14 administrative billing operations that return
`BillingResponseDto`; plan/promotion deletion intentionally return 204.

Observed: `BillingResponseDto.data` generates `{}`.

Owner/action: backend billing/OpenAPI owner must publish operation-specific
administrative record response DTOs.

Blocks: Step 11 billing in full.

### CG-014 — Administrative billing input scalars

Affected operations:

- plan create/update;
- promotion create/update/trial;
- invoice update;
- payment create/update.

Observed generated `{}`/`{} | null` fields:

- `PlanDto`: `description`, `trialDays`, `isActive`, `stripeProductId`,
  `stripePriceId`, `metadata`;
- `PromotionDto`: `description`, `currency`, `maxRedemptions`, `redeemBy`,
  `trialDays`, `planCode`, `stripeCouponId`, `stripePromoCodeId`, `metadata`;
- `TrialPromotionDto`: `trialDays`, `maxRedemptions`;
- `UpdateInvoiceDto`: `paidAt`, `failureReason`, `refundReason`, `notes`;
- `PaymentDto`: `invoiceId`, `gateway`, `transactionReference`,
  `failureReason`, `notes`.

Owner/action: backend billing/OpenAPI owner must declare the existing scalar,
date-time, boolean, integer, nullable, and metadata shapes.

Blocks: affected Step 11 billing forms even after CG-013 is corrected.

## Confirmed usable intersections

The generator may emit harmless `Dto & {}` intersections for documented
`allOf` references. These retain the referenced DTO and are not classified as
gaps:

- `BudgetRuleResponseDto.plan` retains `BudgetRulePlanResponseDto`;
- `BudgetRulesResponseDto.period` retains
  `BudgetPlanPeriodResponseDto`;
- `RecurringRuleResponseDto.forecast` retains
  `RecurrenceForecastResponseDto`;
- `GoalResponseDto.recurringRule` retains
  `GoalRecurringRuleResponseDto`.

They may be cleaned up in a generator upgrade, but frontend feature work need
not be blocked by them.

## Intentional no-body operations

The following classes are accepted as intentional `void` only where the
OpenAPI and runtime agree:

- 204 logout, password change, email verification, passkey login/delete,
  currency/rule/category/income/recurrence/loan/goal/investment/watchlist/admin
  integration/plan/promotion/feedback deletions;
- accepted registration and generic verification-request commands whose
  anti-enumeration UX does not require a response body.

Passkey option/registration operations are explicitly excluded from this
allowance by CG-001 because the browser workflow requires their runtime result.

## Non-blocking observations

- `GET /health/live` and `GET /health/ready` are fully typed and assigned to
  operations/CI rather than a public Angular page.
- `GET /admin/operations/queues` has an explicit inline schema and is usable for
  the internal operations page.
- Privacy export/deletion responses are explicit and usable.
- Current user, theme, onboarding, currency, category, basic-income, recurrence,
  emergency-reserve, goal, and report aggregate DTOs are otherwise usable.

## Closure register

| Gap    | Approval            | Backend correction  | Client regenerated | Freeze checks | Status  |
| ------ | ------------------- | ------------------- | ------------------ | ------------- | ------- |
| CG-001 | approved 2026-07-31 | complete 2026-07-31 | yes                | passed        | closed  |
| CG-002 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-003 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-004 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-005 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-006 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-007 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-008 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-009 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-010 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-011 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-012 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-013 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
| CG-014 | approved 2026-07-31 | not started         | no                 | not rerun     | blocked |
