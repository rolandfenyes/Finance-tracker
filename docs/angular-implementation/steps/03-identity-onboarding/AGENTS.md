# Step 03 — Identity, Passkeys, Onboarding, and Tutorial

## Objective

Implement the complete evidenced signed-out identity experience and
server-directed onboarding/tutorial flow.

## Dependencies

- Steps 00–02 are complete.
- Session bootstrap, guards, API errors, exact-value adapters, localization,
  and responsive shells are operational.
- Passkey option and registration responses have usable generated schemas, and
  Step 00 resolved how existing passkey IDs are obtained for deletion.

## Required evidence

Read identity/users controllers, DTOs, generated identity and user-setting
services, WebAuthn configuration/adapter contracts, supported locales/themes,
onboarding transitions, and all identity/onboarding sections of the API and
Angular handoff.

## Routes and required behavior

- `/auth/login`: password login, generic failure, remember-server-session,
  passkey entry, rate-limit state.
- `/auth/register`: email, password, full name, calendar date of birth;
  accepted/non-enumerating success.
- `/auth/verify-email`: consume token, remove it from visible URL/history, then
  refresh session state.
- `/auth/verification-sent`: factual resend flow without account enumeration.
- `/auth/passkey`: begin/finish browser authentication with cancellation and
  unsupported-browser handling.
- `/onboarding`: use server-returned `next`, never infer state from empty lists.
- `/onboarding/theme`: persist approved palette and preview mode/palette.
- `/onboarding/rules`: atomically initialize the approved percentage-rule set.
- `/onboarding/currencies`: catalogue, membership, main-currency, quota.
- `/onboarding/categories`: kind/color/quota and protected semantics.
- `/onboarding/income`: planning-only baseline income.
- `/onboarding/tutorial`: responsive product walkthrough and one-way completion.

Also implement passkey enrollment and password change as reusable security
panels for the later settings route. Implement passkey deletion only through
the Step 00-approved typed credential-list/identifier contract; do not retain a
credential ID in browser storage or invent a list. Do not yet build the full
settings hub.

There is no public reset-password completion endpoint. Do not add a forgot
password form or invent API behavior.

## Reusable UI

- auth and onboarding form shells;
- password field and strength/help presentation based only on contract rules;
- passkey browser adapter;
- onboarding progress component;
- palette selector;
- currency and category selection primitives;
- form error summary and field violation focus;
- non-enumerating accepted-state panel.

## Tests

- registration does not reveal duplicate-account state;
- login, remembered session, logout, expiry, and throttling;
- invalid/expired verification and resend behavior;
- verification token removed from URL and logs;
- passkey create/get serialization, cancellation, origin failure, and unsupported
  browser;
- server-directed onboarding transitions;
- invalid palette/locale and entitlement quotas;
- planning income does not appear as a posted transaction;
- keyboard, screen-reader labels, 320 px layout, and EN/ES/HU copy;
- Playwright registration/verification/login/onboarding and passkey fixture
  journeys.

## Acceptance criteria

- Every public identity and onboarding operation has an intentional UI.
- No account enumeration, token leakage, or client-owned onboarding inference
  remains.
- The app reaches the correct product/admin destination after session refresh.
- Full settings and dashboard work were not started.
- Step 04 was not started.
