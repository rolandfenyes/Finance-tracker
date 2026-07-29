import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE INDEX journal_entries_user_period_reporting
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      (user_id, posted_on, economic_type, id)
      INCLUDE (category_id, note, effective_at, reverses_entry_id, source_module, source_reference_id);
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP INDEX IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.journal_entries_user_period_reporting`)};
  `.execute(database);
}
