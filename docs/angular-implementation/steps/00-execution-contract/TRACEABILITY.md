# Angular Traceability Register

Status: **approved by Decision Set A on 2026-07-31**

This register does not reopen corrected backend behavior. It gives every
Critical/High audit finding a frontend preservation, copy, security, or
regression owner. Backend-only invariants remain backend-tested; Angular tests
prove the UI does not misrepresent or bypass them.

## Critical

| ID   | Angular owner      | Frontend obligation and evidence                                                                                                 |
| ---- | ------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| C-01 | 11, 12             | No default credential, temporary known password, or reset secret in UI/fixtures; admin actions use generic request outcomes      |
| C-02 | 01, 11, 12         | Browser bundle/config/fixtures contain no provider secret; production build and secret scan                                      |
| C-03 | 09, 12             | Oversell failure remains distinct and cannot optimistically mutate cash/holdings; trade rejection journey                        |
| C-04 | 04–08, 12          | Goals/reserve/investment/transfers render as transfers, never income/spending; server-total fixtures defeat client recomputation |
| C-05 | 04, 05, 08, 09, 12 | Unavailable FX has no fabricated converted amount; partial/incomplete states propagate                                           |
| C-06 | 11, 12             | Billing/provider secrets are absent; integration secrets are write-only and never redisplayed                                    |

## Security, authentication, and authorization

| ID   | Angular owner    | Frontend obligation and evidence                                                                                                     |
| ---- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| S-01 | 02, 03           | Cookie-only session; login/passkey transitions refetch `/users/me`; no token storage                                                 |
| S-02 | 03               | Unverified state routes only from server response; verification token removed from visible URL/history                               |
| S-03 | 02, 03           | `429` is distinct, localized, and non-enumerating; no sensitive login telemetry                                                      |
| S-04 | 03, 10           | Password change requires current password contract; success invalidates/refetches session state                                      |
| S-05 | 01, 11, 12       | No generated/browser-side encryption key or secret fallback; build artifact secret scan                                              |
| S-06 | 02, all features | Stable error-code/request-ID mapping; server detail is not rendered/logged as trusted copy                                           |
| S-07 | 01, 03, 12       | Relative same-origin `/api`; no user-controlled RP/origin/API host configuration                                                     |
| S-10 | 01, 12           | No runtime CDN fonts/icons/scripts; production header/CSP smoke contract                                                             |
| S-11 | 02–11, 12        | Guards use returned role/entitlements but API authorization remains authoritative; 403/cross-user identifier journeys reveal no data |

## Privacy and legal behavior

| ID   | Angular owner | Frontend obligation and evidence                                                                          |
| ---- | ------------- | --------------------------------------------------------------------------------------------------------- |
| P-01 | 10, 12        | Privacy copy describes actual controls; no private-key/encrypted-finance claim                            |
| P-02 | 10, 12        | Export UI presents only manifest-backed export/status fields and implemented format                       |
| P-04 | 10, 12        | Deletion copy uses returned policy/state, not an invented 30-day promise                                  |
| P-08 | 10, 12        | Deletion request UI does not enumerate tables or promise cascade details beyond API evidence              |
| P-10 | 10–12         | Legal/retention/subprocessor approval remains a launch gate; UI makes no compliance or consent assumption |

## Financial calculations and semantics

| ID   | Angular owner              | Frontend obligation and evidence                                                                               |
| ---- | -------------------------- | -------------------------------------------------------------------------------------------------------------- |
| F-01 | 02, 04–09                  | Unavailable conversion contains no amount; identity conversion stays distinct                                  |
| F-02 | 04–08                      | Forecast FX is labelled assumption and never presented as observed future quote                                |
| F-03 | 05, 08, 09                 | Posted FX provenance is rendered from response, not current-rate lookup                                        |
| F-04 | 02, all financial features | Decimal-string lint/type boundary; `decimal.js` adapter only; adversarial exact-value tests                    |
| F-05 | 02, 03, 05, 09             | Currency/FX/security precision comes from approved metadata; no universal two-decimal helper                   |
| F-06 | 03, 05, 06                 | Client validation mirrors documented sign/zero constraints for UX while server rejection remains authoritative |
| F-07 | 05–08                      | Transfers show source/destination and zero income/spending effect from read models                             |
| F-08 | 04–08                      | Posted, forecast, and projection are separate types/labels/surfaces; reads never trigger mutation UI           |
| F-12 | 08                         | Loan payment conversion comes from loan read model; unavailable FX blocks without client conversion            |
| F-13 | 08, 12                     | Loan estimate shows formula/version/assumptions returned by API; no lender-grade claim                         |
| F-15 | 08, 12                     | Loan detail GET journey is read-only; frontend sends no hidden mutation                                        |
| F-17 | 04, 05, 07, 12             | Goal/reserve movements remain transfers in activity and reports                                                |
| F-21 | 08                         | Investment scenario is visibly hypothetical and cannot alter posted balance                                    |
| F-22 | 09                         | Oversell rejection state; no excess proceeds or optimistic position update                                     |
| F-23 | 09                         | Securities correction uses reversal, not delete semantics                                                      |
| F-24 | 09                         | Missing/stale quote shows unavailable/stale valuation with no fabricated cost-as-market value                  |
| F-25 | 09                         | Historical cost/FX comes from returned snapshots, never current FX                                             |
| F-28 | 09, 12                     | Technical measures remain descriptive; no buy/sell/trim recommendation copy                                    |

## Data model and migration

| ID   | Angular owner | Frontend obligation and evidence                                                                |
| ---- | ------------- | ----------------------------------------------------------------------------------------------- |
| D-01 | 11, 12        | No known default admin credential or “reset to default” workflow                                |
| D-02 | 08, 09, 12    | Investment/security identifiers use generated fields only; no legacy column assumptions         |
| D-03 | 06, 07        | Goal-category relationship uses returned IDs/labels; no client repair inference                 |
| D-05 | 07, 12        | Duplicate legacy rows are not deduplicated or summed in Angular                                 |
| D-06 | 02–11         | Related IDs remain opaque/owned; server 403/404 behavior is preserved without cross-user lookup |
| D-07 | 09            | Instrument retirement/deletion is not represented as trade-history deletion                     |
| D-11 | 02, 03, 05–09 | Forms reflect generated enums/required fields; API validation remains authoritative             |

## Product and marketing accuracy

| ID   | Angular owner | Frontend obligation and evidence                                                                         |
| ---- | ------------- | -------------------------------------------------------------------------------------------------------- |
| M-01 | 10, 12        | Export copy names only implemented JSON export; no general CSV export claim                              |
| M-02 | 11, 12        | Billing is administrative records only; no checkout, portal, cancellation, webhook, or self-service copy |
| M-05 | 05, 12        | Reports contain no month close/reopen state or claim                                                     |
| M-06 | 08, 12        | Loan projection copy states assumptions/version and separates posted history                             |
| M-07 | 04, 05, 12    | FX stale/unavailable and report completeness are visible; no unconditional accuracy claim                |
| M-08 | 10, 11, 12    | Privacy/security copy reflects actual control manifest; no private-key claim                             |
| M-15 | 12            | Frontend developer/deployment docs rely on repository migration gate, not “migration 001” guidance       |

## Operational and UX

| ID   | Angular owner  | Frontend obligation and evidence                                                                   |
| ---- | -------------- | -------------------------------------------------------------------------------------------------- |
| O-01 | 04–09, 12      | GET/navigation journeys send no materialization command; no polling route mutates financial state  |
| O-02 | 09, 11         | Worker retry/dead-letter/delayed states are rendered from operations/read models, not hidden       |
| O-03 | 01, 12         | Reproducible Angular install/lint/typecheck/test/build/contract/Playwright CI                      |
| O-04 | 02, 12         | Required integration/HTTP journeys fail when backend dependencies are unavailable; no skip-success |
| O-05 | 12             | Final frontend freeze includes backend contract/drift gate; no frontend schema workaround          |
| O-07 | 01, 12         | No viewport zoom restriction; Playwright/manual 200% zoom and 320px reflow                         |
| O-08 | 01, 12         | No orientation blocker; landscape Playwright journey reaches every critical shell                  |
| O-11 | 04, 09, 11, 12 | Health/queue/provider unavailable states are observable and PII-safe                               |

## Frontend handoff invariants

| Invariant                                  | Owners            | Required evidence                                                        |
| ------------------------------------------ | ----------------- | ------------------------------------------------------------------------ |
| Frozen OpenAPI/generated-client ownership  | 00, 02, 12        | 113/148 baseline, drift check, no generated edits                        |
| Same-origin HttpOnly session               | 01–03, 12         | relative `/api`, storage inspection, 401/session tests                   |
| Server-owned onboarding/role/entitlements  | 02–04, 07–11      | route guards from `/users/me`, server rejection preserved                |
| Exact decimal strings                      | 02, 04–09, 11, 12 | adapter tests, forbidden `number`/`parseFloat` audit                     |
| Backend-owned calculations                 | 04–09             | fixtures prove rendered values differ from possible client recomputation |
| Immutable correction/reversal              | 05, 07–09         | no destructive history edit/delete UI                                    |
| Posted/forecast/projection/scenario states | 04–09             | distinct labels, status tokens, filters, accessibility text              |
| Provider/production gates                  | 04, 09–12         | disabled, delayed, stale, unavailable states; no unsupported claim       |
| EN/ES/HU + English fallback                | 01–12             | catalog completeness/fallback and route journeys                         |
| System/light/dark + eight palettes         | 01–12             | token contrast, persistence, component and visual tests                  |
| Mobile-first WCAG 2.2 AA                   | 01–12             | 320px, desktop, keyboard, focus, axe, reduced motion, target size        |
| Privacy-safe browser/log/URL behavior      | 02–12             | storage, URL, telemetry, fixture, snapshot scans                         |

## Step dependency and ownership map

| Step | Depends on        | Owns                                                              | Contract blockers                    |
| ---: | ----------------- | ----------------------------------------------------------------- | ------------------------------------ |
|   01 | 00 approved       | Angular/Nx scaffold, design system, themes, i18n/test foundations | none beyond Step 00 approval         |
|   02 | 00–01, drift pass | API core, session, errors, decimal/idempotency/date boundaries    | none for core                        |
|   03 | 00–02             | identity and onboarding                                           | CG-001 for passkeys                  |
|   04 | 00–03             | product shell/current month                                       | CG-004 for activity provenance       |
|   05 | 00–04             | journal and reports                                               | CG-002, CG-004                       |
|   06 | 00–05             | planning and recurrence                                           | none currently                       |
|   07 | 00–06             | goals and reserve                                                 | none currently                       |
|   08 | 00–07             | loans and investments                                             | CG-003, CG-005                       |
|   09 | 00–08             | securities                                                        | CG-006                               |
|   10 | 00–09             | feedback/settings/notifications/privacy                           | CG-001, CG-007, CG-009               |
|   11 | 00–10             | administration/operations/email/billing                           | CG-008, CG-010–CG-014                |
|   12 | 00–11             | hardening and frontend freeze                                     | all gap closure and owner acceptance |

No later step may reinterpret a blocker as permission to create a local DTO.
