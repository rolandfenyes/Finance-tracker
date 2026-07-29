# Step 19 — Privacy Export, Deletion, and Audit

## Objective

Implement a complete, versioned data manifest for access/export/deletion and a security/privileged audit trail.

## Evidence

`src/controllers/settings_privacy.php`, `build_user_data_export()`, all user-owned tables, and findings P-01 through P-10.

## Required behavior

- manifest listing every user-owned domain/table/object/cache/export/log category;
- complete versioned JSON export and useful CSV datasets;
- exclude password/session/remember-token hashes, WebAuthn challenge internals, and secret values;
- asynchronous export with expiry and private object storage when approved;
- account deletion workflow covering FK data, non-FK remnants, jobs, caches, exports, and documented backup retention;
- legal-policy version/effective-date record only if supplied by owner/counsel;
- immutable security and privileged-action audit events with retention controls.

## Prohibited

Do not claim GDPR compliance or invent retention periods. Do not delete backup data outside an approved retention/restore design.

## Acceptance

Automated manifest coverage fails when a new user-owned table lacks export/deletion classification. Export completeness, secret exclusion, cross-user access, deletion rehearsal, retry, cache/job cleanup, and audit immutability tests pass.

