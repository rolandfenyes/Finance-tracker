# Step 04 — Identity, Sessions, Passkeys, and Authorization

## Objective

Reimplement registration/login/logout/email verification/remember behavior/passkeys with audited security corrections.

## Evidence

`src/auth.php`, `src/controllers/auth.php`, `src/controllers/email_verification.php`, `src/webauthn.php`, migrations 016, 019, 020, 027–030, and findings S-01 through S-14.

## Required behavior

- registration and password hashing;
- expiring, single-use email verification with resend throttling;
- verified email required for application access;
- secure server-managed session creation, regeneration on authentication/privilege changes, expiration, revocation, and logout;
- remember-session behavior only if approved in Step 00;
- passkey registration/login using the approved maintained package and explicit RP/origin configuration;
- login rate limiting and failed/success audit events;
- current-user endpoint and policy/guard foundation;
- password change with current-password/step-up verification and session revocation.

## Explicit omissions

No OAuth/social login, SMS MFA, passwordless email magic links, admin impersonation, or new role types.

## Acceptance

Fixation, brute-force throttling, token expiry/reuse, unverified access, origin/RP mismatch, session revocation, cross-user passkey deletion, and generic error-enumeration tests pass.

