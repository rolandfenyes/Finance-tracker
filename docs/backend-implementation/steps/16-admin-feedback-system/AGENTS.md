# Step 16 — Feedback, Administration, and System Settings

## Objective

Rebuild existing feedback lifecycle and guarded administrative operations without exposing secrets or inventing support roles.

## Evidence

`src/controllers/feedback.php`, `src/controllers/admin.php`, migrations 017, 030, 032, 033, 035, and all `/admin/*` routes in the route inventory.

## Required behavior

- feedback create/read/status/delete and admin response/resolution;
- admin analytics only for currently evidenced metrics, with explicit definitions;
- user status, verification/reset operations using secure links rather than displayed temporary passwords;
- system settings and integration configuration;
- privileged-action audit records;
- masked/write-only secret handling through the shared encrypted-setting/secret-manager interface.

## Prohibited

No admin impersonation, support role, arbitrary analytics, editable secrets returned in full, or migration execution over a public application endpoint unless Step 00 explicitly preserves it with a separate privileged operations design.

## Acceptance

Admin guard, non-admin denial, PII redaction, secret write/read masking, feedback ownership, privileged audit, pagination, and safe user-recovery tests pass.

