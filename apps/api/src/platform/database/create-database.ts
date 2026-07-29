import { Kysely, PostgresDialect } from 'kysely';
import { Pool } from 'pg';
import type { DatabaseSchema } from './database.types';
import { createPostgresPoolConfig, type PostgresConnectionPolicy } from './postgres-config';

export function createDatabase(policy: PostgresConnectionPolicy): {
  database: Kysely<DatabaseSchema>;
  pool: Pool;
} {
  const pool = new Pool(createPostgresPoolConfig(policy));
  const database = new Kysely<DatabaseSchema>({ dialect: new PostgresDialect({ pool }) });

  return { database, pool };
}
