# Step 01 — Workspace and NestJS Foundation

## Objective

Create the side-by-side TypeScript workspace and a minimal production-shaped NestJS API without implementing finance domains.

## Deliverables

- Approved workspace structure with `apps/api` and generated-client location.
- Locked Node/package-manager versions and reproducible install.
- NestJS bootstrap, `/api/v1`, OpenAPI generation, configuration validation, request IDs, structured redacted logging, global validation, exception mapping, health/readiness skeleton, and graceful shutdown.
- Development/test/production configuration contract with no secret fallback.
- CI jobs for formatting, linting, type checking, unit tests, PostgreSQL integration tests, build, OpenAPI drift, and secret/dependency scanning.
- Local PostgreSQL/Redis development dependencies using the approved approach.

## Do not implement

Users, authentication, database domain tables, queues, finance endpoints, or vendor integrations.

## Acceptance

The API starts with validated configuration, exposes only health/OpenAPI endpoints, fails safely on invalid production configuration, builds reproducibly, and CI fails if required PostgreSQL/Redis test dependencies are unavailable.

