import type { Migration, MigrationProvider } from 'kysely';
import * as databaseBaseline from './20260729000000_database_baseline';
import * as idempotencyKeys from './20260729010000_idempotency_keys';
import * as identityAccess from './20260729020000_identity_access';
import * as passkeyRevision from './20260729020100_passkey_revision';
import * as usersSettings from './20260729030000_users_settings';
import * as ledgerJournal from './20260729040000_ledger_journal';

export const registeredMigrations = {
  '20260729000000_database_baseline': databaseBaseline,
  '20260729010000_idempotency_keys': idempotencyKeys,
  '20260729020000_identity_access': identityAccess,
  '20260729020100_passkey_revision': passkeyRevision,
  '20260729030000_users_settings': usersSettings,
  '20260729040000_ledger_journal': ledgerJournal,
} satisfies Record<string, Migration>;

export class RegisteredMigrationProvider implements MigrationProvider {
  getMigrations(): Promise<Record<string, Migration>> {
    return Promise.resolve(registeredMigrations);
  }
}
