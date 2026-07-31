# Design System Contract

Status: **approved by Decision Set A on 2026-07-31**

The design system expresses a premium fintech interface without changing
financial meaning. It is mobile-first from 320 CSS pixels, WCAG 2.2 AA, and
shared by product and admin shells.

## Ownership boundary

| Concern                                                                                                                      | Owner                                       |
| ---------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| Buttons, form fields, checkboxes, menus, tabs, tooltips, dialogs, bottom sheets, snackbars, date selection, focus management | Angular Material/CDK                        |
| Grid/flex layout, spacing, sizing, responsive composition, visibility utilities, simple surfaces                             | Tailwind CSS                                |
| Color, type, spacing, radius, elevation, density, motion, focus, chart semantics                                             | CSS custom properties                       |
| Business-aware cards, tables, charts, state views, money/date presentation                                                   | `libs/web/design-system` project components |
| Feature composition and copy                                                                                                 | owning lazy feature                         |

Tailwind classes must consume semantic variables where color conveys meaning.
Material theming maps to the same variables. Neither system owns independent
hardcoded palettes.

## Semantic token layers

### Primitive scale

- spacing: `--space-0`, `--space-1` (4px), `--space-2` (8px),
  `--space-3` (12px), `--space-4` (16px), `--space-5` (20px),
  `--space-6` (24px), `--space-8` (32px), `--space-10` (40px),
  `--space-12` (48px);
- radius: `--radius-sm` 8px, `--radius-md` 12px, `--radius-lg` 16px,
  `--radius-xl` 24px, `--radius-full`;
- elevation: `--elevation-0` through `--elevation-3`, expressed with borders
  plus restrained shadows;
- typography: `--font-sans`, `--font-mono`, `--text-xs` through
  `--text-4xl`, `--weight-regular`, `--weight-medium`, `--weight-semibold`;
- focus: `--focus-width` 3px and `--focus-offset` 2px.

### Semantic color roles

Every display mode and palette defines:

- surfaces: `--surface-canvas`, `--surface-subtle`, `--surface-card`,
  `--surface-raised`, `--surface-inverse`;
- text: `--text-primary`, `--text-secondary`, `--text-muted`,
  `--text-on-accent`, `--text-inverse`;
- borders: `--border-subtle`, `--border-strong`, `--divider`;
- brand: `--accent`, `--accent-hover`, `--accent-active`,
  `--accent-container`, `--on-accent-container`;
- status: success, warning, danger, info, neutral, each with foreground,
  container, and border tokens;
- finance: positive, negative, zero, forecast, projection, scenario, stale,
  delayed, unavailable;
- chart series: eight distinguishable series tokens plus grid, axis, tooltip,
  and selection.

Positive/negative color is accompanied by sign, label, icon, or pattern.
Forecast/projection/scenario states have explicit text and non-color
differentiation.

## Modes and palettes

- Modes: `system`, `light`, `dark`.
- Palettes: `blue`, `green`, `purple`, `orange`, `teal`, `indigo`, `pink`,
  `red`.
- Mode changes surface/text/status values; palette changes accent values.
- All 16 light/dark palette combinations must meet WCAG AA for normal text and
  controls.
- High-risk actions always use danger semantics, never the selected accent.
- The device-local display mode is applied before first paint. Account palette
  comes from the theme API and may initially render with the safe default blue
  until session resolution.

## Density and sizing

- Default density is comfortable.
- Interactive targets are at least 44x44 CSS pixels; compact density may be
  used only for desktop data grids while preserving a 40px minimum row/control
  target and keyboard operability.
- Form fields stack at 320px. Two-column forms start only when each field can
  retain readable labels/errors.
- Money uses tabular numerals. Raw decimal strings are formatted by approved
  pipes/adapters without changing the authoritative value.

## Breakpoints

| Name  | Minimum | Contract                                                                                                   |
| ----- | ------: | ---------------------------------------------------------------------------------------------------------- |
| `xs`  |       0 | 320px minimum, single column, bottom navigation, full-width dialogs become bottom sheets where appropriate |
| `sm`  |   480px | Wider phone composition; no desktop-only assumptions                                                       |
| `md`  |   768px | Tablet, optional two-column content, navigation rail                                                       |
| `lg`  |  1024px | Desktop shell and persistent side navigation                                                               |
| `xl`  |  1280px | Constrained multi-column dashboards                                                                        |
| `2xl` |  1536px | Maximum content width; whitespace grows, data density does not become illegible                            |

Content must reflow at 320px without horizontal page scrolling. Tables use
responsive rows/cards or an explicitly labelled, keyboard-scrollable region.

## Layout contracts

- `AppShell`: top bar, skip link, main landmark, responsive primary navigation,
  announcement/live region.
- `AdminShell`: visibly distinct operational shell and navigation; no personal
  finance content mixing.
- `PageHeader`: title, optional factual subtitle, primary action, breadcrumbs
  only when useful.
- `PageGrid`: one-column mobile, explicit responsive spans.
- `DataView`: owns loading, empty, error, success, partial, disabled/gated, and
  retry composition.
- Destructive confirmation states action, affected record, irreversibility or
  reversal semantics, and requires an explicit labelled confirmation.

## Reusable component contracts

| Component                             | Required behavior                                                                                     |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `AppDialog` / `ConfirmDialog`         | Material overlay, labelled title/description, focus trap/return, Escape except while unsafe to cancel |
| `BottomSheetActionMenu`               | Mobile action equivalent to accessible desktop menu                                                   |
| `MetricCard`                          | label, exact formatted value, currency/unit, provenance/state, optional trend from API only           |
| `CashFlowSummaryCard`                 | backend totals and signed variance; never recomputes                                                  |
| `EntityCard`                          | stable mobile representation of table row actions                                                     |
| `DataTable`                           | caption, sortable headers only when supported, keyboard actions, responsive fallback                  |
| `ChartPanel`                          | title, time/currency/source context, ECharts adapter, textual/table equivalent                        |
| `MoneyText` / `SignedVariance`        | locale display from decimal string; sign and status not color-only                                    |
| `StatusBadge`                         | icon/text/color with normalized semantic status                                                       |
| `ConversionStatus`                    | converted/unavailable/stale/delayed provenance without client FX                                      |
| `PartialDataBanner`                   | names missing slices and retains available authoritative data                                         |
| `ProviderGate`                        | factual disabled/delayed state without unsupported provider claim                                     |
| `EntitlementGate`                     | current server entitlement/quota, no client tier inference                                            |
| `DateRangeField`                      | calendar-date semantics, localized label/error, timezone-safe                                         |
| `IdempotentSubmitDirective`           | disables duplicate UI action and retains one key per intent retry only where declared                 |
| `AutofocusErrorDirective`             | moves focus to error summary/first invalid control after failed submit                                |
| `ExactMoneyPipe` / `ExactPercentPipe` | decimal-string input only; display formatting only                                                    |
| `StateAnnouncement`                   | polite/assertive live announcements without PII/financial payload logging                             |

## Forms and feedback

- Reactive typed forms.
- Labels remain visible; placeholders are examples, not labels.
- Inline errors are associated with controls and summarized after submit.
- Pending submits expose progress and remain idempotent.
- `409`, `422`, `429`, provider-disabled, and generic failure get distinct
  localized states.
- Snackbars are supplementary; durable outcomes remain in page content.
- Empty states describe what the implemented feature can do without marketing
  or connected-provider claims.

## Motion

- Standard transitions: 120–200ms; complex route/sheet motion at most 300ms.
- Animate opacity/transform, not layout-heavy properties.
- Honor `prefers-reduced-motion`; non-essential animation becomes instant.
- No animated financial number counting, autoplay carousel, or flashing status.
- Loading indicators remain understandable without animation.

## Accessibility gates

- WCAG 2.2 AA contrast, reflow, focus visibility, target size, and status
  identification.
- One `h1`, ordered headings, landmark names, skip link, descriptive document
  title.
- Keyboard access to all actions; no hover-only disclosure.
- Dialogs, menus, tabs, and tables use Material/CDK semantics/harnesses.
- Charts have textual summaries and accessible data alternatives.
- Locale changes update the document language.
- Theme/palette selection is announced and visible beyond color.
- Automated axe checks complement, not replace, keyboard and screen-reader
  review.

## Performance constraints

- Route-level lazy loading for every feature boundary.
- Tree-shaken ECharts modules; charts load only on routes that use them.
- No remote font/icon blocking requests.
- Virtualization/pagination follows API semantics; do not load all cursor pages.
- Skeletons reserve final layout space to limit cumulative layout shift.
