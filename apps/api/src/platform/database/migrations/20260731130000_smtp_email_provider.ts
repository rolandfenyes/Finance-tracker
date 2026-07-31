import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)}
      DROP CONSTRAINT email_channel_provider_check;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)}
      ADD CONSTRAINT email_channel_provider_check
      CHECK (provider IN ('disabled','log','postmark','smtp'));
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    UPDATE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)}
      SET enabled=false, provider='disabled'
      WHERE provider='smtp';
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)}
      DROP CONSTRAINT email_channel_provider_check;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.email_channel_settings`)}
      ADD CONSTRAINT email_channel_provider_check
      CHECK (provider IN ('disabled','log','postmark'));
  `.execute(database);
}
