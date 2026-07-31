# Step 02 — API Core, Session, Errors, and Exact-Value Boundaries

## Objective

Integrate the generated Angular client and build the shared session, HTTP,
error, idempotency, decimal, currency, date, locale, and route-policy
boundaries used by every feature.

## Dependencies

- Steps 00–01 are complete.
- The Angular application, tests, design tokens, and proxy are operational.
- `pnpm contracts:check` passes before implementation.

## Required evidence

Read:

- Step 00 API coverage and decisions;
- frozen OpenAPI and generated-client configuration/base service;
- backend bootstrap, session, authentication guards, errors, pagination,
  idempotency, decimal/currency/date primitives, and users/entitlements DTOs;
- current-user, theme, onboarding, health, and error schemas.

## Required deliverables

- `libs/web/core` with a narrow public API.
- Generated `ApiConfiguration` using same-origin root URL.
- API-session interceptor scoped to `/api` and explicit credentials.
- Stable API-error parser with request ID and field-violation mapping.
- Idempotency request context that attaches a key only when the caller provides
  one for a declared operation.
- Safe observability boundary using route template, method, status, duration,
  and request ID only.
- Current-user/session store bootstrapped exclusively from `GET /users/me`.
- Guards for signed-out, authenticated, verified-email, personal-finance,
  administration, onboarding, capability, and pending changes.
- Correct handling for `401`, verification/role/entitlement `403`, `409`,
  `422`, `429`, and service unavailable.
- Exact-decimal adapter using the Step 00-approved library.
- money, percent, calendar-date, instant, freshness, cursor, and RRULE
  presentation utilities.
- typed command lifecycle and idempotency-key retention helper.
- route-level error/unavailable/forbidden/not-found pages.
- API-operation coverage register updated for operations consumed here.

No feature page may aggregate financial records or calculate a backend-owned
total.

## Required behavioral tests

- same-origin cookie request behavior and absence of browser-stored auth token;
- signed-out/signed-in/admin/personal route matrices;
- session expiry and state clearing;
- current-user refresh after relevant mutations;
- exact decimal string parsing/formatting/comparison;
- no `number` coercion at public exact-value boundaries;
- date round trips without timezone shift;
- cursor opacity;
- idempotency key reuse for the same intent and reset for a new intent;
- API error and violation mapping;
- retry timing from `Retry-After`;
- safe telemetry/log payloads.

Use synthetic generated DTO fixtures and HTTP fakes. Do not call live providers.

## Acceptance criteria

- The app derives authentication and entitlements from the backend session.
- No JWT/session data is stored in browser storage.
- Exact-value and error boundaries are reusable and tested.
- Generated client files remain untouched and drift-free.
- No identity, onboarding, dashboard, or later feature UI was implemented.
- Step 03 was not started.
