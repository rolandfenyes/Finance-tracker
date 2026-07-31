# MyMoneyMap Angular Application Implementation Plan

## 1. Objective

Build the authenticated MyMoneyMap product as a mobile-first Angular
application on the frozen NestJS v1 contract. The UI should feel like a
premium, calm fintech product while remaining precise about financial state,
data freshness, projections, and unavailable provider capabilities.

This is an implementation plan, not an instruction to reimplement backend
rules. Angular coordinates user intent, renders authoritative read models, and
provides accessible interaction. NestJS remains authoritative for
authentication, ownership, entitlements, financial invariants, calculation,
currency conversion, lifecycle transitions, and background work.

### Explicitly proposed frontend choices

The following are frontend design decisions in this plan, not existing backend
fields or API promises:

- standalone Angular components and route-level lazy loading;
- Angular signals for local/view state and RxJS at asynchronous/API boundaries;
- a system/light/dark display mode stored locally because the API currently
  persists only a color palette identifier;
- a chart adapter owned by the design-system layer;
- proposed Angular URLs listed below.

No new backend endpoint is required by this plan.

## 2. Definition of done

The Angular application is complete when:

- every personal-finance and admin operation in the frozen contract has an
  intentional UI or an explicitly documented non-UI operational owner;
- authentication, verification, onboarding, entitlements, provider gates,
  corrections, reversals, and idempotent submission are correctly represented;
- all exact financial values remain decimal strings outside the selected
  decimal/formatting adapter;
- layouts work from 320 px mobile width through desktop;
- light, dark, and all eight backend-supported palettes meet accessibility
  requirements;
- component tests cover reusable behavior and feature orchestration;
- Playwright covers critical user journeys and responsive variants;
- API-client and OpenAPI drift checks remain green;
- no interface claims support for excluded or disabled capabilities.

## 3. Technology and workspace architecture

### 3.1 Required stack

- Angular, aligned to the workspace’s Angular 21 major
- Nx and pnpm in the existing monorepo
- Angular Material for accessible interactive primitives
- TailwindCSS for layout, responsive composition, spacing, and utilities
- generated `ng-openapi-gen` client in `libs/generated/api-client`
- Angular component tests plus Playwright end-to-end tests

Do not mix Angular Material and Tailwind without ownership rules:

- Material owns dialog/overlay behavior, focus management, form controls,
  menus, tooltips, tables where suitable, snack bars, and accessibility
  semantics.
- Tailwind owns page grids, responsive breakpoints, spacing, sizing,
  visibility, typography composition, and simple surfaces.
- CSS custom properties own semantic design tokens.
- Feature components must not reach into private Material DOM selectors.
- Reusable primitives wrap Material where product behavior or visual
  consistency is needed.

### 3.2 Proposed Nx projects

```text
apps/
  api/                         existing NestJS application
  web-app/                     Angular application
  web-app-e2e/                 Playwright journeys
libs/
  generated/api-client/        existing generated transport client
  web/core/                    session, routing policy, HTTP, app configuration
  web/design-system/           tokens and reusable visual primitives
  web/shared/                  pipes, directives, general utilities
  web/feature-auth/
  web/feature-onboarding/
  web/feature-dashboard/
  web/feature-transactions/
  web/feature-planning/
  web/feature-reports/
  web/feature-goals/
  web/feature-emergency/
  web/feature-loans/
  web/feature-investments/
  web/feature-securities/
  web/feature-feedback/
  web/feature-settings/
  web/feature-admin/
```

Split a library only when it has a real ownership boundary. Do not create a
library per component or a second domain-model layer mirroring every generated
DTO.

### 3.3 Dependency direction

```text
feature route
  -> feature facade / view-state service
     -> generated API service
        -> NestJS /api/v1

feature route
  -> design-system and shared utilities
  -> core session, navigation, and policy
```

- `core` may depend on the generated client and shared utilities.
- features may depend on `core`, the generated client, design system, and
  shared utilities.
- design system must not depend on feature libraries.
- features must not import other feature internals.
- no frontend service writes directly to more than one backend domain to
  simulate a transaction. Use the authoritative backend command.

### 3.4 State management

Start with:

- signals for page state, selections, filters, and derived view state;
- RxJS for HTTP, debouncing, cancellation, polling, and WebAuthn interop;
- route resolvers only where the page cannot render a useful shell without the
  data;
- feature facades for commands, refresh rules, and idempotency state;
- URL query parameters for shareable filters, periods, and tabs;
- no global mutable copy of every server object.

Do not add a global state framework until measured cross-feature requirements
justify it. Session/current-user and app-wide appearance are the only initial
global stores.

## 4. API integration agreement

The detailed backend contract is in
`NESTJS-API-AND-BUSINESS-LOGIC.md`. Angular must additionally follow these
implementation rules.

### 4.1 HTTP setup

- configure generated `ApiConfiguration.rootUrl` as `''`;
- proxy `/api` to NestJS during local development;
- deploy same-site in production;
- clone API requests with credentials enabled;
- never attach a bearer token or read the HttpOnly cookie;
- use the generated request/response DTOs without editing them;
- use full response variants only when status or headers are required.

Recommended interceptors:

1. `api-session.interceptor`: credentials and API-origin scoping.
2. `api-idempotency.interceptor`: only attaches a key supplied through an
   explicit request context; it must not add random keys to every POST.
3. `api-error.interceptor`: parses the stable envelope and retains request ID.
4. `api-observability.interceptor`: measures safe route templates/status only;
   never logs bodies, email, tokens, notes, or financial values.

### 4.2 Session bootstrap

On application start:

1. call `GET /api/v1/users/me`;
2. on success, store the current user and entitlements;
3. route admin users to the admin shell and personal users to onboarding or the
   product shell;
4. on `401`, remain in the public auth shell;
5. on verified-user-required `403`, route to the verification page;
6. do not infer authentication from local storage.

After login, passkey login, profile change affecting locale, password change,
or an admin role/status mutation affecting the current account, refresh the
session model.

### 4.3 Command lifecycle

Every form command uses a small explicit state machine:

`idle -> validating -> submitting -> succeeded | failed | uncertain`.

- Disable only the submitted action, not unrelated navigation.
- Retain the same idempotency key across network retry.
- If the browser loses the response, refresh the authoritative read model
  before offering another submission.
- On `409`, refresh and explain the state conflict.
- On `422`, keep values and show the domain reason.
- On `429`, expose retry timing without automatic request storms.
- Reset the idempotency key after confirmed success or a deliberate new intent.

### 4.4 Cache and invalidation policy

- Current user: refresh after identity/profile/preference commands.
- Catalogue currencies: cache for the application session.
- Dashboard/month report: refresh after any posted financial command.
- Goals/reserve/loans/investments/securities: refresh that domain and the
  dashboard after a successful movement.
- Categories/rules/basic income/recurrence: refresh planning and affected
  reports.
- Admin lists: invalidate the active filter page after a mutation.
- Do not optimistically calculate a new financial total.

### 4.5 Decimal, currency, date, and locale adapters

Create:

- `ExactDecimalAdapter`: parsing/comparison/display conversion through an
  arbitrary-precision decimal implementation;
- `MoneyFormatter`: amount string + currency + locale + minor-unit metadata;
- `PercentFormatter`: exact percentage string without binary arithmetic;
- `CalendarDateAdapter`: `YYYY-MM-DD` form values without UTC date shifting;
- `InstantFormatter`: UTC instant into user-facing locale/timezone;
- `DataFreshnessPresenter`: available/stale/delayed/unavailable state;
- `RRulePresenter`: human-readable text for the supported subset only.

Never let `parseFloat`, unary `+`, or implicit numeric chart conversion become a
business calculation. A chart adapter may convert normalized display points at
the final rendering boundary while retaining the exact strings in its source
model.

### 4.6 WebAuthn adapter

Keep browser credential serialization in one `PasskeyBrowserAdapter`:

- obtain options from the generated service;
- call `navigator.credentials.create()` or `.get()`;
- serialize the credential fields expected by the backend;
- finish through the generated service;
- handle unsupported browsers and user cancellation without reporting an
  authentication failure as a server error.

## 5. Information architecture and routing

All routes below are proposed Angular routes. They do not change API paths.

### 5.1 Route shells

| Shell            | Route area                                 | Audience                        | Navigation                              |
| ---------------- | ------------------------------------------ | ------------------------------- | --------------------------------------- |
| Auth shell       | `/auth/*`                                  | signed out or verifying         | logo, language, minimal help            |
| Onboarding shell | `/onboarding/*`                            | personal user before completion | progress, back where valid, sign out    |
| Product shell    | `/app/*`                                   | free/premium personal finance   | bottom nav mobile, rail/sidebar desktop |
| Admin shell      | `/admin/*`                                 | admin only                      | separate admin sidebar and context      |
| Error shell      | `/unavailable`, `/forbidden`, `/not-found` | all                             | recovery action                         |

Never show personal-finance navigation to an admin user. Never show the admin
shell merely because a URL starts with `/admin`; the current-user entitlement
must permit it.

### 5.2 Guards and routing policy

- `signedOutOnlyGuard`: login/register pages
- `authenticatedGuard`: any protected shell
- `verifiedEmailGuard`: verified product/admin features
- `personalFinanceGuard`: `entitlements.personalFinanceAccess`
- `administrationGuard`: `entitlements.administration`
- `onboardingGuard`: follows server-returned `next`
- `capabilityGuard`: page-level capability only; server still enforces commands
- `pendingChangesGuard`: complex unsaved forms/import previews

Use lazy route loading by feature. Route titles, breadcrumbs, and analytics
names must use bounded route templates rather than entity names or IDs.

## 6. Layout system

### 6.1 Mobile-first product shell

At 320–767 px:

- top app bar: page title, optional period/context, notifications/settings
  action only where real;
- scrollable content with 16 px outer gutter and safe-area insets;
- primary bottom navigation: Home, Activity, Plan, Goals, More;
- one prominent page action through an extended FAB or bottom action, never
  several competing FABs;
- filters in a full-height bottom sheet;
- tables become cards or horizontally safe key-value rows;
- monetary totals remain visible without horizontal scrolling;
- destructive or financial confirmation uses a full-screen dialog on narrow
  devices.

“Plan” is a frontend hub linking budgets, categories, income, and schedules.
“Goals” can lead to goals with reserve, loans, and investments accessible from
More or contextual cards. This avoids an overloaded bottom bar.

### 6.2 Tablet and desktop

At 768–1199 px:

- navigation rail;
- two-column dashboard where content supports it;
- dialogs use medium max widths;
- dense tables are allowed with responsive column priority.

At 1200 px and above:

- collapsible sidebar with section labels;
- centered content max width around 1440 px;
- 12-column grid;
- summary cards may use 3–4 columns;
- detail pages may use an 8/4 main-and-aside split;
- persistent filter side panel is allowed for reports and admin lists.

Breakpoints are layout decisions, not device labels. Validate at representative
widths rather than targeting specific phones.

### 6.3 Page anatomy

Every protected page follows:

1. breadcrumb on desktop where nesting is deep;
2. title, factual subtitle, and primary action;
3. optional status/gate banner;
4. summary/insight region;
5. main task or data region;
6. contextual education/disclosure;
7. consistent loading, empty, partial, stale, error, and success states.

Keep the highest-value number and next action above the mobile fold. Do not
compress important provenance or projection labels into tooltips only.

## 7. Comprehensive page catalogue

### 7.1 Authentication and verification

| Route                     | Purpose                         | Business/interaction logic                                              | API                               |
| ------------------------- | ------------------------------- | ----------------------------------------------------------------------- | --------------------------------- |
| `/auth/login`             | Password or passkey sign-in     | generic credential errors; remember means longer server session         | sessions, passkey options/session |
| `/auth/register`          | Create account                  | exact registration fields; accepted response does not reveal duplicates | registrations                     |
| `/auth/verify-email`      | Consume token and continue      | token from URL is submitted then removed from visible URL/history       | email-verifications               |
| `/auth/verification-sent` | Explain/resend verification     | resend stays non-enumerating and throttled                              | email-verification-requests       |
| `/auth/passkey`           | Dedicated passkey flow/fallback | browser capability and cancellation states                              | passkey session operations        |

There is no password-reset completion endpoint in the public frozen contract.
Do not invent a “forgot password” journey; admin may request a secure recovery
email through its guarded workflow.

### 7.2 Onboarding and tutorial

| Route                    | Purpose                     | Business/interaction logic                           | API                       |
| ------------------------ | --------------------------- | ---------------------------------------------------- | ------------------------- |
| `/onboarding`            | Server-directed entry       | redirect to returned `next`                          | onboarding                |
| `/onboarding/theme`      | Pick persisted palette      | preview palette; display mode remains frontend-local | theme                     |
| `/onboarding/rules`      | Initialize percentage rules | atomic initial set; clearly show over-allocation     | budget-rules              |
| `/onboarding/currencies` | Select allowed currencies   | free limit and exactly one main currency             | catalogue/user currencies |
| `/onboarding/categories` | Create starter categories   | kind/color, quota, protected semantics               | categories                |
| `/onboarding/income`     | Add forecast income         | no transaction is posted                             | basic-incomes             |
| `/onboarding/tutorial`   | Product walkthrough         | completion is one-way through current API            | onboarding PATCH          |

Always use the server’s next destination after each successful step.

### 7.3 Home and journal

| Route                       | Purpose                      | Business/interaction logic                                     | API                             |
| --------------------------- | ---------------------------- | -------------------------------------------------------------- | ------------------------------- |
| `/app/home`                 | Current-month command center | posted vs forecast vs projection; budget and conversion status | current month report            |
| `/app/activity`             | Searchable journal history   | cursor/date filters; immutable records                         | journal list                    |
| `/app/activity/new`         | Post manual entry            | economic type drives form; exact positive amount               | journal create                  |
| `/app/activity/:id`         | Explain entry and legs       | source, dates, conversion, reversal links                      | list/read data already returned |
| `/app/activity/:id/correct` | Reverse and replace          | explicit audit-safe correction, same retry key                 | correction                      |
| `/app/activity/:id/reverse` | Reverse history              | impact preview from returned data; never “delete”              | reversal                        |

The dashboard headline is “net cash flow,” not “balance” or “net worth.”

### 7.4 Planning

| Route                          | Purpose                 | Business/interaction logic                         | API                        |
| ------------------------------ | ----------------------- | -------------------------------------------------- | -------------------------- |
| `/app/plan`                    | Planning hub            | budget allocation, forecast income, upcoming rules | rules, incomes, recurrence |
| `/app/plan/budget`             | Rule plans and variance | signed variance; over 100% remains visible         | budget-rules               |
| `/app/plan/categories`         | Category management     | protected/reference-aware deletion                 | categories                 |
| `/app/plan/income`             | Baseline income         | validity range; planning only                      | basic-incomes              |
| `/app/plan/schedules`          | Recurring rules         | supported RRULE subset; forecast is not posted     | recurring-rules            |
| `/app/plan/schedules/new`      | Create schedule         | explicit income/expense/transfer                   | recurring-rules            |
| `/app/plan/schedules/:id/edit` | Update schedule         | show regeneration of forecasts, not history        | recurring-rules            |

Cash-flow editing controls are available only when
`cashFlowRuleEditing=true`. Free users still see the resulting read model.

### 7.5 Reports

| Route                       | Purpose                          | Business/interaction logic                        | API          |
| --------------------------- | -------------------------------- | ------------------------------------------------- | ------------ |
| `/app/reports`              | Year index and quick comparisons | years with real report sources                    | report years |
| `/app/reports/:year`        | Annual cash-flow report          | month series and annual exact totals              | year report  |
| `/app/reports/:year/:month` | Historic month analysis          | filters in URL; totals stable over activity pages | month report |

Charts must distinguish posted from forecast, show currency, expose a tabular
alternative, and surface conversion incompleteness.

### 7.6 Goals and emergency reserve

| Route                 | Purpose                            | Business/interaction logic                           | API               |
| --------------------- | ---------------------------------- | ---------------------------------------------------- | ----------------- |
| `/app/goals`          | Active/completed/archived goals    | derived progress; quota and lifecycle                | goals             |
| `/app/goals/new`      | Create zero-balance goal           | target/currency/deadline/category/priority           | goal create       |
| `/app/goals/:id`      | Goal detail                        | exact progress, history, schedule, state             | goals read model  |
| `/app/goals/:id/edit` | Update open goal                   | target cannot go below current amount                | goal update       |
| dialogs from detail   | Contribute/correct/reverse/archive | transfers; reject overfunding; archive is not income | goal commands     |
| `/app/reserve`        | Emergency reserve                  | manual target, allocation, neutral schedule context  | emergency reserve |
| dialogs from reserve  | Target/contribute/withdraw/reverse | all movements are transfers; neutral copy            | reserve commands  |

Do not use celebratory copy that turns a financial calculation into advice.
Celebration may acknowledge completion while retaining factual amounts and
reversal consequences.

### 7.7 Loans

| Route                 | Purpose                             | Business/interaction logic                         | API                 |
| --------------------- | ----------------------------------- | -------------------------------------------------- | ------------------- |
| `/app/loans`          | Active/completed/archived overview  | outstanding principal and quota                    | loans               |
| `/app/loans/new`      | Create loan                         | exact principal/rate/fees and assumptions          | loan create         |
| `/app/loans/:id`      | Posted history plus illustration    | projection visually separate; “nominal,” never APR | loans               |
| `/app/loans/:id/edit` | Update open configuration           | never rewrite posted history                       | loan update         |
| dialogs from detail   | Payment/correction/reversal/archive | explicit components and dated FX                   | loan commands       |
| schedule panel        | Manage recurrence                   | forecast repayment only until worker posts         | loan recurring rule |

### 7.8 Generic investments

| Route                       | Purpose                            | Business/interaction logic                              | API               |
| --------------------------- | ---------------------------------- | ------------------------------------------------------- | ----------------- |
| `/app/investments`          | Savings/ETF/stock scenario records | distinct from securities portfolio                      | investments       |
| `/app/investments/new`      | Create investment bucket           | optional scenario metadata                              | create            |
| `/app/investments/:id`      | Balance, movements, scenario       | scenario is not expected/guaranteed and does not accrue | list read model   |
| `/app/investments/:id/edit` | Metadata/scenario                  | zero accepted, negative rejected, missing disables      | update            |
| dialogs from detail         | Deposit/withdraw/reverse           | internal transfers                                      | movement commands |
| schedule panel              | Contribution forecast              | transfer forecast                                       | recurring rule    |

### 7.9 Securities

| Route                             | Purpose                         | Business/interaction logic                                 | API                            |
| --------------------------------- | ------------------------------- | ---------------------------------------------------------- | ------------------------------ |
| `/app/securities`                 | Portfolio overview              | FIFO positions, allocation, quote status                   | portfolio                      |
| `/app/securities/activity`        | Trades/cash/realized result     | immutable activity                                         | activity                       |
| `/app/securities/trade`           | Buy/sell                        | market identity, quantity, fees, FX; oversell guarded      | trades                         |
| `/app/securities/cash`            | Portfolio cash transfer         | transfer, not income/expense                               | cash movements                 |
| `/app/securities/import`          | Preview then commit broker data | row errors, fingerprint, explicit commit                   | imports                        |
| `/app/securities/instruments/:id` | Instrument detail               | actual trading dates, price status, descriptive indicators | instrument/price/quote         |
| `/app/securities/watchlist`       | Watched instruments             | watch/unwatch canonical ID                                 | watchlist and instrument reads |
| dialogs from activity             | Reverse trade                   | reverse linked cash/fee and rebuild FIFO                   | trade reversal                 |
| portfolio action                  | Refresh/poll                    | job success is distinct from quote availability            | refresh job                    |
| danger zone                       | Clear portfolio                 | step-up confirmation; reverses history explicitly          | clear request                  |

If market data is disabled or unavailable, retain positions and cost information
but never present cost as market value.

### 7.10 Feedback, settings, and More

| Route                         | Purpose                         | Business/interaction logic                       | API             |
| ----------------------------- | ------------------------------- | ------------------------------------------------ | --------------- |
| `/app/more`                   | Mobile navigation hub           | modules not in bottom navigation                 | current user    |
| `/app/feedback`               | Owned feedback and responses    | bug/idea, close/reopen/delete                    | feedback        |
| `/app/feedback/new`           | Submit feedback                 | no sensitive financial data prompt               | feedback create |
| `/app/settings`               | Settings hub                    | grouped account/product/privacy                  | current user    |
| `/app/settings/profile`       | Profile and locale              | locale drives app formatting/copy                | users/me        |
| `/app/settings/security`      | Password and passkeys           | password revokes sessions; WebAuthn browser flow | identity        |
| `/app/settings/appearance`    | Palette + display mode          | palette server-synced; mode device-local         | theme           |
| `/app/settings/currencies`    | Membership/main currency        | free quota, main cannot be removed               | currency        |
| `/app/settings/categories`    | Alternate settings entry        | reuse planning page/component                    | categories      |
| `/app/settings/income`        | Alternate settings entry        | reuse planning page/component                    | basic-incomes   |
| `/app/settings/notifications` | Educational email opt-in        | transactional mail cannot be disabled            | preferences     |
| `/app/settings/privacy`       | Export/deletion and legal links | provider gate, async polling, reauthentication   | privacy         |

The privacy page must explain when export storage is not enabled without
claiming compliance or retention periods.

### 7.11 Administration

| Route                           | Purpose                              | Business/interaction logic                          | API                 |
| ------------------------------- | ------------------------------------ | --------------------------------------------------- | ------------------- |
| `/admin`                        | Admin dashboard                      | only defined counts                                 | admin dashboard     |
| `/admin/analytics`              | Defined account/registration metrics | no invented financial/user-behavior analytics       | analytics           |
| `/admin/users`                  | Filtered masked user list            | cursor, role/status/verification filters            | admin users         |
| `/admin/users/:id`              | Guarded user management              | fixed roles, status, secure recovery actions        | user detail/actions |
| `/admin/feedback`               | Cross-user workflow                  | masked author, status/severity, staff response      | admin feedback      |
| `/admin/system`                 | Non-secret system settings           | masked configured state                             | system/settings     |
| `/admin/system/integrations`    | Write-only secret setup              | never prefill/read secret                           | integrations        |
| `/admin/system/email`           | Templates/channel/test               | synthetic preview and test; production gate visible | notification admin  |
| `/admin/operations`             | Queue/provider diagnostics           | PII-safe queued/success/retry/dead-letter states    | operations queues   |
| `/admin/billing`                | Record summary                       | explicitly administrative                           | billing summary     |
| `/admin/billing/plans`          | Plan records                         | CRUD only                                           | plans               |
| `/admin/billing/plans/:id`      | Plan editor                          | no checkout/provider claims                         | plan detail         |
| `/admin/billing/promotions`     | Promotion records                    | CRUD/trial record                                   | promotions          |
| `/admin/billing/promotions/:id` | Promotion editor                     | validate boundaries                                 | promotion detail    |

Subscription, invoice, and payment actions belong within `/admin/users/:id`
tabs or dialogs because the API models them as administration of a user record.

### 7.12 No end-user page

Health/readiness, migrations, legacy migration, background worker runners,
metrics bearer access, and database operations have no public Angular page.
Queue diagnostics have an admin page only because a guarded, PII-safe admin
endpoint already exists.

## 8. Reusable design-system components

### 8.1 Application structure

- `AppShell`: responsive product shell
- `AdminShell`: visually related but unmistakably administrative
- `AuthShell`: focused signed-out layout
- `PageHeader`: title, subtitle, breadcrumbs, actions, status slot
- `Section`: heading, description, action, content
- `ResponsiveGrid`: documented card/grid patterns
- `MobileBottomNav`, `DesktopNavRail`, `DesktopSidebar`
- `MoreNavigationList`

Navigation consumes a typed route definition filtered by entitlements. It does
not hardcode role checks throughout templates.

### 8.2 Feedback and state

- `LoadingSkeleton`: shape-specific, avoids layout shift
- `EmptyState`: factual explanation and one primary action
- `ErrorState`: safe message, retry, request ID
- `PartialDataBanner`: conversion or provider incompleteness
- `FreshnessBadge`: available/delayed/stale/unavailable
- `EntitlementBanner`: limit reached or premium capability
- `FeatureGateBanner`: disabled provider/operation
- `InlineAlert`, `ToastService`, `ConfirmActionDialog`
- `AsyncButton`: prevents duplicate intent and exposes progress

Success toasts must not be used as the only confirmation of a financial
command; update the authoritative page state as well.

### 8.3 Financial display

- `MoneyValue`: exact string, currency, sign, privacy/compact modes
- `MoneyInput`: string-based validation and currency suffix
- `PercentValue` and `PercentInput`
- `SignedVariance`: never hides negative values
- `CashFlowSummaryCard`: posted summary fields only
- `PostedForecastToggle` or segmented control
- `ConversionStatus`: provider/rate/fetch provenance disclosure
- `ProjectionDisclosure`: standardized “illustration/forecast/scenario” copy
- `ProgressMeter`: exact goal progress with accessible text
- `AllocationBar`: budget or portfolio composition
- `FinancialMovementList`
- `JournalEntryCard` and `JournalLegsDisclosure`

Use tabular numerals. Color must never be the only income/expense,
positive/negative, or status signal.

### 8.4 Cards

- `MetricCard`: label, exact value, comparison/status, optional sparkline
- `AccountCard`: name, currency, derived amount, status
- `GoalCard`: progress, remaining, deadline, status
- `LoanCard`: outstanding principal, currency, lifecycle
- `InvestmentCard`: balance plus clearly separated scenario
- `InstrumentCard`: instrument identity, quote status, watch state
- `ActionCard`: onboarding/empty-state action

Cards are summaries and navigation. Do not conceal core actions behind
hover-only affordances.

### 8.5 Tables and lists

Build `DataView` as a composition, not one mega-component:

- desktop table with explicit column definitions;
- mobile card-row template;
- loading, empty, error, and partial states;
- cursor “load more” and optional infinite-scroll adapter;
- sort indicators only when the API supports that sort;
- filter summary and clear action;
- row actions through accessible menu;
- sticky headers only when they do not obscure mobile content.

Use Material table for stable structured grids. For small mobile-first lists,
semantic lists/cards are preferable to forcing a table.

### 8.6 Dialogs and forms

- `FormDialogShell`: title, disclosure, errors, actions
- `FinancialCommandDialog`: idempotency lifecycle and exact amount
- `ReversalDialog`: consequence and immutable-history language
- `CorrectionDialog`: original vs replacement
- `DangerConfirmationDialog`: typed confirmation/reauthentication where the
  backend contract requires it
- `ResponsiveDialogService`: Material dialog on desktop, full-screen or bottom
  sheet presentation on mobile
- `FormErrorSummary`: violation mapping and focus management

Forms use typed reactive forms. Avoid a generic CRUD form engine; financial
workflows have materially different rules and disclosures.

### 8.7 Charts

Provide a stable `FinanceChart` interface with:

- line, area, bar, stacked bar, and donut/allocation variants;
- exact source strings and a rendering-only numeric projection;
- currency and period metadata;
- posted/forecast visual differentiation;
- unavailable gaps instead of fabricated zero points;
- keyboard-readable legend;
- screen-reader summary;
- downloadable/visible data table;
- responsive resize and reduced-motion mode.

The concrete chart rendering package is intentionally not selected by the
backend contract. Select and record it in the Angular foundation decision after
checking Angular compatibility, accessibility, bundle cost, SSR/browser
behavior if relevant, and maintenance. Pages must depend on the adapter, not a
vendor API.

### 8.8 Directives and pipes

Directives:

- `appAutofocusInvalid`
- `appCurrencyInput` (string-preserving)
- `appPercentInput` (string-preserving)
- `appRequireCapability` (presentation only)
- `appDisableWhilePending`
- `appCopyToClipboard` for non-sensitive IDs/request IDs
- `appSensitiveReveal` only for local visual masking, never secret retrieval

Pipes/adapters:

- `money`
- `exactPercent`
- `calendarDate`
- `instant`
- `rruleLabel`
- `dataFreshness`
- `maskedIdentity`

Prefer pure formatting functions under the pipes so they can be tested without
Angular rendering.

## 9. Premium fintech visual system

“Premium” should mean disciplined hierarchy, trustworthy detail, restrained
motion, excellent typography, and consistent states—not glossy surfaces or
unexplained financial claims.

### 9.1 Semantic design tokens

Define CSS custom properties consumed by Tailwind utilities and the Angular
Material theme:

```css
--color-canvas
--color-surface
--color-surface-raised
--color-surface-muted
--color-border
--color-text
--color-text-muted
--color-primary
--color-primary-contrast
--color-accent
--color-positive
--color-negative
--color-warning
--color-info
--color-focus
--shadow-card
--shadow-overlay
--radius-control
--radius-card
--radius-dialog
```

Feature code uses semantic tokens, never raw palette shades.

### 9.2 Light and dark modes

Implement `system`, `light`, and `dark` display modes:

- default to `system`;
- react to `prefers-color-scheme` while in system mode;
- persist only this non-sensitive device preference locally;
- apply the mode before Angular paints to avoid a flash;
- backend `theme` remains the palette identifier;
- each palette provides light and dark semantic token sets.

This mode is not synchronized across devices because no backend field exists.
Do not overload the existing `theme` identifier to pretend otherwise.

### 9.3 Multiple color palettes

Map all supported backend IDs to a distinct primary/accent family:

| Backend ID        | Visual direction                    |
| ----------------- | ----------------------------------- |
| `polar-quartz`    | cool neutral with clear blue accent |
| `verdant-horizon` | emerald/teal, default               |
| `celestial-tide`  | blue/cyan                           |
| `blush-nocturne`  | rose/plum                           |
| `ember-vanguard`  | coral/amber                         |
| `lilac-eclipse`   | violet/indigo                       |
| `solaris-bloom`   | gold/green                          |
| `dune-mirage`     | sand/copper                         |

These names define direction, not final color values. During design-system
implementation, set concrete accessible tokens and test text, controls, focus,
charts, and statuses in both modes. Positive/negative colors remain semantic
and must not shift meaning with the selected palette.

### 9.4 Typography, density, and surfaces

- Use one highly legible sans-serif family with tabular-number support.
- Use a restrained display scale: page title, section title, body, label,
  caption.
- Financial hero values are prominent but never disconnected from currency,
  period, and provenance.
- Default cards use subtle borders and low-elevation shadow.
- Admin tables may use compact density; financial forms retain comfortable
  touch targets.
- Minimum interactive target is 44 × 44 CSS px.
- Use whitespace to group meaning before adding dividers.

### 9.5 Motion

- 120–200 ms transitions for surface/state changes.
- Never animate a number in a way that briefly displays a false value.
- Progress and chart entrance motion is optional and disabled by
  `prefers-reduced-motion`.
- Submission state favors clear progress over decorative animation.

## 10. Fintech UX principles

1. **Truth before delight.** Show whether data is posted, forecast, stale,
   delayed, or unavailable.
2. **One financial intent, one command.** Do not synthesize a transaction from
   multiple client calls.
3. **Review before irreversible impact.** Show amount, currency, date,
   destination/source, and semantics before posting or reversal.
4. **History is immutable.** Use correction and reversal language.
5. **Explain the number.** Offer source drill-down and calculation provenance
   returned by the API.
6. **No false advice.** Reserve, investment, and securities copy remains
   factual and neutral.
7. **Progressive disclosure.** The first view is calm; accounting legs, FX
   provenance, and assumptions remain accessible.
8. **Recovery is designed.** Network uncertainty, idempotency conflicts,
   provider gates, empty data, and partial conversions have intentional paths.
9. **Privacy by default.** Avoid financial data in URLs, logs, telemetry, and
   notification previews.
10. **Entitlements are transparent.** Explain limits without promising a
    checkout flow that does not exist.

## 11. Accessibility and localization

- Target WCAG 2.2 AA.
- All navigation and commands work with keyboard only.
- Focus moves to dialog headings and first invalid control appropriately.
- Dynamic command/status messages use deliberate live regions.
- Charts include text summaries and data tables.
- Do not encode financial sign or data freshness by color alone.
- Test 200% zoom, reflow, long names, and translated strings.
- Source all visible copy from locale files for EN/ES/HU.
- Use locale-aware display while preserving API date/decimal serialization.
- English is the application fallback, matching notification behavior.
- Passkey, provider, and privacy messages need clear non-technical versions.

The backend persists `desiredLanguage`; Angular should switch immediately after
a successful profile update. Do not infer locale solely from the browser once a
user preference exists.

## 12. Security and privacy requirements

- No auth token or session ID in local/session storage.
- No credential, passkey object, verification token, password, email, note,
  financial amount, or provider secret in frontend logs/telemetry.
- Remove verification tokens from the address bar after consumption.
- Treat generated HTML email previews as untrusted unless the response contract
  and rendering strategy prove sanitization; prefer sandboxed preview handling.
- Admin integration secrets are write-only and never prefilled.
- Destructive privacy and portfolio operations use explicit confirmation and
  reauthentication fields defined by the API.
- Frontend guards are convenience, never authorization.
- Do not cache authenticated API responses in a service worker.
- Clear in-memory user state on logout/401; the server revokes the session.

## 13. Testing strategy

### 13.1 Unit and component tests

Test:

- exact decimal and money formatting without numeric coercion;
- calendar dates without timezone drift;
- current-user/session bootstrap and guard matrices;
- entitlement navigation for free/premium/admin;
- error-envelope and violation mapping;
- idempotency-key retention across retry;
- posted/forecast/unavailable visual states;
- responsive table-to-card behavior;
- dialog focus, validation, and pending state;
- each palette in light/dark semantic token contrast checks where automatable;
- chart summary/table parity;
- WebAuthn adapter success, cancellation, and unsupported browser;
- feature facades with generated-service fakes.

Calculation-source tests should assert that dashboard, goal, reserve, loan,
investment, and securities totals render values from API DTOs rather than local
aggregation.

### 13.2 HTTP contract tests

Use generated DTO/service compile checks and an HTTP test environment to cover:

- cookie session behavior through the proxy;
- public vs authenticated vs verified vs admin operations;
- exact decimal request serialization;
- opaque cursor forwarding;
- idempotency headers on required commands only;
- `401`, `403`, `409`, `422`, `429`, and `503` UI mapping;
- provider-disabled and partial-conversion responses.

`pnpm contracts:check` is mandatory in CI.

### 13.3 Playwright critical journeys

1. Register -> verification -> login -> server-directed onboarding -> tutorial.
2. Existing user login/logout and remembered server session.
3. Passkey registration/login using browser test support or a deterministic
   WebAuthn test fixture.
4. Create income/expense/transfer -> report -> correction -> reversal.
5. Free quota denial and premium rule editing.
6. Create schedule and distinguish forecast from posted activity.
7. Goal create -> contribute -> reject overfunding -> complete -> reverse.
8. Reserve target -> contribute -> withdraw -> reverse.
9. Loan create -> inspect illustration -> pay -> correct/reverse -> archive.
10. Investment create -> movement -> scenario disclosure.
11. Securities import preview/commit, oversell denial, unavailable quote,
    refresh polling, and reversal.
12. Feedback lifecycle and admin response.
13. Admin user/status/role/recovery workflow with masked PII.
14. Admin settings/integration write-only behavior and email production gate.
15. Privacy export disabled/enabled status and deletion confirmation.
16. Session expiry during a form and safe recovery without duplicate posting.

Run critical journeys at a narrow mobile viewport and a desktop viewport.
Include keyboard-only and basic automated accessibility checks.

### 13.4 Visual and theme regression

Capture stable synthetic states for:

- auth, onboarding, dashboard, report, domain detail, settings, and admin table;
- light/dark;
- default palette plus a rotating palette coverage matrix;
- loading, empty, error, stale, over-allocated, limit-reached, and dialog states.

Do not snapshot real names, emails, tokens, or financial records.

## 14. Implementation sequence

### Phase 00 — Angular execution contract

Deliver:

- Angular-specific `AGENTS.md` and step plan before feature implementation;
- approved route map and responsive navigation;
- chart-renderer decision record;
- exact-decimal frontend package decision;
- browser support/accessibility targets;
- same-origin development/deployment contract;
- explicit backend owner-acceptance record or documented pending status.
- generated-response schema usability audit and correction ownership for every
  frontend-blocking `void` or empty-object response.

No feature business behavior is implemented in this phase.

### Phase 01 — Workspace and design-system foundation

- create `apps/web-app` and `apps/web-app-e2e`;
- install/configure matching Angular Material and TailwindCSS;
- establish standalone bootstrap, environments, API proxy, lint/type/build/test;
- implement semantic tokens, default palette, light/dark/system mode;
- create Storybook-equivalent isolated component harness only if deliberately
  approved; it is not required by this plan.

### Phase 02 — API core and session

- wire generated client;
- interceptors, error model, exact decimal/date adapters;
- current-user store, session bootstrap, route guards;
- auth shell and error routes;
- contract-focused tests.

### Phase 03 — Identity and onboarding

- registration/login/verification/passkeys/password;
- server-directed onboarding and tutorial;
- profile/locale/theme foundation;
- mobile and Playwright auth journeys.

### Phase 04 — Product shell and dashboard

- responsive navigation and More hub;
- dashboard from current-month reporting;
- financial display components, chart adapter, filters, freshness states;
- no journal mutations yet beyond navigation placeholders.

### Phase 05 — Journal and reports

- activity list, detail, create, correction, reversal;
- year/month reports, cursor pagination, URL filters;
- idempotency and uncertain-result handling;
- reconciliation-oriented tests.

### Phase 06 — Planning

- categories, basic income, percentage rules, assignments;
- recurrence CRUD and forecast presentation;
- entitlement/quota UI and over-allocation states.

### Phase 07 — Goals and reserve

- goal CRUD/lifecycle/contributions/corrections/schedules;
- emergency target/movements/reversals;
- neutral educational copy and progress visualization.

### Phase 08 — Loans and generic investments

- loan pages, payment history, schedule, annuity disclosure;
- generic-investment balances, movement, recurrence, scenario disclosure;
- exact-value and projection separation tests.

### Phase 09 — Securities

- portfolio/activity/trade/cash/watchlist/instrument;
- import preview/commit;
- refresh polling, quote status, FIFO/reversal UX, clear workflow;
- provider-disabled behavior.

### Phase 10 — Feedback, settings, notifications, and privacy

- feedback;
- consolidated settings;
- educational email preference;
- privacy export polling and deletion reauthentication;
- provider-gate and privacy tests.

### Phase 11 — Administration

- separate shell and guard;
- dashboard/analytics/users/feedback;
- system/integrations/email/operations;
- administrative billing records;
- masked/write-only/PII-safe behavior tests.

### Phase 12 — Hardening and Angular freeze

- full component, HTTP, Playwright, responsive, accessibility, and visual suites;
- bundle analysis and lazy-route verification;
- error/session expiry/network interruption checks;
- EN/ES/HU content review;
- all palettes/modes;
- OpenAPI/generated-client drift;
- production same-origin and security-header validation;
- frontend completion report and owner acceptance.

## 15. Per-phase verification

At minimum, every implementation phase runs:

```bash
pnpm format:check
pnpm lint
pnpm typecheck
pnpm test
pnpm build
pnpm contracts:check
```

Run affected Playwright projects for every phase and the complete suite at the
freeze. Backend integration and Postman acceptance should remain part of the
release pipeline so frontend work cannot silently drift the frozen baseline.

## 16. Decisions that must not be silently made later

Record an explicit Angular decision before implementation if changing:

- the same-origin cookie deployment;
- the generated-client generator or ownership;
- the exact-decimal library;
- the chart rendering implementation;
- display-mode persistence or server synchronization;
- supported locales or theme identifiers;
- route hierarchy affecting deep links;
- a Material/Tailwind ownership rule;
- analytics or error tracking payloads;
- any backend contract, role, entitlement, financial formula, or provider gate.

Changing one of these is not ordinary component styling. It needs impact review
and the relevant contract/tests updated.

## 17. First implementation checkpoint

Before building feature pages, prove this thin vertical slice:

1. Angular starts through Nx with the `/api` proxy.
2. `GET /users/me` establishes signed-out or signed-in state using the cookie.
3. Authenticated routing selects product or admin shell from entitlements.
4. The current-month dashboard renders synthetic backend values as exact
   strings with currency, period, and posted/forecast distinction.
5. A narrow mobile viewport and desktop layout pass component and Playwright
   checks.
6. Switching palette and display mode does not change financial/status meaning.
7. `pnpm contracts:check` and the existing backend verification remain green.

This slice validates the riskiest integration boundaries before duplicating
page patterns across every domain.
