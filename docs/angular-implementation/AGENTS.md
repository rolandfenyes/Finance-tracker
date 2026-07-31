# Angular Implementation — Shared Agent Contract

These instructions apply to every Angular step under
`docs/angular-implementation/steps/`.

## Required reading and source order

Before implementing a step, read completely:

1. `docs/angular-implementation/IMPLEMENTATION-PLAN.md`;
2. `docs/angular-implementation/AGENTS.md`;
3. the current step's `AGENTS.md`;
4. `docs/angular-implementation/NESTJS-API-AND-BUSINESS-LOGIC.md`;
5. `docs/angular-implementation/ANGULAR-IMPLEMENTATION-PLAN.md`;
6. every source file and backend record referenced by the current step;
7. any applicable repository-level `AGENTS.md`.

Use this source-of-truth order:

1. approved Angular decisions from Step 00;
2. frozen OpenAPI at `apps/api/openapi/openapi.json`;
3. generated client under `libs/generated/api-client/src`;
4. implemented NestJS behavior and approved backend decisions;
5. the Angular handoff documents;
6. legacy UI behavior only where the corrected backend still supports it.

If evidence conflicts and the approved records do not resolve it, stop and ask
one precise question. State the evidence, choices, consequences, and
recommendation. Do not invent a business rule or silently expand the API.

## Step isolation

- Work only on the requested step.
- Verify all listed dependencies before editing.
- Preserve unrelated and user-owned changes.
- Do not repeat completed deliverables.
- Do not start a later step.
- Do not commit or push unless the user explicitly requests it.
- Do not change NestJS behavior, PostgreSQL schema, OpenAPI, or generated client
  to make a frontend assumption work. Report the exact contract gap instead.
- Never hand-edit files under `libs/generated/api-client/src`.
- Do not replace missing OpenAPI response schemas with handwritten casts or
  duplicate interfaces. A frontend-blocking schema gap must be corrected at the
  authoritative backend/OpenAPI boundary and the client regenerated through an
  explicitly approved contract change.

## Architecture constraints

- Keep the authenticated application in `apps/web-app` and Playwright in
  `apps/web-app-e2e`.
- Use standalone Angular APIs and route-level lazy loading unless Step 00
  explicitly supersedes this.
- Use Angular signals for local/view state and RxJS at asynchronous boundaries.
- Do not add a global state framework without an approved decision and measured
  need.
- Angular Material owns accessible interactive primitives and overlays.
- TailwindCSS owns layout, spacing, sizing, responsive composition, and simple
  surfaces.
- CSS custom properties own semantic design tokens.
- Feature libraries may depend on core, shared, design system, and the generated
  API client. They must not import another feature's internals.
- Do not create a second mutable copy of backend domain rules or generated DTOs.

## API and financial constraints

- Use same-origin `/api` routing and the HttpOnly session cookie.
- Do not store session IDs or bearer credentials in browser storage.
- Do not invent a CSRF header absent from the frozen contract.
- Treat `401`, `403`, `409`, `422`, `429`, and provider-disabled responses as
  distinct states.
- Preserve opaque cursors unchanged.
- Add `Idempotency-Key` only to operations that declare it.
- Retain one key across retries of the same user intent.
- Keep exact amounts, percentages, rates, and quantities as decimal strings.
- Never use JavaScript `number`, `parseFloat`, or implicit coercion for a
  financial calculation.
- Render totals, balances, progress, FIFO, FX, allocation, amortization, and
  projections from approved API read models. Do not recalculate them from raw
  records.
- Keep posted, forecast, projection, scenario, stale, delayed, and unavailable
  states visibly distinct.
- Use correction/reversal semantics for immutable history.

## Product, security, and privacy constraints

- Route from `GET /api/v1/users/me` and returned entitlements; client guards do
  not replace server authorization.
- Admin and personal-finance shells are separate.
- Do not expose excluded capabilities such as connected banks, SMS, push,
  checkout, provider webhooks, AI advice, tax features, or arbitrary roles.
- Do not log credentials, tokens, email addresses, names, notes, provider
  secrets, request bodies, or financial values.
- Remove verification tokens from the visible URL after use.
- Keep integration secrets write-only.
- Do not claim legal compliance or invent retention periods.
- Do not present scenarios or technical indicators as advice or guaranteed
  outcomes.

## UI and accessibility constraints

- Design mobile first from 320 CSS pixels, then tablet and desktop.
- Every page must implement loading, empty, error, success, partial-data, and
  relevant disabled/gated states.
- Meet WCAG 2.2 AA: keyboard access, visible focus, labels, error association,
  reflow, target size, reduced motion, and non-color status cues.
- Support EN, ES, and HU with English fallback.
- Support system/light/dark display modes and all eight approved palette IDs.
- Display mode is device-local until a backend field is explicitly approved.
- Use synthetic fixtures only in tests and visual snapshots.

## Testing and verification

Write tests with the implementation. Each step must run the relevant subset of:

- formatting, linting, type checking, and build;
- unit and Angular component tests;
- generated-client/OpenAPI drift checks;
- HTTP contract tests;
- Playwright journeys at mobile and desktop viewports;
- accessibility checks;
- visual/theme regression where the step changes UI;
- existing backend contract checks when an API boundary is exercised.

Do not skip, quarantine, loosen, or delete tests to obtain a pass. Fix failures
caused by the current step and report unrelated pre-existing failures.

## Completion report

At the end of every step report:

- step implemented;
- main files and routes created or changed;
- generated-client services and API operations consumed;
- reusable components or tokens added;
- business behavior preserved;
- tests and verification commands with results;
- accessibility and responsive evidence;
- acceptance-criteria status;
- approved decisions applied;
- unresolved risks or contract gaps;
- confirmation that no later step was started;
- confirmation that no commit or push was created unless requested.
