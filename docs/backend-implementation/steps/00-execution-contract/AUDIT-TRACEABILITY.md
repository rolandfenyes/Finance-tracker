# Step 00 — Critical/High Audit Traceability

## Contract

This register maps every finding classified **Critical** or **High** in `docs/INCORRECT-FACTS-AND-LOGIC-AUDIT.md` to an implementation owner and a test obligation. The test names are stable intent identifiers; later steps may adapt them to the selected test framework but may not weaken the assertion.

Fixture IDs refer to `GOLDEN-FIXTURES.md`. “Contract/copy gate” means the backend must expose only the documented capability and the later Angular/Astro work must test the user-facing statement. It does not authorize frontend work in the backend plan.

## Critical findings

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| C-01 default administrator migration/reset | 02, 04, 20–21 | `migration_never_seeds_or_resets_known_admin_credentials`; secure one-time bootstrap has expiry, audit, and reuse rejection | MIG-03 |
| C-02 hard-coded production secret fallbacks | 01, 16, 21 | `production_boot_fails_when_required_secret_or_provider_config_is_missing`; repository secret scan gate | ADM-02 |
| C-03 stock oversell corrupts holdings/cash | 15 | PostgreSQL atomic and concurrent oversell rejection; no partial trade/lot/cash/P&L rows | STK-02, STK-03 |
| C-04 internal savings movements counted as income/spending | 06, 10–14, 20 | balanced transfer invariant and report reconciliation across goals, reserve, investments, and migrated history | MON-03, REP-01, MIG-04 |
| C-05 FX failure relabels unchanged amount | 07, 10, 13, 15 | unavailable FX has no converted amount and makes dependent aggregate incomplete | FX-03, REP-04 |
| C-06 billing secrets stored/displayed in plaintext | 16–17, 20–21 | no billing-provider secret columns/endpoints in v1; integration secrets remain write-only/masked; legacy secrets never migrate | ADM-02, BILL-02 |

## Security, authentication, and authorization

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| S-01 session fixation across authentication transitions | 04 | password, passkey, registration/verification privilege changes rotate session ID and invalidate pre-auth state | ID-01, ID-02, ID-07 |
| S-02 verification not enforced and token has no expiry | 04 | unverified principal cannot access domain APIs; tokens are expiring, single-use, and resend-throttled | ID-01, ID-06 |
| S-03 missing login throttling/audit | 04, 21 | account/IP throttling activates deterministically without enumeration; success/failure security event is recorded safely | ID-02, ID-05 |
| S-04 password change lacks reauthentication/session revocation | 04 | wrong/missing current password fails; success rotates current session and revokes other sessions | ID-02 |
| S-05 generated data key may live under project storage | 01, 16, 21 | production config accepts secrets only from approved runtime secret source; deploy artifact contains no generated key | ADM-02 |
| S-06 DB exception details exposed | 01, 21 | synthetic repository failure returns stable safe error/request ID and records private structured error without credentials | OPS-01 |
| S-07 untrusted host/proxy/origin affects canonical/WebAuthn origin | 01, 04, 21 | spoofed host/forwarded headers cannot alter canonical URL, secure-cookie policy, RP ID, or allowed origin | ID-07 |
| S-10 missing browser security headers/runtime CDN exposure | 01, 21 | API security-header contract and production dependency/build integrity gate | — |
| S-11 procedural/incomplete authorization and related-ID ownership | 03–19 | every protected resource family includes two-user object/reference isolation and admin-policy HTTP tests | ID-08, ADM-01, MIG-05 |

`S-14` is Medium in the source audit, not High; its misleading encryption claim is nevertheless covered under P-01/M-08 below.

## Privacy and legal behavior

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| P-01 private-key/encrypted-finance claim is false | 19, contract/copy gate | machine-readable privacy/control manifest never claims user-private-key encryption; later copy test matches implemented controls | PRIV-01, PRIV-02 |
| P-02 export omits multiple domains | 19 | complete manifest-driven export contains every approved user-data domain and fails when a new owned model lacks export classification | PRIV-01, PRIV-04 |
| P-04 claimed 30-day purge differs from implementation | 19, 21, legal/copy gate | deletion state machine, retention clock, backup exception, and policy-version response agree exactly; no unimplemented deadline claim | PRIV-03 |
| P-08 cascades miss data and some ownership FKs | 02–20 | deletion-manifest integration test covers DB, Redis/jobs, objects/exports, and constrained owner references | PRIV-03, PRIV-04, MIG-05 |
| P-10 no retention/DPA/subprocessor/consent/DSR operations | 19, 21, legal gate | launch gate fails without approved retention/subprocessor/policy versions; consent and DSR events are auditable | PRIV-03, PRIV-04 |

## Financial calculations and semantics

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| F-01 FX unavailable returns input value | 07 | typed unavailable result contains no amount; same-currency identity is distinct | FX-01, FX-03 |
| F-02 current FX copied into future dates | 07, 09–10 | forecast rate is labeled assumption and never stored/queryable as an observed future quote | FX-05 |
| F-03 posted transactions lack stable FX snapshots | 06–07, 20 | posting stores rate/source/rate time/retrieval/rounding; reports remain reproducible after quote changes | FX-02, FX-04 |
| F-04 NUMERIC cast to binary float | 03, all financial steps | decimal lint/type boundary plus adversarial exact-decimal PostgreSQL/API round trip | MON-02, LOAN-01, STK-01 |
| F-05 universal two-decimal formatting | 03, 07, 15 | zero-/two-/three-minor-unit currencies, FX rates, and security quantities use distinct approved precision | MON-02, FX-02, STK-01 |
| F-06 invalid zero/negative transaction amounts | 02–03, 06 | domain validation and PostgreSQL checks reject invalid sign/zero and roll back all legs | MON-06 |
| F-07 no account/transfer model | 06, 10, 20 | every posted entry balances; internal transfers have owned source/destination and zero income/spending effect | MON-01–MON-04, REP-01 |
| F-08 virtual/posted records mixed | 06, 09–10, 13 | forecasts and posted entries are distinct in schema/API/reports; reads never materialize | REC-05, REP-02, LOAN-05 |
| F-12 manual cross-currency loan payment corrupts principal | 07, 13 | manual and scheduled paths apply identical dated, snapshotted conversion or both reject unavailable FX | LOAN-04 |
| F-13 loan annual/12 formula overstates accuracy | 13, contract/copy gate | versioned nominal-monthly estimate matches approved formula and response label; no lender-grade claim | LOAN-01, LOAN-02 |
| F-15 loan GET mutates/backfills history | 13 | read-only transaction/query assertion and before/after DB snapshot show zero writes | LOAN-06 |
| F-17 reserve/goal movements are income/spending | 06, 10–12, 20 | contribution, withdrawal, archive, and migrated history use transfers and reconcile reports | GOAL-03, EF-01–EF-03, MIG-04 |
| F-21 securities modeled with fixed interest | 14 | projection changes no posted balance and is returned as user-authored scenario | INV-03, INV-04 |
| F-22 stock oversell records excess proceeds | 15 | locked atomic oversell rejection including concurrent sells | STK-02, STK-03 |
| F-23 trade deletion leaves cash wrong | 15 | reversal/rebuild covers trade, cash, lots, position, and realized result in one transaction | STK-04 |
| F-24 missing quote falls back to cost | 15 | missing/stale quote returns valuation state without market value or fabricated zero P/L | STK-06 |
| F-25 current FX used for historical cost basis | 07, 15 | acquisition/sale FX snapshots preserve security and currency attribution after rate changes | STK-07 |
| F-28 buy/trim signals resemble advice | 15, legal/copy gate | analytics response contains descriptive measures/status only and has no action verb/recommendation field | STK-10 |

## Data model and migrations

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| D-01 default-admin migration reset | 02, 04, 20 | clean schema and legacy migration never create/reset a known credential; secure bootstrap is separately tested | MIG-03 |
| D-02 untracked investment columns | 02, 14–15, 20 | source fingerprint detects `stock_id/units`; reconciliation maps or quarantines without silent loss | MIG-02 |
| D-03 goal category present but migration not recorded | 02, 11, 20 | source ledger/schema mismatch is detected and produces explicit repair/reconciliation result | MIG-02 |
| D-05 duplicate goal/emergency tables | 11–12, 20 | deduplication prevents double counting and emits deterministic matched/quarantined counts | MIG-04 |
| D-06 missing ownership FKs | 02, each domain, 20 | non-null/composite tenant integrity plus cross-user reference rejection; orphans quarantine | ID-08, MIG-05 |
| D-07 shared stock deletion cascades user trades | 15, 20 | deleting/retiring instrument cannot delete trades, lots, cash, or P/L history | STK-01, STK-05 |
| D-11 weak amount/date/kind constraints | 02–15 | empty-schema migration exposes checks/enums/not-null constraints and invalid direct SQL rolls back | MON-06, REC-06 |

## Product and marketing accuracy

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| M-01 claims general CSV and JSON export | 19, 22, copy gate | OpenAPI/export capabilities enumerate only implemented formats; acceptance rejects undocumented general CSV route/claim | PRIV-01 |
| M-02 claims trial/cancel/self-service billing | 05, 17, 22, copy gate | API exposes administrative records only; no checkout, portal, provider webhook, or customer cancellation contract exists | BILL-01, BILL-02 |
| M-05 claims month open/close | 10, 22, copy gate | report contract contains no close/reopen state or endpoints; later copy test omits claim | REP-01 |
| M-06 “payoff forecasts you can trust” | 13, 22, copy gate | loan response identifies formula/version/assumptions and keeps projection separate from posted history | LOAN-01, LOAN-04, LOAN-05 |
| M-07 “daily FX for accurate reports” despite silent failure | 07, 10, 22, copy gate | missing/stale quote state propagates to report completeness and provenance | FX-03–FX-05, REP-04 |
| M-08 “encrypted storage/private key” | 16, 19, 21, copy gate | control manifest and API documentation describe actual protection; secrets omitted/masked and finance encryption is not falsely asserted | ADM-02, PRIV-02 |
| M-15 quick-start applies only migration 001 | 02, 21 | documented clean-schema command applies every ordered migration and CI drift check from empty PostgreSQL | MIG-01, OPS-01 |

## Operational and UX defects

| Finding | Correction owned by | Required target test | Golden fixture |
|---|---:|---|---|
| O-01 scheduled processing runs on normal requests | 09, 21 | authenticated reads generate no jobs/occurrences/entries; only worker path materializes due work | REC-04, REC-05 |
| O-02 scheduler swallows failures | 09, 18, 21 | failure is observable, retried within bounds, then dead-lettered; retry stays idempotent | REC-04, MAIL-03 |
| O-03 no reproducible build/CI/deployment | 01, 21 | clean install, lint, typecheck, unit/integration, build, OpenAPI drift, migration, and deploy-config gates | OPS-01 |
| O-04 required DB test exits successfully without DB | 02, 21 | PostgreSQL or Redis outage makes the required integration job fail, never skip-success | OPS-01 |
| O-05 no clean-schema migration rehearsal | 02, 20–21 | ephemeral PostgreSQL applies all migrations from empty and compares expected fingerprint | MIG-01, MIG-02 |
| O-07 zoom disabled | **outside backend:** Angular/Astro accessibility gate | browser test verifies scalable viewport, no maximum-scale/user-scalable restriction, and WCAG zoom behavior | — |
| O-08 landscape blocked | **outside backend:** Angular/Astro responsive gate | Playwright landscape viewport reaches all supported routes without an orientation-blocking overlay | — |
| O-11 no queues/health/monitoring/restore/rollback | 01, 09, 18, 21 | liveness/readiness dependency states, queue failure metrics, synthetic backup/restore reconciliation, and rollback rehearsal | MAIL-03, OPS-01, OPS-02 |

## Coverage summary

| Audit section | Critical/High IDs covered |
|---|---|
| Critical | C-01–C-06 |
| Security | S-01–S-07, S-10, S-11 |
| Privacy | P-01, P-02, P-04, P-08, P-10 |
| Finance | F-01–F-08, F-12, F-13, F-15, F-17, F-21–F-25, F-28 |
| Data | D-01–D-03, D-05–D-07, D-11 |
| Marketing | M-01, M-02, M-05–M-08, M-15 |
| Operations | O-01–O-05, O-07, O-08, O-11 |

Medium/Low findings remain valid audit evidence and must be handled by their owning step when in scope, but Step 00 acceptance specifically requires exhaustive Critical/High traceability.
