# Angular Test Strategy

Status: **approved by Decision Set A on 2026-07-31**

## Test layers

| Layer           | Tool                                      | Responsibility                                                                    |
| --------------- | ----------------------------------------- | --------------------------------------------------------------------------------- |
| Pure unit       | Angular CLI Vitest                        | exact adapter, pipes, validators, guards, error mapping, idempotency intent state |
| Component       | Vitest + TestBed + Material/CDK harnesses | rendering and interaction for all component states                                |
| HTTP contract   | generated client + Angular HTTP testing   | method/path/query/header/body/response use without duplicate DTOs                 |
| Browser journey | Playwright                                | real routing, cookies, forms, keyboard, responsive behavior                       |
| Accessibility   | axe integration + manual scripts          | automated WCAG rules plus keyboard/focus/reflow/semantics                         |
| Visual          | Playwright `toHaveScreenshot`             | reviewed stable shell/design-system/page states                                   |
| Contract drift  | repository scripts                        | OpenAPI snapshot/client generation and endpoint coverage                          |

## Component contract

Each page/component added by Steps 01–11 tests relevant:

- loading;
- empty;
- success;
- error with retry;
- partial data;
- disabled/provider-gated;
- entitlement/quota-gated;
- `401`, `403`, `409`, `422`, and `429`;
- light/dark and palette token application;
- EN, ES, HU and missing-key English fallback;
- keyboard and focus behavior;
- 320px reflow and desktop composition.

Tests assert exact source strings passed to the formatter/adapter, not
floating-point equivalents. Financial read-model components use fixtures that
would visibly disagree with a client-side recomputation, proving the server
value is rendered.

## HTTP-contract rules

- Consume generated services/models only.
- Assert relative `/api/v1` paths and cookie-based session behavior.
- Assert `Idempotency-Key` only for operations declaring it.
- Repeating one user intent retains the same key; a new intent gets a new key.
- Preserve opaque cursors byte-for-byte.
- Assert no token/session ID is written to local/session storage.
- A missing/`void`/`{}` required schema fails the feature gate; tests must not
  cast around it.
- Contract tests cover declared response status and typed error-code mapping.

## Playwright projects

| Project            | Purpose                                                                               |
| ------------------ | ------------------------------------------------------------------------------------- |
| `mobile-chromium`  | complete critical journeys from 320px, touch-sized actions, bottom navigation, reflow |
| `desktop-chromium` | complete critical journeys and visual baseline                                        |
| `desktop-firefox`  | critical smoke journeys                                                               |
| `desktop-webkit`   | critical smoke journeys and Safari-engine behavior                                    |

Use synthetic seeded accounts and deterministic server fixtures. Authentication
setup may store Playwright test state on disk only in ignored test output; it
must never be committed and must contain no real account data.

Critical journeys:

1. register, generic verification request, verify, login/logout;
2. server-directed onboarding;
3. dashboard and current-month partial/unavailable states;
4. journal create/correct/reverse with retry idempotency;
5. planning and recurrence;
6. goal/reserve movements;
7. loan/investment movements;
8. provider-gated and enabled securities paths;
9. feedback/settings/notifications/privacy;
10. admin authorization and masked operational workflows.

Dependent journeys remain blocked until their contract-gap IDs are closed.

## Accessibility

Automated:

- axe scan on every top-level route in representative state;
- accessible-name checks for inputs/actions;
- dialog/menu/table harness assertions;
- color-token contrast checks for all mode/palette/status pairs.

Manual/scripted evidence by Step 12:

- keyboard-only critical journeys;
- focus order, focus restoration, and visible focus;
- 200% zoom and 320 CSS-pixel reflow;
- reduced motion;
- screen-reader landmarks/headings/error announcements;
- chart textual alternatives;
- target sizing.

No automated tool is treated as proof of full WCAG conformance.

## Visual comparison

- Use Playwright built-in screenshots, not a third-party service.
- Generate and compare baselines in one pinned Linux CI image.
- Disable animation, freeze time, and use bundled/system fonts.
- Mask only genuinely volatile, non-contract regions; do not mask broken
  business content.
- Required snapshots: auth shell, product shell, admin shell, DataView states,
  dialogs, table/card responsive forms, charts with textual alternative,
  light/dark, and representative palette tokens.
- Baseline changes require human review; `--update-snapshots` is never part of a
  normal verification command.

## Security and privacy assertions

- browser storage contains only the validated display-mode key;
- visible URLs contain no verification token after consumption and no PII or
  financial payload;
- logs/telemetry fixtures reject credentials, token-like values, emails, names,
  notes, request bodies, and financial values;
- integrations remain write-only;
- admin identity stays masked;
- 401 clears in-memory session state without destructive browser-storage
  sweeping;
- provider-disabled and entitlement states do not reveal inaccessible data.

## Required gates by step

| Step | Minimum gates                                                                           |
| ---- | --------------------------------------------------------------------------------------- |
| 00   | doc completeness, 113/149 audit, generated-client drift, whitespace                     |
| 01   | format, lint, typecheck, unit/component, build, mobile/desktop shell/a11y/visual        |
| 02   | previous + HTTP contract, exact-decimal, session/security                               |
| 03   | previous + identity/onboarding Playwright                                               |
| 04   | previous + dashboard partial/provider states and charts                                 |
| 05   | previous + journal idempotency/correction/reversal/report filters                       |
| 06   | previous + planning invariants and recurrence forecast distinction                      |
| 07   | previous + goal/reserve read-model boundary                                             |
| 08   | previous + loan/investment projections/scenarios                                        |
| 09   | previous + securities provider/FIFO/import/refresh boundaries                           |
| 10   | previous + preferences/privacy/passkey/feedback security                                |
| 11   | previous + admin authorization, masking, write-only secrets, billing records            |
| 12   | complete suite, full browser matrix, a11y/performance/security reports, contract freeze |

## Commands

Step 01 owns the final Nx target names. The required command semantics are:

- formatting check;
- lint all affected projects;
- TypeScript typecheck;
- production build;
- Vitest unit/component suite with coverage;
- Playwright mobile and desktop projects;
- OpenAPI generation/checksum and generated-client drift;
- backend OpenAPI/HTTP contract checks when a frontend boundary depends on it;
- `git diff --check`.

CI must fail when a required PostgreSQL/Redis/backend dependency is unavailable;
it must not convert dependency failure into a skipped success.

## Coverage policy

Coverage percentages are a diagnostic, not a substitute for behavioral tests.
Step 01 may establish initial measurable thresholds after scaffolded source
exists. Thresholds may only increase or change through an approved decision;
tests may not be deleted, weakened, or quarantined to satisfy them.
