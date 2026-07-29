# Backend Implementation — Shared Agent Contract

These instructions apply to every step in this directory.

## Required behavior

1. Read `../MYMONEYMAP-COMPLETE-PROJECT-DOCUMENTATION.md`, `../INCORRECT-FACTS-AND-LOGIC-AUDIT.md`, and `IMPLEMENTATION-PLAN.md` before implementing a step.
2. Read the PHP source and migrations named by the step. Treat them as evidence, not automatically correct requirements.
3. Follow the source-of-truth order in the master plan.
4. If behavior is unresolved, stop and record one precise decision request. Do not invent a rule.
5. Preserve existing behavior unless an audited correction or approved decision changes it.
6. Never copy secret values, the default-admin seed, insecure fallback credentials, or production data into source/tests/docs.

## Architecture constraints

- NestJS modular monolith and PostgreSQL.
- No microservices, GraphQL, event-sourcing framework, or unrelated abstraction.
- No cross-module table queries outside an approved repository/application-service boundary.
- No JavaScript `number` for money, FX rates, security quantities, interest, or calculated financial output.
- Reads must not silently mutate financial data.
- Background work must be queued, idempotent, observable, and retry-safe.
- API authentication uses secure server-managed sessions.

## Test and delivery constraints

- Use migrations; never use ORM schema synchronization in production.
- Required integration tests must fail—not skip/pass—when dependencies are unavailable.
- Test happy path, validation, authorization, cross-user isolation, rollback, retry, and relevant numeric/date edges.
- Add OpenAPI changes and request/response examples.
- Do not modify unrelated modules or the legacy PHP runtime unless a separately approved migration task requires a read-only compatibility artifact.
- End each step with evidence: commands run, test results, migrations added, API diff, remaining risks, and explicit acceptance checklist.

