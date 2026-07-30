import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)} (
      id uuid PRIMARY KEY,
      transformer_version varchar(80) NOT NULL,
      source_schema_version varchar(80) NOT NULL,
      source_schema_fingerprint char(64) NOT NULL,
      source_data_fingerprint char(64) NOT NULL,
      mode varchar(16) NOT NULL,
      status varchar(24) NOT NULL,
      checkpoint jsonb NOT NULL DEFAULT '{}'::jsonb,
      source_row_count bigint NOT NULL DEFAULT 0,
      planned_row_count bigint NOT NULL DEFAULT 0,
      quarantine_row_count bigint NOT NULL DEFAULT 0,
      error_code varchar(80),
      created_at timestamptz NOT NULL,
      started_at timestamptz,
      completed_at timestamptz,
      CONSTRAINT legacy_migration_transformer_version_check
        CHECK (transformer_version ~ '^[a-z0-9][a-z0-9._-]{0,79}$'),
      CONSTRAINT legacy_migration_source_schema_fingerprint_check
        CHECK (source_schema_fingerprint ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_source_data_fingerprint_check
        CHECK (source_data_fingerprint ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_mode_check CHECK (mode IN ('rehearsal','cutover')),
      CONSTRAINT legacy_migration_status_check
        CHECK (status IN ('running','completed','blocked','rolled_back')),
      CONSTRAINT legacy_migration_counts_check
        CHECK (
          source_row_count >= 0
          AND planned_row_count >= 0
          AND quarantine_row_count >= 0
        ),
      CONSTRAINT legacy_migration_completion_check
        CHECK (
          (status = 'completed' AND completed_at IS NOT NULL)
          OR status <> 'completed'
        ),
      CONSTRAINT legacy_migration_source_run_unique
        UNIQUE (transformer_version, source_data_fingerprint, mode)
    );
    CREATE INDEX legacy_migration_batches_status_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)}
      (status, created_at DESC, id DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_row_ledger`)} (
      id uuid PRIMARY KEY,
      batch_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)} (id)
        ON DELETE CASCADE,
      source_table varchar(80) NOT NULL,
      source_key_hash char(64) NOT NULL,
      target_domain varchar(80) NOT NULL,
      target_table varchar(80),
      target_id uuid,
      outcome varchar(16) NOT NULL,
      reason_code varchar(80),
      created_at timestamptz NOT NULL,
      CONSTRAINT legacy_migration_row_source_table_check
        CHECK (source_table ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_row_source_key_hash_check
        CHECK (source_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_row_target_domain_check
        CHECK (target_domain ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_row_target_table_check
        CHECK (target_table IS NULL OR target_table ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_row_outcome_check
        CHECK (outcome IN ('planned','quarantined','skipped')),
      CONSTRAINT legacy_migration_row_reason_check
        CHECK (reason_code IS NULL OR reason_code ~ '^[A-Z][A-Z0-9_]{1,79}$'),
      CONSTRAINT legacy_migration_row_outcome_detail_check
        CHECK (
          (outcome = 'planned' AND target_table IS NOT NULL AND target_id IS NOT NULL
            AND reason_code IS NULL)
          OR (outcome <> 'planned' AND target_id IS NULL AND reason_code IS NOT NULL)
        ),
      CONSTRAINT legacy_migration_row_unique
        UNIQUE (batch_id, source_table, source_key_hash, target_domain, target_table)
    );
    CREATE INDEX legacy_migration_row_batch_outcome_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_row_ledger`)}
      (batch_id, outcome, source_table, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_quarantine`)} (
      id uuid PRIMARY KEY,
      batch_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)} (id)
        ON DELETE CASCADE,
      source_table varchar(80) NOT NULL,
      source_key_hash char(64) NOT NULL,
      user_key_hash char(64),
      domain varchar(80) NOT NULL,
      reason_code varchar(80) NOT NULL,
      detail_codes varchar(80)[] NOT NULL DEFAULT ARRAY[]::varchar(80)[],
      created_at timestamptz NOT NULL,
      CONSTRAINT legacy_migration_quarantine_source_table_check
        CHECK (source_table ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_quarantine_source_key_hash_check
        CHECK (source_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_quarantine_user_key_hash_check
        CHECK (user_key_hash IS NULL OR user_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_quarantine_domain_check
        CHECK (domain ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_quarantine_reason_check
        CHECK (reason_code ~ '^[A-Z][A-Z0-9_]{1,79}$'),
      CONSTRAINT legacy_migration_quarantine_unique
        UNIQUE (batch_id, source_table, source_key_hash, reason_code)
    );
    CREATE INDEX legacy_migration_quarantine_batch_reason_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_quarantine`)}
      (batch_id, reason_code, source_table, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_reconciliation`)} (
      id uuid PRIMARY KEY,
      batch_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)} (id)
        ON DELETE CASCADE,
      user_key_hash char(64) NOT NULL,
      domain varchar(80) NOT NULL,
      currency char(3) NOT NULL,
      source_count bigint NOT NULL,
      planned_count bigint NOT NULL,
      quarantine_count bigint NOT NULL,
      source_amount numeric(30,12) NOT NULL,
      planned_amount numeric(30,12) NOT NULL,
      difference numeric(30,12) NOT NULL,
      status varchar(16) NOT NULL,
      explanation_codes varchar(80)[] NOT NULL DEFAULT ARRAY[]::varchar(80)[],
      created_at timestamptz NOT NULL,
      CONSTRAINT legacy_migration_reconciliation_user_hash_check
        CHECK (user_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT legacy_migration_reconciliation_domain_check
        CHECK (domain ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT legacy_migration_reconciliation_currency_check
        CHECK (currency ~ '^[A-Z]{3}$'),
      CONSTRAINT legacy_migration_reconciliation_counts_check
        CHECK (source_count >= 0 AND planned_count >= 0 AND quarantine_count >= 0),
      CONSTRAINT legacy_migration_reconciliation_difference_check
        CHECK (difference = source_amount - planned_amount),
      CONSTRAINT legacy_migration_reconciliation_status_check
        CHECK (status IN ('exact','explained','blocked')),
      CONSTRAINT legacy_migration_reconciliation_explanation_check
        CHECK (
          (status = 'exact' AND difference = 0 AND cardinality(explanation_codes) = 0)
          OR (status <> 'exact' AND cardinality(explanation_codes) > 0)
        ),
      CONSTRAINT legacy_migration_reconciliation_unique
        UNIQUE (batch_id, user_key_hash, domain, currency)
    );
    CREATE INDEX legacy_migration_reconciliation_batch_status_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_reconciliation`)}
      (batch_id, status, domain, currency, id);
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_reconciliation`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_quarantine`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_row_ledger`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.legacy_migration_batches`)};
  `.execute(database);
}
