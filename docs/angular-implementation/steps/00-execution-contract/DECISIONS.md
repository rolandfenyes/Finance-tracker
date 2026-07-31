# Angular Step 00 Decision Record

Status: **approved**

This file records the approved frontend decision set for MyMoneyMap. It does
not install packages. The choices below are binding for Step 01 and later
Angular implementation steps.

## Evidence and baseline

- Angular target: 21.x, standalone APIs, Nx workspace.
- Contract baseline: `apps/api/openapi/openapi.json`, 113 paths and 148
  operations.
- Generated client: `libs/generated/api-client/src`, generated from the frozen
  contract and never edited by hand.
- Backend completion report: technical verification is recorded as passing, but
  **owner acceptance is explicitly still pending**.
- The contract audit in `CONTRACT-GAPS.md` found response-shape gaps. Approval of
  this decision record does not authorize frontend casts or backend edits.

## Recommended decision set A

### ADR-FE-001 — Exact decimal arithmetic

Use `decimal.js` behind a project-owned `ExactDecimalAdapter`. Generated and
application DTOs retain exact financial values as strings. Components cannot
import `decimal.js` directly. The adapter may compare, add, subtract, multiply,
divide, round for display, and convert back to a canonical decimal string; it
must never convert an exact value to a JavaScript `number`.

Reason: the library is dependency-free and provides arbitrary-precision decimal
arithmetic. The adapter boundary prevents vendor types from entering feature
code and makes a later replacement measurable.

Rejected:

- native `number`: violates the exact-value contract;
- `bigint`: cannot represent decimal rates and quantities without inventing a
  scale protocol;
- feature-local arithmetic helpers: duplicate financial-risk boundaries.

### ADR-FE-002 — Charts

Use Apache ECharts through `ngx-echarts` 21.x, imported as standalone,
tree-shaken modules behind project-owned chart components. Canvas is the default
renderer. Every chart must provide an adjacent textual summary or accessible
table and cannot be the only carrier of status or value.

Reason: `ngx-echarts` publishes an Angular 21 compatibility line and supports
standalone imports and tree-shaken ECharts modules. The wrapper is isolated in
`libs/web/design-system`; features provide typed display-series inputs, not raw
backend records.

Rejected:

- direct ECharts use in feature components: leaks renderer concerns;
- a second chart library: increases bundle and accessibility maintenance;
- recalculating chart totals: violates backend read-model ownership.

### ADR-FE-003 — Runtime localization

Use Transloco for runtime EN, ES, and HU catalogs, with English as the fallback.
Route feature catalogs are lazy-loaded. The initial language is the supported
`desiredLanguage` returned by `GET /api/v1/users/me`; unsupported or missing
values fall back to English. A signed-in change is persisted through
`PATCH /api/v1/users/me`.

Reason: immediate runtime switching is a product requirement and Transloco
supports standalone Angular, lazy loading, runtime switching, and fallback
languages. Angular's compile-time localization alone does not meet the runtime
switch requirement.

No user-facing string may bypass a catalog. API error `code` values map to
catalog keys; server messages are never treated as trusted translated copy.

### ADR-FE-004 — Unit and component tests

Use the Angular CLI `@angular/build:unit-test` target with Vitest and `jsdom`.
Use Angular TestBed and Angular Material/CDK component harnesses for interactive
primitives. Use `HttpTestingController` only at the API adapter boundary.

Reason: Vitest is the default Angular CLI testing setup for new Angular
projects. The CLI owns its integration; custom runner configuration is allowed
only for a proven missing capability.

### ADR-FE-005 — End-to-end and visual comparison

Use `@playwright/test` for browser journeys and its built-in
`expect(page).toHaveScreenshot()` for selected stable visual contracts. Store
reviewed baselines in the repository and generate/compare them in the same
Linux CI image. Do not add a hosted visual-testing provider.

Required Playwright projects:

- `mobile-chromium`: 320 CSS-pixel minimum coverage plus the agreed mobile
  device profile;
- `desktop-chromium`: primary functional and visual gate;
- `desktop-firefox` and `desktop-webkit`: critical smoke journeys.

Visual baselines cover the shells, core design-system states, light/dark modes,
all palettes through token tests, and representative mobile/desktop pages. They
must use synthetic data, reduced motion, deterministic time, and bundled fonts.

### ADR-FE-006 — Icons and fonts

Use Angular Material icons through a project-owned SVG icon registry containing
only bundled, reviewed SVG assets. Do not depend on the Google Fonts/Icons CDN
or allow arbitrary runtime icon URLs. Use a local system font stack:

`Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`

`Inter` is optional and may be bundled later only if its font files and license
are committed and the performance budget remains satisfied. Until then the
system stack is authoritative.

### ADR-FE-007 — Browser support

Compilation support follows Angular 21's documented Baseline date
(`2025-10-20`) and the generated Browserslist target; do not maintain a
conflicting handwritten list. CI exercises current Playwright-bundled Chromium,
Firefox, and WebKit. Responsive journeys cover 320px mobile and 1440px desktop;
Step 12 adds the agreed intermediate reflow checks.

Unsupported-browser handling is a factual generic message. It must not claim
support for a browser that Angular 21 excludes.

### ADR-FE-008 — Same-origin topology

Local:

- the Angular dev server serves the UI;
- an Angular/Nx proxy forwards `/api` to the local NestJS server;
- browser code calls relative `/api/v1/...` URLs only;
- cookies remain HttpOnly and are sent with same-origin requests.

Production:

- one HTTPS origin serves the Angular assets and routes `/api` to NestJS at the
  reverse proxy/load balancer;
- no production API hostname is embedded in the browser bundle;
- SPA fallback excludes `/api`;
- forwarded-proxy and secure-cookie behavior remains backend-owned.

Do not add bearer-token storage, a client-generated CSRF header, or cross-origin
credential logic absent from the frozen API.

### ADR-FE-009 — Display mode and palette persistence

- `system`, `light`, or `dark` is device-local and stored under the namespaced
  key `mymoneymap.display-mode.v1`.
- The value is validated against the three allowed literals before use.
- A tiny pre-paint initializer applies the mode before Angular bootstraps.
- Palette is account-owned and read/written only through the theme-preference
  API. The eight IDs are `blue`, `green`, `purple`, `orange`, `teal`, `indigo`,
  `pink`, and `red`.
- Browser storage contains no token, session ID, PII, financial value, or
  server-owned onboarding state.

### ADR-FE-010 — Route hierarchy and navigation

Adopt the hierarchy in `ROUTE-MAP.md`:

- public/anonymous identity routes under `/auth`;
- server-driven onboarding under `/onboarding`;
- authenticated personal-finance routes under `/app`;
- role-gated operational routes under `/admin`.

Each feature is a route-level lazy boundary. `/app` and `/admin` use separate
shells. Mobile uses bottom navigation for Home, Activity, Plan, Goals, and More;
desktop uses a side rail/drawer with the same information architecture.
Entitlement guards are usability gates only; the API remains authoritative.

## Package and maintenance constraints

- Step 01 must select versions compatible with the workspace's Angular 21 line
  and lock them in `package-lock.json`.
- Package installation is forbidden in Step 00.
- Licenses must be compatible with distribution: `decimal.js` (MIT),
  `ngx-echarts` (MIT), Apache ECharts (Apache-2.0), Transloco (MIT), Angular
  Material (MIT), Tailwind CSS (MIT), Playwright (Apache-2.0), Vitest (MIT).
- Dependencies are isolated behind project-owned boundaries so feature code
  does not couple directly to decimal, chart, localization, or icon vendors.

## Approval record

Owner decision: **Decision Set A approved in full on 2026-07-31**

The approval includes:

1. ADR-FE-001 through ADR-FE-010;
2. the route ownership and design/test contracts referenced here;
3. treating the OpenAPI gaps in `CONTRACT-GAPS.md` as blockers owned by a
   separately approved backend/OpenAPI correction, followed by client
   regeneration and all freeze checks;
4. withholding backend owner acceptance until those Angular-blocking response
   contracts are corrected and re-verified.

The approval authorizes a separately executed, scoped backend/OpenAPI contract
correction for CG-001 through CG-014. It does not authorize a backend change in
Angular Step 00, and it does not close any gap before the client is regenerated
and all backend freeze checks pass.

No backend owner acceptance is inferred from this Angular decision. The backend
completion report remains awaiting acceptance until the approved contract
corrections are completed and re-verified.

## Primary compatibility evidence

- Angular documents Vitest as the default new-project test runner and its
  Angular CLI integration:
  <https://angular.dev/guide/testing>
- Angular 21 compatibility and browser Baseline:
  <https://angular.dev/reference/versions>
- Playwright built-in visual comparisons and same-environment requirement:
  <https://playwright.dev/docs/test-snapshots>
- `decimal.js` arbitrary-precision and dependency-free design:
  <https://github.com/MikeMcl/decimal.js>
- `ngx-echarts` Angular 21 compatibility and standalone/tree-shaken setup:
  <https://github.com/xieziyu/ngx-echarts>
- Transloco runtime switching, standalone, lazy loading, and fallback support:
  <https://github.com/jsverse/transloco>
