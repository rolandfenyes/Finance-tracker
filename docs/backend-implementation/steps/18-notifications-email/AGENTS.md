# Step 18 — Notifications and Email

## Objective

Preserve evidenced multilingual emails and notification preferences through a durable queued delivery model.

## Evidence

`src/mailer.php`, `src/services/email_notifications.php`, `scripts/send_user_emails.php`, `scripts/send_verification_emails.php`, migrations 011/035, and `docs/email_templates/{en,es,hu}`.

## Required behavior

- template key/version/locale and validated data contract;
- existing welcome, verification, reports, overspend, emergency, goal, feedback, and tips categories only where their triggering backend feature exists;
- queued send, retry, dead-letter, delivery status, and correlation to triggering event;
- user language/preferences and transactional versus educational distinction;
- provider webhook/bounce/suppression only if the provider is approved.

## Corrections

Do not mention connected bank accounts. Do not enable SMS or push merely because configuration rows exist. Do not send incorrect financial totals; notification data must come from tested read models.

## Acceptance

Locale fallback, template-contract validation, duplicate-event idempotency, retry/dead-letter, preference enforcement, PII-safe logs, provider failure, and calculation-source tests pass.

