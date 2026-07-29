# Step 02 — PostgreSQL Baseline and Migration System

## Objective

Establish a reproducible PostgreSQL schema/migration/test foundation before domain tables are added.

## Evidence

Read all 42 migration files and the schema-drift findings D-01 through D-12. Never execute `028_default_admin.sql` in the new system.

## Deliverables

- Approved migration runner and naming convention with one unambiguous order.
- Database roles/least-privilege model, TLS configuration contract, connection pooling policy, and transaction helper.
- Empty-database create/migrate/rollback rehearsal.
- Migration metadata and drift check.
- PostgreSQL integration-test lifecycle with isolated databases.
- Initial extensions only when justified by an approved requirement.

## Corrections

- No default administrator seed.
- No duplicate migration sequence numbers.
- No ORM `synchronize` in production.
- Schema ownership, `NOT NULL`, FK, unique, and check constraints must be intentional.

## Acceptance

An empty PostgreSQL instance reaches the expected baseline deterministically; rollback/restore behavior is documented; CI detects drift and treats an unavailable database as failure.

