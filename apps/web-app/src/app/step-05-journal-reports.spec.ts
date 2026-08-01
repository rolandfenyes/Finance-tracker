// Step 05 features are lazy source libraries. Import focused tests into the app compilation
// without packaging the features or duplicating test cases.
/* eslint-disable @nx/enforce-module-boundaries -- Test-only imports execute lazy feature specs. */
export {};

await import('../../../../libs/web/feature-transactions/src/lib/journal.facade.spec');
await import('../../../../libs/web/feature-reports/src/lib/reports.facade.spec');
