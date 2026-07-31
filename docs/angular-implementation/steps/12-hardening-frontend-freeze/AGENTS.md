# Step 12 — Angular Hardening, QA, and Frontend Freeze

## Objective

Verify the complete Angular application against the frozen backend, accessibility
and responsive requirements, security/privacy rules, performance budgets, and
all operation-coverage commitments, then produce the owner-reviewable frontend
completion report.

## Dependencies

- Steps 00–11 are complete.
- No required route, API operation, test, locale, palette, provider state, or
  accessibility deliverable remains knowingly unfinished.

Stop and report the exact incomplete preceding step if this is not true.

## Required evidence

Read all Angular step acceptance reports, Step 00 traceability/API coverage,
backend completion and endpoint coverage reports, frozen OpenAPI, generated
client, CI/deployment configuration, security headers/session bootstrap, and
all frontend tests.

## Required work

### Contract and feature coverage

- Reconcile all 149 operations against the final application.
- Run OpenAPI, generated-client, Postman, and route-coverage drift checks.
- Confirm health, migrations, legacy migration, metrics bearer access, and
  worker runners correctly have no end-user route.
- Confirm no excluded or provider-gated capability is overstated.

### Functional QA

- Run every component/unit test.
- Run every HTTP contract test.
- Run the complete Playwright suite against production-like PostgreSQL/Redis.
- Test session expiry and revocation during forms and financial commands.
- Test offline/network interruption and uncertain idempotent outcomes.
- Test concurrency/conflict/limit/provider-disabled states represented by the
  contract.
- Confirm no read-like UI interaction triggers a write.

### Financial boundary audit

- Search and test for `number`, `parseFloat`, unary coercion, local sums, FX,
  FIFO, amortization, compounding, allocation, balance, progress, or net-flow
  calculations that bypass approved presentation boundaries.
- Prove dashboard, reports, goals, reserve, loans, investments, and securities
  render authoritative DTO values.
- Confirm charts preserve exact source values and unavailable gaps.

### Accessibility, responsive, localization, and themes

- WCAG 2.2 AA audit with automated and manual keyboard/screen-reader checks.
- Reflow and interaction checks from 320 px through desktop at 200% zoom.
- Touch targets, focus order, dialogs, live regions, reduced motion, and
  non-color status.
- EN/ES/HU review with English fallback and long-string layouts.
- System/light/dark and all eight palettes, including contrast and status
  invariance.
- Visual-regression suite with synthetic data only.

### Performance and security

- Analyze initial and lazy-route bundles against Step 00 budgets.
- Confirm route-level lazy loading and no accidental feature bundle coupling.
- Test Core Web Vitals or approved equivalent on representative pages.
- Verify same-origin production routing, secure-cookie behavior, CSP/security
  headers, and no authenticated API service-worker caching.
- Audit logs, telemetry, storage, URLs, screenshots, and fixtures for secrets,
  tokens, PII, and financial data.
- Run dependency/security audit and document accepted findings without silently
  weakening controls.

### Completion artifacts

Create:

- `FRONTEND-COMPLETION-REPORT.md`;
- final `API-UI-COVERAGE.md`;
- `ACCESSIBILITY-REPORT.md`;
- `PERFORMANCE-REPORT.md`;
- `SECURITY-PRIVACY-REVIEW.md`;
- production configuration and deployment checklist without credentials;
- known limitations and provider/owner approval gates;
- owner acceptance section initially marked pending.

Do not mark owner acceptance yourself.

## Acceptance criteria

- All repository-owned verification passes.
- All 149 operations have an accepted disposition.
- Critical journeys pass at mobile and desktop viewports.
- Exact-value/calculation-source audits pass.
- Accessibility, locale, palette, mode, security, and performance gates pass or
  have an explicit owner-approved exception.
- No secret, PII, or real financial fixture is committed.
- OpenAPI and generated client are drift-free.
- Completion report is accurate and owner acceptance remains pending until
  explicitly supplied.
- No Astro or later product work was started.
