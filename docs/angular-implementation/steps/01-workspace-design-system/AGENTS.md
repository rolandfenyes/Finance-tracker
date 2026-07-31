# Step 01 — Angular Workspace and Design-System Foundation

## Objective

Create a runnable Angular application and Playwright project in the existing Nx
workspace, then implement the approved mobile-first design-system foundation
without starting feature behavior.

## Dependencies

- Step 00 is complete.
- All Step 00 decisions are approved.
- Backend contract artifacts remain current.

Stop if any required Step 00 package or topology decision is unresolved.

## Required evidence

Read Step 00 decisions, design-system contract, route map, and test strategy;
workspace package/Nx/TypeScript/ESLint/Prettier configuration; generated-client
project configuration; and current CI workflows.

## Required deliverables

- `apps/web-app` with standalone Angular bootstrap.
- `apps/web-app-e2e` with the approved Playwright configuration.
- `libs/web/design-system` and `libs/web/shared` with explicit public APIs.
- Matching Angular Material and TailwindCSS setup.
- Nx targets for serve, build, lint, typecheck, component tests, and Playwright.
- Development proxy for same-origin `/api` access.
- Environment/config abstraction without credentials.
- Semantic CSS tokens consumed by both Material theming and Tailwind utilities.
- `system`, `light`, and `dark` mode handling with pre-paint initialization.
- All eight approved palette identifiers with light/dark token sets.
- Base typography, focus, spacing, radius, elevation, density, reduced-motion,
  safe-area, and responsive breakpoint rules.
- Minimal application, auth, onboarding, product, admin, and error shell
  placeholders sufficient to demonstrate responsive layout only.
- Shared loading, empty, error, inline-alert, async-button, page-header, and
  section primitives.
- Synthetic component harness/pages used only for design-system verification.

Do not create speculative feature libraries. Do not implement API calls,
session logic, feature routes, or domain-specific cards.

## Material and Tailwind boundary

- Use Material for overlays, focus management, form/control primitives, menus,
  tooltips, snack bars, and accessible table primitives.
- Use Tailwind for layout, responsive variants, spacing, sizing, typography
  composition, and simple surfaces.
- Do not use private Material DOM selectors.
- Do not hardcode raw palette shades in feature-facing APIs.
- Positive, negative, warning, information, and focus colors retain the same
  meaning across palettes.

## Tests

- application bootstrap and shell smoke tests;
- semantic-token and palette registration tests;
- system/light/dark persistence and media-query tests;
- no-flash theme bootstrap test where practical;
- keyboard focus and reduced-motion tests;
- automated contrast checks for critical token pairs;
- responsive shell tests at 320 px, tablet, and desktop;
- Playwright smoke test through the development proxy;
- Nx boundary/public-API checks.

## Acceptance criteria

- `web-app` builds and serves through Nx.
- Playwright launches the application.
- Material and Tailwind coexist under the approved ownership contract.
- Every palette works in light and dark mode without changing status meaning.
- Base shells reflow without horizontal page scrolling at 320 px.
- No credentials, backend mutations, or feature implementation were added.
- Step 02 was not started.
