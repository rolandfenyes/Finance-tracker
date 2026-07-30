import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.feedback`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      kind varchar(16) NOT NULL,
      title varchar(200) NOT NULL,
      message varchar(10000) NOT NULL,
      severity varchar(16),
      status varchar(16) NOT NULL DEFAULT 'open',
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT feedback_kind_check CHECK (kind IN ('bug', 'idea')),
      CONSTRAINT feedback_title_check CHECK (char_length(btrim(title)) BETWEEN 1 AND 200),
      CONSTRAINT feedback_message_check CHECK (char_length(btrim(message)) BETWEEN 1 AND 10000),
      CONSTRAINT feedback_severity_check CHECK (
        severity IS NULL OR severity IN ('low', 'medium', 'high')
      ),
      CONSTRAINT feedback_status_check CHECK (
        status IN ('open', 'in_progress', 'resolved', 'closed')
      ),
      CONSTRAINT feedback_id_user_unique UNIQUE (id, user_id)
    );
    CREATE INDEX feedback_owner_page_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.feedback`)} (user_id, created_at DESC, id DESC);
    CREATE INDEX feedback_admin_page_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.feedback`)} (created_at DESC, id DESC);
    CREATE INDEX feedback_admin_filter_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.feedback`)} (status, kind, severity);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.feedback_responses`)} (
      id uuid PRIMARY KEY,
      feedback_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.feedback`)} (id) ON DELETE CASCADE,
      admin_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE RESTRICT,
      message varchar(10000) NOT NULL,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT feedback_responses_message_check CHECK (
        char_length(btrim(message)) BETWEEN 1 AND 10000
      )
    );
    CREATE INDEX feedback_responses_feedback_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.feedback_responses`)}
      (feedback_id, created_at, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.system_settings`)} (
      id smallint PRIMARY KEY DEFAULT 1,
      site_name varchar(160) NOT NULL DEFAULT 'MyMoneyMap',
      primary_url varchar(2048),
      support_email varchar(320),
      contact_email varchar(320),
      logo_url varchar(2048),
      favicon_url varchar(2048),
      maintenance_mode boolean NOT NULL DEFAULT false,
      maintenance_message varchar(1000),
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT system_settings_singleton_check CHECK (id = 1),
      CONSTRAINT system_settings_site_name_check CHECK (
        char_length(btrim(site_name)) BETWEEN 1 AND 160
      ),
      CONSTRAINT system_settings_maintenance_message_check CHECK (
        maintenance_message IS NULL
        OR char_length(btrim(maintenance_message)) BETWEEN 1 AND 1000
      )
    );

    INSERT INTO ${sql.table(`${APPLICATION_SCHEMA}.system_settings`)}
      (id, created_at, updated_at)
    VALUES (1, now(), now());

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.api_integrations`)} (
      id uuid PRIMARY KEY,
      name varchar(160) NOT NULL,
      service varchar(64) NOT NULL UNIQUE,
      api_key_encrypted text NOT NULL,
      status varchar(16) NOT NULL,
      metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
      last_used_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT api_integrations_name_check CHECK (
        char_length(btrim(name)) BETWEEN 1 AND 160
      ),
      CONSTRAINT api_integrations_service_check CHECK (
        service ~ '^[a-z][a-z0-9_-]{1,63}$'
      ),
      CONSTRAINT api_integrations_ciphertext_check CHECK (
        char_length(api_key_encrypted) >= 32
      ),
      CONSTRAINT api_integrations_status_check CHECK (status IN ('active', 'inactive')),
      CONSTRAINT api_integrations_metadata_object_check CHECK (
        jsonb_typeof(metadata) = 'object'
      )
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.account_recovery_requests`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      requested_by_admin_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE RESTRICT,
      kind varchar(32) NOT NULL,
      token_hash char(64) NOT NULL UNIQUE,
      pending_email varchar(320),
      expires_at timestamptz NOT NULL,
      consumed_at timestamptz,
      created_at timestamptz NOT NULL,
      CONSTRAINT account_recovery_kind_check CHECK (
        kind IN ('password_reset', 'email_change')
      ),
      CONSTRAINT account_recovery_pending_email_check CHECK (
        (kind = 'password_reset' AND pending_email IS NULL)
        OR (kind = 'email_change' AND pending_email IS NOT NULL)
      ),
      CONSTRAINT account_recovery_token_hash_check CHECK (
        token_hash ~ '^[0-9a-f]{64}$'
      ),
      CONSTRAINT account_recovery_expiry_check CHECK (expires_at > created_at),
      CONSTRAINT account_recovery_consumed_check CHECK (
        consumed_at IS NULL OR consumed_at >= created_at
      )
    );
    CREATE UNIQUE INDEX account_recovery_one_pending_kind_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.account_recovery_requests`)} (user_id, kind)
      WHERE consumed_at IS NULL;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)} (
      id uuid PRIMARY KEY,
      actor_user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE RESTRICT,
      action varchar(80) NOT NULL,
      target_type varchar(40) NOT NULL,
      target_id varchar(160),
      details jsonb NOT NULL DEFAULT '{}'::jsonb,
      created_at timestamptz NOT NULL,
      CONSTRAINT privileged_audit_action_check CHECK (
        action IN (
          'feedback.updated',
          'feedback.responded',
          'system.settings_updated',
          'integration.upserted',
          'integration.deleted',
          'user.role_updated',
          'user.status_updated',
          'user.password_reset_requested',
          'user.email_verification_requested',
          'user.email_change_requested'
        )
      ),
      CONSTRAINT privileged_audit_target_type_check CHECK (
        target_type IN ('feedback', 'system_settings', 'integration', 'user')
      ),
      CONSTRAINT privileged_audit_details_object_check CHECK (
        jsonb_typeof(details) = 'object'
      )
    );
    CREATE INDEX privileged_audit_actor_page_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      (actor_user_id, created_at DESC, id DESC);

    CREATE OR REPLACE FUNCTION ${sql.raw(`${APPLICATION_SCHEMA}.reject_privileged_audit_change`)}()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      RAISE EXCEPTION 'privileged audit events are immutable';
    END;
    $$;

    CREATE TRIGGER privileged_audit_events_immutable
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(`${APPLICATION_SCHEMA}.reject_privileged_audit_change`)}();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS privileged_audit_events_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)};
    DROP FUNCTION IF EXISTS ${sql.raw(`${APPLICATION_SCHEMA}.reject_privileged_audit_change`)}();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.account_recovery_requests`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.api_integrations`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.system_settings`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.feedback_responses`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.feedback`)};
  `.execute(database);
}
