# Backend operations runbook

## Service objectives and budgets

These are initial engineering targets for synthetic/staging verification, not a promise of third-party uptime:

- API read p95: at most 500 ms under the documented synthetic load profile.
- Error rate caused by the application: below 1%; authorization/validation responses do not count as service errors.
- Queue oldest pending: alert after 300 seconds. Backlog: alert above 100 waiting/prioritized jobs. Any permanently failed job alerts.
- Recovery point objective: 5 minutes. Recovery time objective: 60 minutes.

The hosting choice must demonstrate that encrypted backup and PITR settings meet the RPO before production. If it cannot, choose a compliant PostgreSQL operator; do not revise the target silently.

## Signals

- `GET /api/v1/health/live` proves the process is responsive.
- `GET /api/v1/health/ready` fails unless PostgreSQL and Redis are usable.
- `GET /api/v1/internal/metrics` is excluded from OpenAPI and hidden behind `OPERATIONS_METRICS_ENABLED` plus the independent `x-operations-token`.
- `GET /api/v1/admin/operations/queues` exposes only queue names, counts, ages, alert codes, and provider circuit state. It requires authenticated, verified admin access.
- Prometheus metrics use bounded route names and status classes. They never label by user, email, resource ID, financial value, query string, or job payload.
- Sentry is off by default. Production refuses to start unless its explicit approval gate is true. Request content, URL/query, headers/cookies, user, contexts, extra data, and breadcrumb messages are removed before transmission.
- Pino logs retain request ID, method, status, error type, and safe event codes; common identity, token, template, note, and financial-payload paths are redacted.

Recommended alerts: readiness failing for two minutes; 5xx over 1% for five minutes; p95 over budget for ten minutes; database pool exhaustion; Redis disconnect; any dead-letter; queue age/backlog thresholds; provider circuit open for five minutes; backup/PITR check stale for 24 hours.

## Queue recovery

1. Inspect the protected queue snapshot and correlate by request/business-event ID; never paste payloads into tickets.
2. Resolve the dependency failure. A provider circuit opening is a symptom, not a reason to bypass the circuit.
3. Confirm the durable record and idempotency key before retrying. All workers have at most three bounded attempts with exponential backoff.
4. Replay only dead-letter jobs whose authoritative record still permits work. Never clone a job with a new business key to force delivery.
5. Confirm counts, oldest age, and application state converge. Record the safe event code and timestamps, not payload/PII.

## Backup and restore

Production requirements: encrypted storage, PITR with a recovery window satisfying the five-minute RPO, separate credentials, access audit logs, and a documented restore target. Provider screenshots/config export and a timed restore are release evidence.

The repository rehearsal is restricted to localhost. It accepts a database whose name contains `test`; another confirmed synthetic local database also requires the explicit `RESTORE_REHEARSAL_APPROVED=true` safety flag:

```sh
DATABASE_URL=postgresql://.../mymoneymap_test pnpm operations:restore-rehearsal
RESTORE_REHEARSAL_APPROVED=true DATABASE_URL=postgresql://.../synthetic_local pnpm operations:restore-rehearsal
```

It creates a random isolated database, performs `pg_dump`/`pg_restore`, verifies the application schema exists, reports elapsed time without identifiers, drops only that generated database, and removes the temporary dump. It does not claim to test provider encryption or PITR.

Quarterly production drill: select an approved recovery point, restore into an isolated private network, run schema fingerprint/drift and synthetic integrity checks, record RPO/RTO, then destroy the isolated restore under the provider’s retention process.

## Deployment and rollback

1. Back up/PITR checkpoint and record current artifact digest, schema fingerprint, and environment gate values without secrets.
2. Run migration status, apply migrations, run drift, readiness, OpenAPI, and smoke checks.
3. Deploy the immutable image by digest. Watch error, latency, database, Redis, circuit, and queue signals.
4. Roll application code back to the last digest if runtime behavior regresses.
5. Roll a migration back only after its checked `down` path has been rehearsed and no newer code/data depends on it. Prefer a forward corrective migration for destructive/data-bearing production changes.
6. If correctness is uncertain, stop writes at the edge while keeping evidence and health access private; do not disable constraints or idempotency.

## Incident response

Declare an incident for suspected cross-user access, credential/token disclosure, incorrect financial state, unrecoverable/dead-letter growth, data loss, or sustained objective breach. Assign incident lead, operations lead, and recorder. Contain access, rotate affected credentials, preserve immutable audit/log evidence, determine affected users and periods, restore or forward-correct from the authoritative source, and complete legal/user notification assessment. Never place raw exports, email addresses, tokens, provider bodies, or financial values in chat, logs, or tickets.
