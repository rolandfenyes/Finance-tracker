# Independent security review handoff

The reviewer must be independent of the Step 21 implementation. Review the current commit and immutable image digest, not an unrecorded working tree.

## Scope

- identity, recovery, passkeys, session fixation/expiry, cookie and proxy configuration;
- every user-owned and admin endpoint for broken object-level/function-level authorization;
- exact-decimal and PostgreSQL financial invariants;
- legacy import source isolation, validation, transactions, reconciliation, and cutover gates;
- privacy export object authorization/storage/expiry and deletion completeness;
- BullMQ idempotency, attempt bounds, dead letters, replay, and payload exposure;
- provider allow-listing, credential handling, timeouts, Redis circuit state, and production gates;
- HTTP headers, CSP division, body/rate/time limits, error shapes, logging, metrics, and Sentry scrub;
- migrations, database roles/TLS, backup/PITR, restore, deployment rollback, dependencies, image, and CI supply chain.

## Evidence bundle

Provide the implementation plan and ADRs, this step’s threat model/runbook/budgets, API OpenAPI artifact, schema fingerprint, migration rollback/reapply output, unit/integration/load output, SBOMs, dependency/Gitleaks/CodeQL/Trivy results, local restore output, provider configuration evidence, and a synthetic queue failure/recovery trace.

## Required output

Findings include severity, affected boundary, reproducible synthetic evidence, impact, recommended remediation, and whether launch is blocked. The product owner records acceptance or remediation. Review does not approve unverified provider rights, regional claims, encryption, PITR, retention, or legal terms.

Open external evidence still required before production: hosting region/private network/encrypted backup/PITR/DPA; Sentry region/retention/DPA; transactional-email delivery approval (including Nethely SMTP sender, TLS, bounce/suppression operations, and retention evidence); Finnhub delay/coverage/quota/redistribution rights; private export storage policy/lifecycle. Their feature gates remain false until that evidence is recorded.
