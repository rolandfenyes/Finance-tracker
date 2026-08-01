// Feature libraries are lazy source libraries; this suite keeps their focused specs in the app's
// Angular test compilation without packaging either feature or duplicating any test case.
/* eslint-disable @nx/enforce-module-boundaries -- Test-only imports execute lazy feature specs. */
export {};

await import('../../../../libs/web/feature-auth/src/lib/identity.facade.spec');
await import('../../../../libs/web/feature-auth/src/lib/passkey-browser.adapter.spec');
await import('../../../../libs/web/feature-auth/src/lib/security.facade.spec');
await import('../../../../libs/web/feature-onboarding/src/lib/onboarding.facade.spec');
await import('../../../../libs/web/feature-onboarding/src/lib/onboarding-pages.spec');
