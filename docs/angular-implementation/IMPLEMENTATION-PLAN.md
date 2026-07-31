# MyMoneyMap Angular — Runnable Implementation Plan

**Target:** Angular 21 authenticated application  
**Workspace:** existing pnpm/Nx monorepo  
**UI:** Angular Material + TailwindCSS  
**Contract:** frozen NestJS `/api/v1` OpenAPI and generated Angular client  
**Delivery:** component tests during implementation, Playwright per feature,
full frontend freeze after administration

## Objective

Implement the Angular application described by
`ANGULAR-IMPLEMENTATION-PLAN.md` without recreating backend financial logic or
expanding the frozen API. Each step is an independently runnable, reviewable
slice with explicit dependencies and acceptance criteria.

## Fixed boundaries

- `apps/web-app` is the authenticated personal-finance and admin application.
- Astro remains responsible for public marketing and privacy content.
- NestJS remains authoritative for authentication, ownership, entitlements,
  calculations, transitions, and provider gates.
- The generated client is transport code and must remain reproducible.
- Browser authentication uses the same-site HttpOnly session cookie.
- Exact financial values remain decimal strings.
- Angular Material provides accessible interaction primitives.
- TailwindCSS provides responsive layout and utility styling.
- The UI is mobile first, localized in EN/ES/HU, and supports light/dark plus
  eight backend palettes.

## Dependency order

| Step | Directory                                    | Depends on               | Main outcome                                                        |
| ---: | -------------------------------------------- | ------------------------ | ------------------------------------------------------------------- |
|   00 | `00-execution-contract`                      | backend freeze documents | approved frontend decisions and traceability                        |
|   01 | `01-workspace-design-system`                 | 00                       | runnable Angular/Nx apps and design-system foundation               |
|   02 | `02-api-core-session`                        | 01                       | generated-client integration, session, errors, exact-value adapters |
|   03 | `03-identity-onboarding`                     | 02                       | auth, passkeys, verification, onboarding, tutorial                  |
|   04 | `04-product-shell-dashboard`                 | 02–03                    | responsive product shell and current-month dashboard                |
|   05 | `05-journal-reports`                         | 04                       | immutable journal workflows and historic reporting                  |
|   06 | `06-planning`                                | 04–05                    | categories, incomes, budget rules, recurrence                       |
|   07 | `07-goals-emergency-reserve`                 | 05–06                    | goals and reserve transfer workflows                                |
|   08 | `08-loans-investments`                       | 05–07                    | loans and generic investments                                       |
|   09 | `09-securities`                              | 05, 08                   | portfolio, trades, cash, import, instruments, watchlist             |
|   10 | `10-feedback-settings-notifications-privacy` | 03–09                    | user feedback and complete settings/privacy surface                 |
|   11 | `11-administration`                          | 02–10                    | guarded admin, operations, email, and billing records               |
|   12 | `12-hardening-frontend-freeze`               | 01–11                    | complete QA, accessibility, performance, and contract freeze        |

Steps are sequential unless a step explicitly says a deliverable can be safely
prepared in parallel. A later route may not be started merely because its
generated API service already exists.

## Cross-step deliverables

### Application structure

```text
apps/web-app
apps/web-app-e2e
libs/web/core
libs/web/design-system
libs/web/shared
libs/web/feature-*
libs/generated/api-client
```

Do not create all feature libraries speculatively in Step 01. Create a feature
library when its owning step begins.

### Quality gates

From Step 01 onward, maintain runnable Nx targets for:

- build;
- lint;
- type checking;
- component/unit tests;
- Playwright;
- formatting;
- contract drift.

Each feature step adds mobile and desktop coverage for its critical user
journeys. Step 12 runs the complete suite and records the final evidence.

### API coverage

By the end of Step 12 every one of the 149 frozen operations must have one of:

- an implemented Angular interaction;
- an explicit internal/operational owner with no end-user page;
- a documented provider-disabled state;
- a documented reason that the operation is used indirectly.

No operation may disappear from the coverage register silently.

## Step 00 decisions

Step 00 must record, with owner approval where material:

- exact-decimal frontend library;
- chart rendering implementation;
- runtime localization implementation;
- Angular component-test runner and configuration;
- Playwright visual-regression approach;
- icon/font delivery policy;
- supported browser baseline;
- final route map;
- same-origin dev/prod topology;
- design token and Material/Tailwind ownership rules;
- whether backend owner acceptance is recorded or still pending.
- whether each successful non-empty response has a usable generated schema.

Angular, Nx, Angular Material, TailwindCSS, the generated client, mobile-first
delivery, EN/ES/HU, and light/dark/multiple palettes are already fixed.

### Known contract-usability blockers to verify in Step 00

The frozen contract currently contains operations whose existence is covered
but whose generated response type is not sufficient for Angular:

- CG-001 is closed: passkey registration/authentication options, nested browser
  credentials, registration success, owned-passkey listing, and UUID deletion
  are explicitly typed;
- notification-preference reads and updates have no response schema;
- securities responses collapse to `SecuritiesResponseDto.data: {}`;
- administration responses collapse to `AdministrationResponseDto.data: {}`;
- billing responses collapse to `BillingResponseDto.data: {}`.

Step 00 must audit the full generated client for equivalent gaps. Do not begin
an affected Angular feature with handwritten duplicate DTOs. Record and obtain
approval for a backend/OpenAPI contract correction, regenerate the client, and
re-run the backend freeze checks before the dependent frontend step.

## Completion definition

Angular is ready for handoff when:

- Steps 00–12 are complete and accepted;
- all documented routes and states are implemented;
- API operation coverage is complete;
- exact-value boundary tests prove no local financial recalculation;
- free/premium/admin, verification, ownership, and provider gates are reflected
  accurately;
- component, contract, Playwright, accessibility, responsive, and visual suites
  pass;
- all locales and palette/mode combinations are reviewed;
- production same-origin session behavior is verified;
- OpenAPI and generated-client drift checks pass;
- known limitations and owner-controlled production gates are documented;
- the owner explicitly accepts the Angular v1 completion report.

## Step-agent files

Each directory under `docs/angular-implementation/steps/` contains an
`AGENTS.md`. The shared contract in `docs/angular-implementation/AGENTS.md`
applies to every step.
