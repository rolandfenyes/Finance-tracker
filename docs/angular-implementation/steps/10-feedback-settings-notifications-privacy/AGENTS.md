# Step 10 — Feedback, Settings, Notifications, and Privacy

## Objective

Complete the personal user's More/settings surface, feedback lifecycle,
security/profile/preferences, educational email preference, and privacy
workflows.

## Dependencies

- Steps 00–09 are complete.
- Identity security panels, planning settings components, domain navigation,
  polling, provider gates, and session refresh are reusable.
- Notification-preference GET/PATCH operations have usable generated response
  schemas.

## Required evidence

Read users, identity, currency, budgeting, feedback, notifications, and privacy
controllers/DTOs/generated services; privacy manifest and service tests;
notification classification/preferences; provider gates; and the approved
privacy/legal boundary.

## Routes and required behavior

- `/app/feedback` and `/app/feedback/new`: owned bug/idea feedback, staff
  responses, close/reopen, delete.
- `/app/settings`: grouped settings hub.
- `/app/settings/profile`: name, birth date, desired language.
- `/app/settings/security`: password change and passkey enrollment/deletion.
- `/app/settings/appearance`: backend palette plus device-local
  system/light/dark mode.
- `/app/settings/currencies`: catalogue, membership, main currency, quota.
- `/app/settings/categories` and `/app/settings/income`: reuse Step 06
  implementations.
- `/app/settings/notifications`: educational email enabled/disabled;
  transactional security messages remain mandatory.
- `/app/settings/privacy`: export request/status and deletion request.

## Business and privacy constraints

- Refresh current user after profile, locale, palette, or security changes.
- Password change revokes sessions; present the resulting sign-in path.
- Never store or log passwords, passkey credentials, email, feedback content,
  tokens, or financial export metadata.
- Feedback list is owner-only.
- Export uses one idempotency key per request intent and polls only the owned
  request.
- Export-disabled state must explain the feature gate without claiming success.
- Download links are short-lived private links when present; do not persist
  them.
- Deletion requires the exact reauthentication fields in the generated DTO.
- Do not claim GDPR compliance, retention periods, or immediate backup erasure.
- Transactional email cannot be disabled through the educational preference.

## Reusable UI

- settings navigation/list;
- reusable profile, security, appearance, currency, category, and income panels;
- feedback timeline with attributed staff response;
- educational preference control with mandatory-message disclosure;
- asynchronous operation status/polling panel;
- privacy danger-zone confirmation.

## Tests

- profile/locale/theme session refresh;
- display mode remains local and palette remains server-synced;
- password/passkey safe handling and post-change session behavior;
- feedback ownership and lifecycle UI;
- notification preference does not imply transactional opt-out;
- export idempotency, polling, unavailable storage, expiry, and URL
  non-persistence;
- deletion reauthentication, accepted state, and uncertain-result recovery;
- no PII/financial payloads in logs or telemetry;
- settings aliases reuse one implementation;
- mobile settings navigation, keyboard, EN/ES/HU, theme combinations;
- Playwright feedback, security, appearance, notification, export-gated, and
  deletion-confirmation journeys.

## Acceptance criteria

- All personal settings, feedback, notification preference, and privacy
  operations have UI coverage.
- Security and privacy-sensitive data remain transient and unlogged.
- Provider/legal gates are represented honestly.
- Administration was not started.
- Step 11 was not started.
