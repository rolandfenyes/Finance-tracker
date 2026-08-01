// Step 04 dashboard is a lazy source library. Import its focused tests into the app compilation
// without packaging the feature or duplicating any test cases.
/* eslint-disable @nx/enforce-module-boundaries -- Test-only import executes the lazy feature spec. */
export {};

await import('../../../../libs/web/feature-dashboard/src/lib/dashboard.facade.spec');
