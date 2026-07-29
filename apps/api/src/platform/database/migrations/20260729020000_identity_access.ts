import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.users`)} (
      id uuid PRIMARY KEY,
      email varchar(320) NOT NULL,
      password_hash text NOT NULL,
      full_name varchar(200) NOT NULL,
      date_of_birth date NOT NULL,
      role varchar(16) NOT NULL DEFAULT 'free',
      status varchar(16) NOT NULL DEFAULT 'active',
      email_verified_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT users_email_normalized_check CHECK (email = lower(btrim(email))),
      CONSTRAINT users_role_check CHECK (role IN ('free', 'premium', 'admin')),
      CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive')),
      CONSTRAINT users_name_check CHECK (char_length(btrim(full_name)) BETWEEN 1 AND 200)
    );
    CREATE UNIQUE INDEX users_email_unique ON ${sql.table(`${APPLICATION_SCHEMA}.users`)} (email);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_verification_tokens`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      token_hash char(64) NOT NULL UNIQUE,
      expires_at timestamptz NOT NULL,
      consumed_at timestamptz,
      created_at timestamptz NOT NULL,
      CONSTRAINT email_verification_expiry_check CHECK (expires_at > created_at),
      CONSTRAINT email_verification_consumed_check CHECK (consumed_at IS NULL OR consumed_at >= created_at)
    );
    CREATE UNIQUE INDEX email_verification_one_live_token
      ON ${sql.table(`${APPLICATION_SCHEMA}.email_verification_tokens`)} (user_id)
      WHERE consumed_at IS NULL;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      credential_id text NOT NULL UNIQUE,
      public_key bytea NOT NULL,
      counter bigint NOT NULL,
      transports text[] NOT NULL DEFAULT '{}',
      device_type varchar(32) NOT NULL,
      backed_up boolean NOT NULL,
      label varchar(100) NOT NULL,
      created_at timestamptz NOT NULL,
      last_used_at timestamptz,
      CONSTRAINT passkeys_counter_check CHECK (counter >= 0),
      CONSTRAINT passkeys_label_check CHECK (char_length(btrim(label)) BETWEEN 1 AND 100)
    );
    CREATE INDEX passkeys_user_id_index ON ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)} (user_id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)} (
      id uuid PRIMARY KEY,
      user_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE SET NULL,
      email_hash char(64) NOT NULL,
      outcome varchar(16) NOT NULL,
      method varchar(16) NOT NULL,
      ip_hash char(64) NOT NULL,
      user_agent_hash char(64) NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT login_audit_outcome_check CHECK (outcome IN ('success', 'failure', 'throttled')),
      CONSTRAINT login_audit_method_check CHECK (method IN ('password', 'passkey'))
    );
    CREATE INDEX login_audit_created_at_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.login_audit_events`)} (created_at);
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await database.schema
    .withSchema(APPLICATION_SCHEMA)
    .dropTable('login_audit_events')
    .ifExists()
    .execute();
  await database.schema.withSchema(APPLICATION_SCHEMA).dropTable('passkeys').ifExists().execute();
  await database.schema
    .withSchema(APPLICATION_SCHEMA)
    .dropTable('email_verification_tokens')
    .ifExists()
    .execute();
  await database.schema.withSchema(APPLICATION_SCHEMA).dropTable('users').ifExists().execute();
}
