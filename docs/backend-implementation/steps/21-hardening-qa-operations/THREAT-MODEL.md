# Step 21 threat model and control review

Status: implementation evidence for the pre-production security review. This document does not approve a hosting region, processor, or provider contract.

## Assets and trust boundaries

The protected assets are credentials and sessions, per-user ledger and planning data, exact financial values, exports, audit records, provider credentials, and background-job payloads. The public API/edge, NestJS process, PostgreSQL, Redis/BullMQ, private object storage, and outbound providers are separate trust boundaries. PostgreSQL is the durable authority; Redis is coordination and queue state, not a financial source of truth.

## Threats and implemented controls

| Area                  | Principal threats                                                          | Repository-owned controls                                                                                                                                                                        | Required deployment evidence                                                       |
| --------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------- |
| Identity              | credential stuffing, session theft, reset-token disclosure, passkey replay | Argon2id, opaque hashed tokens, Redis-backed login and global throttles, secure production cookie validation, verified-email/admin guards, bounded body and HTTP timeouts, Pino/Sentry redaction | TLS termination and trusted-proxy value reviewed                                   |
| Tenant isolation      | horizontal privilege escalation, cross-user joins, ID enumeration          | ownership predicates and composite owner foreign keys, cross-user isolation tests, PostgreSQL constraints; admin routes use explicit admin guard                                                 | independent authorization test review                                              |
| Imports and migration | malicious values, partial writes, source mutation                          | Step 20 read-only source, synthetic rehearsal/cutover gates, exact decimals, reject ledger history, transaction rollback and reconciliation                                                      | source credentials proven read-only before cutover                                 |
| Webhooks              | forged or replayed payment events                                          | no payment webhook exists in the approved v1 scope; billing is manual entitlement administration                                                                                                 | add a new threat review before any webhook is introduced                           |
| Providers             | SSRF, long hangs, quota storms, leaked tokens, inconsistent instance state | allow-listed configured bases, bounded timeouts, Redis circuit breakers, queue retries capped at three, provider credentials only in environment, provider response bodies excluded from logs    | Finnhub rights/coverage/quota approval; Postmark approval; outbound network policy |
| Admin                 | privilege escalation, bulk PII disclosure, unaudited changes               | authentication + verified-email + admin guards, lower admin rate budget, immutable privileged audit events, PII-safe queue diagnostics                                                           | periodic admin-role review and alert routing                                       |
| Exports               | insecure direct object access, public artifacts, overlong retention        | owner checks, private-storage-only configuration, short signed-access contract, immutable audit trail, exact expiry validation                                                                   | S3 bucket policy, encryption, lifecycle, and access logging inspected              |
| Deletion              | incomplete erase, audit tampering, retry duplication                       | durable idempotent queue, bounded retries/dead letter, deletion manifest, anonymized retained audit linkage, immutable audit events                                                              | processor/legal retention approval remains external                                |

## API and edge responsibility

NestJS owns a deny-by-default API CSP, `X-Frame-Options: DENY`, no-referrer, restrictive Permissions Policy, MIME sniffing protection through Helmet, body limits, Redis-backed rate limits, and server timeouts. HSTS is emitted only in production. The edge owns TLS certificates and protocol policy, request-size enforcement at or below the API limit, DDoS/WAF controls, connection limits, and preservation of the request ID. The edge must not weaken API headers; frontend CSP is a separate Angular/Astro policy because the API serves no executable UI.

## Residual risks and gates

- Render remains a candidate, not an asserted deployment. Region, private networking, encrypted backup, PITR, restore API, log retention, and DPA evidence must be recorded before launch.
- Sentry code is PII-scrubbed and production-fail-closed, but DSN, data region, retention, and DPA approval remain deployment evidence.
- Postmark delivery stays disabled unless `EMAIL_DELIVERY_PRODUCTION_APPROVED=true`; production configuration validation enforces the gate.
- Finnhub stays disabled unless delay, coverage, quota, redistribution rights, and explicit approval are recorded.
- Private export storage and TTLs stay disabled until approved configuration is supplied.

No local filesystem session, queue, cache, or financial authority was introduced.
