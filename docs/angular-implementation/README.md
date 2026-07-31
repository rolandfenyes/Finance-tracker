# MyMoneyMap Angular Handoff

This directory is the implementation handoff from the completed NestJS backend
to the Angular application.

## Documents

- [NestJS API and business logic](./NESTJS-API-AND-BUSINESS-LOGIC.md) explains
  the frozen HTTP contract, authentication model, business invariants, endpoint
  catalogue, and production feature gates.
- [Angular implementation plan](./ANGULAR-IMPLEMENTATION-PLAN.md) defines the
  proposed Angular architecture, routes, pages, layouts, reusable components,
  theme system, mobile-first UX, testing, and delivery sequence.
- [Runnable step plan](./IMPLEMENTATION-PLAN.md) defines dependencies and
  completion gates for the step-by-step implementation.
- [Shared agent contract](./AGENTS.md) applies to every runnable Angular step.
- [Generic step prompt](./RUN-STEP-PROMPT.md) can be reused by replacing one
  directory placeholder.

## Authoritative machine-readable contract

- OpenAPI: `apps/api/openapi/openapi.json`
- Generated Angular client: `libs/generated/api-client/src`
- Contract drift check: `pnpm contracts:check`
- Postman collection: `postman/MyMoneyMap-Backend-v1.postman_collection.json`

The Markdown documents explain the contract; they do not replace it. If a
request or response type differs from these documents, regenerate from OpenAPI
and resolve the discrepancy before changing frontend behavior.

The runnable Step 00 includes a mandatory generated-schema usability audit.
Known notification, securities, administration, and billing response
typing gaps must be corrected at the backend/OpenAPI boundary before their
dependent Angular feature steps.
