import type { Migration, MigrationProvider } from 'kysely';
import * as databaseBaseline from './20260729000000_database_baseline';
import * as idempotencyKeys from './20260729010000_idempotency_keys';

export const registeredMigrations = {
  '20260729000000_database_baseline': databaseBaseline,
  '20260729010000_idempotency_keys': idempotencyKeys,
} satisfies Record<string, Migration>;

export class RegisteredMigrationProvider implements MigrationProvider {
  getMigrations(): Promise<Record<string, Migration>> {
    return Promise.resolve(registeredMigrations);
  }
}
