import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

const TABLE = `${APPLICATION_SCHEMA}.idempotency_keys`;

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(TABLE)} (
      scope_id uuid NOT NULL,
      operation varchar(128) NOT NULL,
      key_hash char(64) NOT NULL,
      request_hash char(64) NOT NULL,
      status varchar(16) NOT NULL,
      response jsonb,
      created_at timestamptz NOT NULL,
      completed_at timestamptz,
      CONSTRAINT idempotency_keys_pkey PRIMARY KEY (scope_id, operation, key_hash),
      CONSTRAINT idempotency_keys_operation_check
        CHECK (operation ~ '^[a-z][a-z0-9._:-]{0,127}$'),
      CONSTRAINT idempotency_keys_key_hash_check
        CHECK (key_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT idempotency_keys_request_hash_check
        CHECK (request_hash ~ '^[0-9a-f]{64}$'),
      CONSTRAINT idempotency_keys_status_check
        CHECK (status IN ('in_progress', 'completed')),
      CONSTRAINT idempotency_keys_completion_check
        CHECK (
          (status = 'in_progress' AND response IS NULL AND completed_at IS NULL)
          OR
          (
            status = 'completed'
            AND response IS NOT NULL
            AND jsonb_typeof(response) = 'object'
            AND completed_at IS NOT NULL
          )
        )
    )
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await database.schema
    .withSchema(APPLICATION_SCHEMA)
    .dropTable('idempotency_keys')
    .ifExists()
    .execute();
}
