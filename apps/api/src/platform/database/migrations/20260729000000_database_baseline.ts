import type { Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await database.schema.createSchema(APPLICATION_SCHEMA).ifNotExists().execute();
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await database.schema.dropSchema(APPLICATION_SCHEMA).ifExists().execute();
}
