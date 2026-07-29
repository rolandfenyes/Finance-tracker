# Step 21 — Production Hardening, QA, and Operations

## Objective

Prove the backend can be operated safely before Postman contract freeze.

## Required work

- threat model and remediation for identity, tenant isolation, imports, webhooks, providers, admin, exports, and deletion;
- CSP/HTTP header responsibility documented between API and edge;
- rate limits, request/body limits, timeouts, circuit breakers, redaction, and audit review;
- query-plan/load tests for activity, reports, schedules, portfolio, admin, and export;
- queue retry/dead-letter dashboards and alert thresholds;
- health/readiness, metrics, traces, structured logs, and error tracking;
- encrypted backup/PITR, restore drill, RPO/RTO decisions, deployment rollback and incident runbooks;
- dependency/SAST/secret/container scanning and SBOM;
- independent security review handoff.

## Corrections

Required tests may not skip unavailable DB/Redis. No local filesystem cache or session state may be required for horizontal scaling.

## Acceptance

Approved performance/security budgets pass; restore and rollback are rehearsed; provider/queue/database failure modes are visible and recoverable; unresolved legal/provider claims are documented rather than asserted.

