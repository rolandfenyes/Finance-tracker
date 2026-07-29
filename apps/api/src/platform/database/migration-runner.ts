import { type Kysely, Migrator, type MigrationInfo, type MigrationResultSet } from 'kysely';
import { MIGRATION_LOCK_TABLE, MIGRATION_SCHEMA, MIGRATION_TABLE } from './database.constants';
import type { DatabaseSchema } from './database.types';
import { RegisteredMigrationProvider } from './migrations/migration-provider';

export function createMigrator(database: Kysely<DatabaseSchema>): Migrator {
  return new Migrator({
    db: database,
    provider: new RegisteredMigrationProvider(),
    migrationTableName: MIGRATION_TABLE,
    migrationLockTableName: MIGRATION_LOCK_TABLE,
    migrationTableSchema: MIGRATION_SCHEMA,
    allowUnorderedMigrations: false,
  });
}

export async function migrateToLatest(
  database: Kysely<DatabaseSchema>,
): Promise<MigrationResultSet> {
  return assertMigrationSuccess(await createMigrator(database).migrateToLatest());
}

export async function migrateOneDown(
  database: Kysely<DatabaseSchema>,
): Promise<MigrationResultSet> {
  return assertMigrationSuccess(await createMigrator(database).migrateDown());
}

export function getMigrationStatus(database: Kysely<DatabaseSchema>): Promise<MigrationInfo[]> {
  return createMigrator(database).getMigrations() as Promise<MigrationInfo[]>;
}

function assertMigrationSuccess(result: MigrationResultSet): MigrationResultSet {
  if (result.error) {
    const failed = result.results?.find((migration) => migration.status === 'Error');
    throw new Error(
      failed
        ? `Database migration failed: ${failed.migrationName}`
        : 'Database migration failed before execution',
      { cause: result.error },
    );
  }

  return result;
}
