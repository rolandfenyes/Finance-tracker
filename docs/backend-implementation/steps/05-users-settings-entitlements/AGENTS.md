# Step 05 — Users, Onboarding, Settings, and Entitlements

## Objective

Implement current user profile/preferences/onboarding behavior and the existing free/premium/admin capability model.

## Evidence

`src/controllers/onboard.php`, `settings*.php`, `tutorial.php`, relevant helpers in `src/helpers.php`, migrations 018, 022, 027, 029, 033, and current role capabilities around `src/helpers.php:213–258`.

## Deliverables

- profile and desired-language settings;
- onboarding progress and tutorial completion;
- theme preference as a validated identifier only; theme rendering remains frontend work;
- current free/premium/admin entitlements and limits sourced from one backend capability definition;
- admin is not silently granted a personal-finance account;
- locale list limited to actually supported locales.

## Corrections

Do not reproduce arbitrary custom-role assignment while the underlying role constraint cannot support it. Step 00 must decide either fixed roles/capabilities or a real RBAC schema.

## Acceptance

Capability and quota tests cover free/premium/admin, invalid locale/theme, onboarding transitions, and cross-user settings access.

