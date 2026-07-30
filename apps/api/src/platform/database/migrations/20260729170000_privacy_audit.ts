import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_requests`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      manifest_version integer NOT NULL,
      idempotency_key_hash char(64) NOT NULL,
      status varchar(24) NOT NULL,
      queue_job_id varchar(255),
      attempt_count integer NOT NULL DEFAULT 0,
      max_attempts integer NOT NULL DEFAULT 3,
      error_code varchar(80),
      created_at timestamptz NOT NULL,
      started_at timestamptz,
      completed_at timestamptz,
      expires_at timestamptz,
      CONSTRAINT privacy_export_manifest_version_check CHECK (manifest_version > 0),
      CONSTRAINT privacy_export_key_hash_check CHECK (idempotency_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT privacy_export_status_check CHECK (
        status IN ('queued','running','completed','retryable_failed','dead_letter')
      ),
      CONSTRAINT privacy_export_attempt_check CHECK (
        attempt_count >= 0 AND max_attempts = 3 AND attempt_count <= max_attempts
      ),
      CONSTRAINT privacy_export_expiry_check CHECK (
        (status = 'completed' AND completed_at IS NOT NULL AND expires_at > completed_at)
        OR (status <> 'completed' AND expires_at IS NULL)
      ),
      CONSTRAINT privacy_export_user_key_unique UNIQUE (user_id, idempotency_key_hash),
      CONSTRAINT privacy_export_id_user_unique UNIQUE (id, user_id)
    );
    CREATE INDEX privacy_export_requests_owner_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_requests`)}
      (user_id, created_at DESC, id DESC);
    CREATE INDEX privacy_export_requests_status_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_requests`)}
      (status, created_at, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_artifacts`)} (
      id uuid PRIMARY KEY,
      export_request_id uuid NOT NULL,
      user_id uuid NOT NULL,
      format varchar(8) NOT NULL,
      dataset varchar(80) NOT NULL,
      object_key varchar(512) NOT NULL UNIQUE,
      media_type varchar(100) NOT NULL,
      byte_size bigint NOT NULL,
      sha256 char(64) NOT NULL,
      created_at timestamptz NOT NULL,
      expires_at timestamptz NOT NULL,
      CONSTRAINT privacy_export_artifact_request_owner_fk
        FOREIGN KEY (export_request_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_requests`)} (id, user_id)
        ON DELETE CASCADE,
      CONSTRAINT privacy_export_artifact_user_fk
        FOREIGN KEY (user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      CONSTRAINT privacy_export_artifact_format_check CHECK (format IN ('json','csv')),
      CONSTRAINT privacy_export_artifact_dataset_check CHECK (
        dataset ~ '^[a-z][a-z0-9_]{0,79}$'
      ),
      CONSTRAINT privacy_export_artifact_size_check CHECK (byte_size >= 0),
      CONSTRAINT privacy_export_artifact_sha_check CHECK (sha256 ~ '^[0-9a-f]{64}$'),
      CONSTRAINT privacy_export_artifact_expiry_check CHECK (expires_at > created_at)
    );
    CREATE INDEX privacy_export_artifacts_owner_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_artifacts`)}
      (user_id, export_request_id, dataset, format);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.privacy_deletion_requests`)} (
      id uuid PRIMARY KEY,
      user_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      subject_hash char(64) NOT NULL,
      idempotency_key_hash char(64) NOT NULL,
      status varchar(24) NOT NULL,
      queue_job_id varchar(255),
      attempt_count integer NOT NULL DEFAULT 0,
      max_attempts integer NOT NULL DEFAULT 3,
      error_code varchar(80),
      created_at timestamptz NOT NULL,
      started_at timestamptz,
      completed_at timestamptz,
      CONSTRAINT privacy_deletion_subject_hash_check CHECK (subject_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT privacy_deletion_key_hash_check CHECK (idempotency_key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT privacy_deletion_status_check CHECK (
        status IN ('queued','running','completed','retryable_failed','dead_letter')
      ),
      CONSTRAINT privacy_deletion_attempt_check CHECK (
        attempt_count >= 0 AND max_attempts = 3 AND attempt_count <= max_attempts
      ),
      CONSTRAINT privacy_deletion_completion_check CHECK (
        (status = 'completed' AND completed_at IS NOT NULL)
        OR status <> 'completed'
      ),
      CONSTRAINT privacy_deletion_subject_key_unique UNIQUE (subject_hash, idempotency_key_hash)
    );
    CREATE INDEX privacy_deletion_requests_user_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privacy_deletion_requests`)}
      (user_id, created_at DESC, id DESC) WHERE user_id IS NOT NULL;
    CREATE INDEX privacy_deletion_requests_status_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privacy_deletion_requests`)}
      (status, created_at, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)} (
      id uuid PRIMARY KEY,
      actor_user_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      subject_user_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      subject_hash char(64) NOT NULL,
      action varchar(80) NOT NULL,
      target_type varchar(40) NOT NULL,
      target_id varchar(160),
      details jsonb NOT NULL DEFAULT '{}'::jsonb,
      retention_policy_version varchar(80),
      retain_until timestamptz,
      created_at timestamptz NOT NULL,
      CONSTRAINT security_audit_subject_hash_check CHECK (subject_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT security_audit_action_check CHECK (
        action IN (
          'privacy.export_requested',
          'privacy.export_completed',
          'privacy.export_failed',
          'privacy.export_accessed',
          'privacy.deletion_requested',
          'privacy.deletion_completed',
          'privacy.deletion_failed'
        )
      ),
      CONSTRAINT security_audit_target_type_check CHECK (
        target_type IN ('privacy_export','privacy_deletion','user')
      ),
      CONSTRAINT security_audit_details_object_check CHECK (jsonb_typeof(details) = 'object'),
      CONSTRAINT security_audit_retention_check CHECK (
        (retention_policy_version IS NULL AND retain_until IS NULL)
        OR (retention_policy_version IS NOT NULL AND retain_until IS NOT NULL)
      )
    );
    CREATE INDEX security_audit_subject_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      (subject_hash, created_at DESC, id DESC);
    CREATE INDEX security_audit_actor_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      (actor_user_id, created_at DESC, id DESC) WHERE actor_user_id IS NOT NULL;
    CREATE UNIQUE INDEX security_audit_business_event_unique
      ON ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      (action, target_id)
      WHERE action <> 'privacy.export_accessed' AND target_id IS NOT NULL;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)}
      ADD COLUMN retention_policy_version varchar(80),
      ADD COLUMN retain_until timestamptz,
      ADD CONSTRAINT login_audit_retention_check CHECK (
        (retention_policy_version IS NULL AND retain_until IS NULL)
        OR (retention_policy_version IS NOT NULL AND retain_until IS NOT NULL)
      );

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      ADD COLUMN retention_policy_version varchar(80),
      ADD COLUMN retain_until timestamptz,
      ADD CONSTRAINT privileged_audit_retention_check CHECK (
        (retention_policy_version IS NULL AND retain_until IS NULL)
        OR (retention_policy_version IS NOT NULL AND retain_until IS NOT NULL)
      );

    CREATE OR REPLACE FUNCTION ${sql.raw(`${APPLICATION_SCHEMA}.reject_security_audit_change`)}()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF TG_OP = 'UPDATE' AND pg_trigger_depth() > 1 THEN
        IF TG_TABLE_NAME = 'login_audit_events' THEN
          IF NEW.user_id IS NULL
             AND (to_jsonb(NEW) - 'user_id') = (to_jsonb(OLD) - 'user_id') THEN
            RETURN NEW;
          END IF;
        ELSIF TG_TABLE_NAME = 'security_audit_events' THEN
          IF (NEW.actor_user_id IS NULL OR NEW.actor_user_id = OLD.actor_user_id)
             AND (NEW.subject_user_id IS NULL OR NEW.subject_user_id = OLD.subject_user_id)
             AND (to_jsonb(NEW) - ARRAY['actor_user_id','subject_user_id'])
                 = (to_jsonb(OLD) - ARRAY['actor_user_id','subject_user_id']) THEN
            RETURN NEW;
          END IF;
        END IF;
      END IF;
      RAISE EXCEPTION 'security audit events are immutable';
    END;
    $$;

    CREATE TRIGGER security_audit_events_immutable
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(`${APPLICATION_SCHEMA}.reject_security_audit_change`)}();

    CREATE TRIGGER login_audit_events_immutable
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(`${APPLICATION_SCHEMA}.reject_security_audit_change`)}();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS login_audit_events_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)};
    DROP TRIGGER IF EXISTS security_audit_events_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)};
    DROP FUNCTION IF EXISTS ${sql.raw(`${APPLICATION_SCHEMA}.reject_security_audit_change`)}();

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      DROP CONSTRAINT IF EXISTS privileged_audit_retention_check,
      DROP COLUMN IF EXISTS retain_until,
      DROP COLUMN IF EXISTS retention_policy_version;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)}
      DROP CONSTRAINT IF EXISTS login_audit_retention_check,
      DROP COLUMN IF EXISTS retain_until,
      DROP COLUMN IF EXISTS retention_policy_version;

    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.privacy_deletion_requests`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_artifacts`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.privacy_export_requests`)};
  `.execute(database);
}
