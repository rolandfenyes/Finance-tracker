# Performance and security verification contract

## Synthetic load profile

Use synthetic users only. Warm the service, then run the authenticated read profile for at least 10 minutes with concurrency capped at 25. It covers activity, reports, schedules, portfolio, admin listing, and export status. Results pass when read p95 is at most 500 ms, application errors are below 1%, and no database statement exceeds the configured 10-second hard timeout. Mutation concurrency, rollback, idempotency, exact-decimal, and authorization behavior remain covered by the PostgreSQL integration suites rather than by a state-changing load harness.

The committed read-path runner covers all six named read domains at an arrival rate below the shared API rate budget:

```sh
LOAD_TEST_USER_COOKIE='mymoneymap.sid=synthetic-session' \
LOAD_TEST_ADMIN_COOKIE='mymoneymap.sid=synthetic-admin-session' \
LOAD_TEST_EXPORT_ID='synthetic-owned-export-uuid' \
pnpm load:backend-reads
```

It never prints cookies, identifiers, response bodies, or financial data. Environment settings control duration, arrival rate, concurrency, p95, and error budget. The default is 600 seconds, 4 requests/second, concurrency 25, read p95 500 ms, and error budget 1%.

Before production, repeat from the intended region against staging with production-sized synthetic cardinalities. Record commit, image digest, database plan, data counts, concurrency, percentiles, errors, pool saturation, CPU/memory, and queue age. A faster local run is supporting evidence, not a substitute.

## Query-plan contract

Plans are reviewed for:

- activity: owner/time/id ledger index;
- reports: owner/date indexes and approved reporting read models;
- schedules: owner and due/status indexes;
- portfolio: owner/account/instrument indexes;
- admin: bounded cursor queries, not unbounded scans;
- exports: owner/status and worker status indexes.

`EXPLAIN (ANALYZE, BUFFERS)` must use synthetic fixtures and run outside production. A sequential scan is acceptable only for a demonstrably small bounded relation; otherwise add an evidence-backed index and re-run migration rollback/reapply and drift.

## Security gates

Every pull request runs formatting, lint, type checking, unit/integration tests, migration/OpenAPI drift, production dependency audit, Gitleaks, CodeQL, deterministic application SBOM drift, a non-root backend image build, Trivy high/critical image scan, and a container SBOM. Required PostgreSQL/Redis tests fail when dependencies are unavailable.

Release blockers:

- any known high/critical exploitable finding without a recorded, time-bounded exception;
- secrets or production data in source, fixtures, artifacts, logs, or SBOM metadata;
- cross-user isolation or authorization failure;
- schema/OpenAPI/SBOM drift;
- provider or private-storage production gate enabled without evidence;
- missed RPO/RTO, dead-letter invisibility, or failed restore/rollback rehearsal.
