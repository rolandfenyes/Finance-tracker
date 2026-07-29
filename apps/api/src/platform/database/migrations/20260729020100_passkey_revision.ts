import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)}
      ADD COLUMN revision bigint NOT NULL DEFAULT 0,
      ADD CONSTRAINT passkeys_revision_check CHECK (revision >= 0)
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)}
      DROP CONSTRAINT IF EXISTS passkeys_revision_check,
      DROP COLUMN IF EXISTS revision
  `.execute(database);
}
