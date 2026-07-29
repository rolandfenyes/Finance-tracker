# Step 02 PostgreSQL operations contract

## Boundary

The Step 02 target is intentionally domain-free. The committed baseline creates:

- `mymoneymap`, the schema for later application tables;
- `mymoneymap_meta.kysely_migration`, the immutable migration ledger;
- `mymoneymap_meta.kysely_migration_lock`, the Kysely concurrency lock.

It creates no users, administrator, credentials, finance data, reference seed, or PostgreSQL
extension. The 42 PHP-era SQL files are evidence for later schema design and Step 20 migration;
they are never inputs to the new runner. In particular, `028_default_admin.sql` must never be run,
copied, or represented as applied in the target ledger.

## Migration identity and order

ADR-003 selects Kysely with explicit migrations. Target migration names use:

```text
YYYYMMDDHHMMSS_lowercase_description
```

The 14-digit UTC order prefix is unique and immutable. Files and the explicit registry must match
exactly and remain in ascending order. CI runs `pnpm db:migrations:check` to reject missing registry
entries, invalid names, duplicate names/prefixes, or reordered history. Kysely unordered migrations
are disabled. Applied migrations are never renamed or edited; a correction is a new migration.

Migrations run only through deployment/operations commands. API startup never invokes them.

```text
pnpm db:status
pnpm db:migrate
pnpm db:drift
```

Production requires `DATABASE_MIGRATION_URL`; local/test commands may reuse `DATABASE_URL`.
`db:migrate` is transactional on PostgreSQL and Kysely serializes concurrent runners with its
migration lock. A failed migration returns a failing process status.

## Roles and ownership

`apps/api/database/bootstrap/roles.sql` is an idempotent administrative template. Run it as the
database owner after the hosting platform provisions authentication secrets:

- `mymoneymap_migrator` owns `mymoneymap` and `mymoneymap_meta`, can execute DDL, and is used only
  by deployment migration commands;
- `mymoneymap_runtime` receives `CONNECT`, application-schema `USAGE`, table DML, and sequence
  usage through grants/default privileges;
- runtime receives no access to the migration schema and no schema-creation privilege;
- public schema creation is revoked from `PUBLIC`;
- neither role is superuser, database creator, role creator, or replication role.

The SQL deliberately contains no password. The platform/secret manager supplies distinct
credentials. After each domain migration, its author must verify new objects are owned by the
migrator and runtime has only the required DML privileges. Cross-user ownership and relationship
constraints belong in the owning domain migration; Step 02 does not pre-create speculative tables.

## TLS contract

`DATABASE_TLS_MODE` is explicit:

- `disable`: local/test loopback only;
- `require`: encrypted connection without certificate verification, non-production only;
- `verify-full`: certificate and hostname verification; mandatory in production.

`DATABASE_TLS_CA` supplies a CA bundle only when the system trust store does not already trust the
server chain. Do not put the bundle or a connection URL in source control. `sslmode` in a connection
URL is rejected so it cannot silently contradict this contract. Server-side TLS enforcement,
certificate rotation, private networking, and provider-specific trust configuration are deployment
gates in Step 21.

## Pooling policy

The API owns one shared `pg.Pool`; Kysely and readiness use that pool. It is closed during graceful
shutdown. Configure:

- `DATABASE_POOL_MAX` as a per-process cap;
- `DATABASE_CONNECTION_TIMEOUT_MS` to fail boundedly when no connection is available;
- `DATABASE_IDLE_TIMEOUT_MS` to release idle connections;
- `DATABASE_MAX_LIFETIME_SECONDS` to rotate long-lived connections.

Before deployment, enforce:

```text
(maximum API instances × DATABASE_POOL_MAX)
+ worker pools
+ migration/operations connections
+ monitoring reserve
< provider connection limit
```

Use provider pooling/PgBouncer only after verifying transaction-mode compatibility. Never place
schema migrations through transaction pooling unless the provider guarantees the session and
advisory-lock semantics used by the runner.

## Empty database, drift, and rollback rehearsal

On a new synthetic database:

1. apply `pnpm db:migrate`;
2. require `pnpm db:status` to show every migration applied;
3. require `pnpm db:drift` to match the committed catalog fingerprint;
4. run the PostgreSQL integration suite;
5. run `pnpm db:rollback` once;
6. migrate forward again and verify the identical fingerprint.

`db:rollback` removes only the latest target migration and uses its explicit transactional `down`.
It is for an isolated rehearsal or an owner-approved deployment rollback. Never run it speculatively
against production. Once a migration contains irreversible data transformation, restore-forward or
a new corrective migration is preferred; its pull request must state that `down` is not data-safe.
The current baseline rollback uses `DROP SCHEMA ... RESTRICT`, never `CASCADE`, so later objects make
an accidental rollback fail safely.

## Backup restore contract

Logical rollback is not a backup. Production restore is rehearsed into a newly provisioned database,
never over the source:

1. select an approved provider snapshot/PITR point and record its immutable identifier and time;
2. restore it to a new isolated database with no application traffic;
3. use the migration identity to apply only migrations newer than the snapshot;
4. run schema drift, row-count/reconciliation, constraint, and smoke checks;
5. switch traffic only after owner approval; retain the former database until the rollback window
   closes.

Provider snapshot/PITR configuration, RPO/RTO, encryption, retention, full data reconciliation, and
a production-like restore drill are Step 21 gates. Step 02 proves only empty-schema rollback and
deterministic forward restoration.
