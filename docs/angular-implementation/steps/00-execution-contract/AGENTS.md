# Step 00 — Angular Execution Contract and Decisions

## Objective

Freeze the frontend architecture, route ownership, design choices, testing
stack, and backend-contract traceability before Angular implementation begins.

## Dependencies

- Backend Steps 00–22 are implemented.
- Frozen OpenAPI, generated Angular client, endpoint coverage matrix, and
  backend completion report exist.
- No Angular implementation step is a dependency.

## Required evidence

Read:

- `docs/angular-implementation/NESTJS-API-AND-BUSINESS-LOGIC.md`;
- `docs/angular-implementation/ANGULAR-IMPLEMENTATION-PLAN.md`;
- `docs/backend-implementation/steps/00-execution-contract/DECISIONS.md`;
- `docs/backend-implementation/steps/00-execution-contract/BACKEND-V1-SCOPE.md`;
- `docs/backend-implementation/steps/22-postman-backend-freeze/BACKEND-COMPLETION-REPORT.md`;
- `docs/backend-implementation/steps/22-postman-backend-freeze/ENDPOINT-COVERAGE-MATRIX.md`;
- `apps/api/openapi/openapi.json`;
- `libs/generated/api-client/README.md`;
- workspace package, Nx, TypeScript, lint, formatting, and CI configuration.

## Required deliverables

Create under this step directory:

- `DECISIONS.md` with approved frontend ADRs;
- `ROUTE-MAP.md` with every proposed Angular route, shell, guard, feature owner,
  lazy boundary, and API dependency;
- `API-UI-COVERAGE.md` mapping all 148 operations to a page, indirect workflow,
  operational owner, or provider-disabled state;
- `DESIGN-SYSTEM-CONTRACT.md` defining semantic tokens, density, breakpoints,
  Material/Tailwind ownership, modes, palettes, accessibility, and motion;
- `TEST-STRATEGY.md` defining component, HTTP-contract, Playwright,
  accessibility, visual, and contract-drift gates;
- `TRACEABILITY.md` mapping backend Critical/High corrections and frontend
  handoff invariants to planned Angular steps and tests.
- `CONTRACT-GAPS.md` auditing every successful non-empty operation for a usable
  generated response type and recording the owner/action for each gap.

Record decisions for:

- exact-decimal frontend library;
- chart renderer;
- runtime localization implementation;
- component-test runner;
- Playwright visual comparison;
- icon and font delivery;
- browser support;
- same-origin local and production topology;
- display-mode local persistence;
- route hierarchy and shell navigation.

Do not silently choose an unresolved third-party package. Inspect current
compatibility and maintenance evidence, present the smallest viable choices,
and obtain owner approval when the choice affects bundle, accessibility,
licensing, contract behavior, or long-term maintenance.

## Required checks

- Prove the frozen OpenAPI contains 113 paths and 148 operations or document an
  approved newer baseline.
- Confirm generated-client drift checks pass.
- Confirm generated-client response types are usable, not merely present.
- Confirm backend owner-acceptance status exactly as recorded; do not infer it.
- Confirm no route or page claims an excluded capability.
- Confirm each operation has one coverage disposition.
- Confirm every later step has a dependency and traceability owner.

At minimum, explicitly verify the currently observed gaps:

- passkey option and registration responses generated as `void`, plus no
  passkey-list contract;
- notification-preference responses without schemas;
- `SecuritiesResponseDto.data`, `AdministrationResponseDto.data`, and
  `BillingResponseDto.data` generated as empty object types.

These gaps cannot be solved with frontend casts or duplicated interfaces. If
confirmed, stop before the affected feature, request approval for a scoped
backend/OpenAPI contract correction, regenerate the client, and require the
backend contract/freezing checks to pass again.

## Non-goals

- Do not scaffold Angular.
- Do not install packages.
- Do not implement components, styles, routes, or tests.
- Do not change backend contracts.
- Do not select new product features.

## Acceptance criteria

- All required decisions are approved or one precise blocking decision request
  is recorded.
- Route and API coverage are complete with no unexplained operation.
- Every operation needed by Steps 03–11 has a usable generated response type or
  an explicitly approved correction owner that blocks its dependent step.
- The design and test contracts are actionable.
- The traceability register has no unowned Critical/High frontend concern.
- No Step 01 or later implementation has started.
