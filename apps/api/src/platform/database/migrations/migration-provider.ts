import type { Migration, MigrationProvider } from 'kysely';
import * as databaseBaseline from './20260729000000_database_baseline';

export const registeredMigrations = {
  '20260729000000_database_baseline': databaseBaseline,
} satisfies Record<string, Migration>;

export class RegisteredMigrationProvider implements MigrationProvider {
  getMigrations(): Promise<Record<string, Migration>> {
    return Promise.resolve(registeredMigrations);
  }
}
