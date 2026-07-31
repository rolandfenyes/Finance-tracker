# Step 11 — Administration, Operations, Email, and Billing Records

## Objective

Implement the separate guarded administration application surface for defined
analytics, users, feedback, system/integration settings, email operations,
queue diagnostics, and administrative billing records.

## Dependencies

- Steps 00–10 are complete.
- Administration guard/session policy, design system, data views, dialogs,
  polling, errors, masking, and localization are stable.
- Administration and billing operations have concrete generated response
  schemas; empty `{ data: {} }` wrapper types are not acceptable.

## Required evidence

Read administration, billing, notification-admin, and operations controllers,
DTOs, generated services, admin guard, masked response contracts, audit
behavior, queue diagnostics, provider gates, and fixed-role/administrative
billing decisions.

## Routes and required behavior

- `/admin`: only defined operational counts.
- `/admin/analytics`: only implemented account/registration metrics.
- `/admin/users`: cursor/filter list with masked identity.
- `/admin/users/:id`: masked detail, login activity, fixed role/status,
  password-reset request, verification request, verified email-change request,
  subscription, invoice, and payment record actions.
- `/admin/feedback`: filters, staff-managed fields, attributed responses.
- `/admin/system`: validated non-secret settings.
- `/admin/system/integrations`: write-only secret create/replace/delete.
- `/admin/system/email`: template list, synthetic preview/test job, email
  channel/settings, production gate.
- `/admin/operations`: PII-safe queued/success/retry/dead-letter/suppression and
  provider-circuit diagnostics.
- `/admin/billing`: administrative summary.
- `/admin/billing/plans` and `/:id`: CRUD record UI.
- `/admin/billing/promotions` and `/:id`: CRUD and trial-promotion record UI.

## Security and product constraints

- The admin shell is inaccessible without `administration=true`.
- Do not expose personal-finance navigation to admin users.
- Roles are exactly free, premium, and admin.
- No impersonation or public migration operation.
- Recovery endpoints never reveal token or temporary password.
- Integration secrets are never read back or prefilled.
- Email previews use synthetic data and safe rendering.
- Email has one approved channel; no SMS or push UI.
- Production delivery remains gated.
- Billing is record administration only: no checkout, charging, customer
  portal, webhook, or self-service cancellation language.
- Do not invent analytics or display raw PII.

## Tests

- non-admin denial and admin shell routing;
- masked identities and write-only secrets;
- fixed role/status actions and current-session refresh where relevant;
- recovery accepted states without token disclosure;
- feedback filters/update/response;
- system validation and integration configured/masked states;
- template synthetic preview, safe rendering, test-job state, and production
  email gate;
- queue diagnostic states without payload/PII;
- plan/promotion/subscription/invoice/payment record workflows;
- absence of checkout/provider claims;
- cursor/filter URL behavior, mobile admin fallback, keyboard, EN/ES/HU;
- Playwright admin users, feedback, secret, email gate, operations, and billing
  journeys.

## Acceptance criteria

- Every guarded admin/billing/notification/operations operation has UI coverage.
- PII and secrets remain masked or write-only.
- Administrative records are not represented as provider-backed commerce.
- No hardening/freeze deliverable was started.
- Step 12 was not started.
