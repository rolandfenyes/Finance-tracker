import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_templates`)} (
      id uuid PRIMARY KEY,
      code varchar(80) NOT NULL,
      version integer NOT NULL,
      locale varchar(5) NOT NULL,
      name varchar(160) NOT NULL,
      subject varchar(255) NOT NULL,
      body text NOT NULL,
      data_contract jsonb NOT NULL,
      active boolean NOT NULL DEFAULT true,
      last_tested_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT email_templates_code_check CHECK (code ~ '^[a-z][a-z0-9_]{0,79}$'),
      CONSTRAINT email_templates_version_check CHECK (version > 0),
      CONSTRAINT email_templates_locale_check CHECK (locale IN ('en','es','hu')),
      CONSTRAINT email_templates_text_check CHECK (
        char_length(btrim(name)) BETWEEN 1 AND 160
        AND char_length(btrim(subject)) BETWEEN 1 AND 255
        AND char_length(btrim(body)) > 0
      ),
      CONSTRAINT email_templates_contract_check CHECK (jsonb_typeof(data_contract) = 'array'),
      CONSTRAINT email_templates_identity_unique UNIQUE (code,version,locale)
    );
    CREATE UNIQUE INDEX email_templates_active_unique
      ON ${sql.table(`${APPLICATION_SCHEMA}.email_templates`)} (code,locale) WHERE active;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)} (
      id smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
      enabled boolean NOT NULL DEFAULT false,
      provider varchar(16) NOT NULL DEFAULT 'disabled',
      from_address varchar(320),
      reply_to_address varchar(320),
      updated_by uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT email_channel_provider_check CHECK (provider IN ('disabled','log','postmark')),
      CONSTRAINT email_channel_enabled_check CHECK (NOT enabled OR provider <> 'disabled')
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.user_email_preferences`)} (
      user_id uuid PRIMARY KEY REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      educational_enabled boolean NOT NULL DEFAULT true,
      updated_at timestamptz NOT NULL
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_deliveries`)} (
      id uuid PRIMARY KEY,
      event_key varchar(255) NOT NULL UNIQUE,
      correlation_id uuid NOT NULL,
      user_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      recipient_email varchar(320) NOT NULL,
      template_code varchar(80) NOT NULL,
      template_version integer NOT NULL,
      locale varchar(5) NOT NULL,
      classification varchar(16) NOT NULL,
      template_data jsonb NOT NULL,
      provenance jsonb NOT NULL DEFAULT '{}'::jsonb,
      status varchar(24) NOT NULL,
      queue_job_id varchar(255),
      attempt_count integer NOT NULL DEFAULT 0,
      max_attempts integer NOT NULL DEFAULT 3,
      provider_message_id varchar(255),
      error_code varchar(80),
      created_at timestamptz NOT NULL,
      queued_at timestamptz,
      started_at timestamptz,
      delivered_at timestamptz,
      failed_at timestamptz,
      CONSTRAINT email_deliveries_locale_check CHECK (locale IN ('en','es','hu')),
      CONSTRAINT email_deliveries_classification_check CHECK (classification IN ('transactional','educational')),
      CONSTRAINT email_deliveries_status_check CHECK (
        status IN ('queued','running','delivered','suppressed','preference_blocked','retryable_failed','dead_letter','disabled')
      ),
      CONSTRAINT email_deliveries_data_check CHECK (jsonb_typeof(template_data) = 'object'),
      CONSTRAINT email_deliveries_provenance_check CHECK (jsonb_typeof(provenance) = 'object'),
      CONSTRAINT email_deliveries_attempt_check CHECK (
        attempt_count >= 0 AND max_attempts BETWEEN 1 AND 10 AND attempt_count <= max_attempts
      )
    );
    CREATE INDEX email_deliveries_user_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.email_deliveries`)} (user_id,created_at DESC,id DESC);
    CREATE INDEX email_deliveries_status_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.email_deliveries`)} (status,created_at);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_suppressions`)} (
      email_hash char(64) PRIMARY KEY,
      reason varchar(32) NOT NULL,
      provider varchar(16) NOT NULL,
      provider_event_id varchar(255),
      created_at timestamptz NOT NULL,
      CONSTRAINT email_suppressions_reason_check CHECK (reason IN ('hard_bounce','spam_complaint','manual')),
      CONSTRAINT email_suppressions_provider_check CHECK (provider IN ('postmark','internal'))
    );
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.email_suppressions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.email_deliveries`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.user_email_preferences`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.email_templates`)};
  `.execute(database);
}
