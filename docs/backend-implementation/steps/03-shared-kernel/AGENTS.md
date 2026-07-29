# Step 03 — Shared Kernel and Platform Primitives

## Objective

Implement only the primitives required consistently by later domain modules.

## Deliverables

- UUID/identifier policy.
- UTC clock abstraction and explicit user-timezone boundary.
- decimal money, currency amount, FX rate, percentage, security quantity, and rounding-policy value objects.
- stable API error codes and validation-error structure.
- transaction-bound domain event/outbox interface if approved in Step 00.
- idempotency-key storage and execution primitive.
- pagination/date-range primitives.
- secret-value redaction and encrypted-setting interface without selecting unapproved vendors.

## Rules

API decimals serialize as strings. Money always carries currency. Do not create a generic “BaseRepository,” universal entity class, or speculative framework.

## Acceptance

Property tests cover decimal serialization, rounding, invalid currency/percentage/quantity inputs, time boundaries, and idempotent retries. No primitive uses JavaScript floating point for exact values.

