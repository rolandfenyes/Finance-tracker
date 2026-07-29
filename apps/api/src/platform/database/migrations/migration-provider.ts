import type { Migration, MigrationProvider } from 'kysely';
import * as databaseBaseline from './20260729000000_database_baseline';
import * as idempotencyKeys from './20260729010000_idempotency_keys';
import * as identityAccess from './20260729020000_identity_access';
import * as passkeyRevision from './20260729020100_passkey_revision';
import * as usersSettings from './20260729030000_users_settings';
import * as ledgerJournal from './20260729040000_ledger_journal';
import * as currencyFx from './20260729050000_currency_fx';
import * as fxSnapshotInvariant from './20260729050100_fx_snapshot_invariant';
import * as budgetingCategoriesIncome from './20260729060000_budgeting_categories_income';
import * as recurrenceScheduling from './20260729070000_recurrence_scheduling';
import * as reportingIndexes from './20260729080000_reporting_indexes';
import * as goals from './20260729090000_goals';

export const registeredMigrations = {
  '20260729000000_database_baseline': databaseBaseline,
  '20260729010000_idempotency_keys': idempotencyKeys,
  '20260729020000_identity_access': identityAccess,
  '20260729020100_passkey_revision': passkeyRevision,
  '20260729030000_users_settings': usersSettings,
  '20260729040000_ledger_journal': ledgerJournal,
  '20260729050000_currency_fx': currencyFx,
  '20260729050100_fx_snapshot_invariant': fxSnapshotInvariant,
  '20260729060000_budgeting_categories_income': budgetingCategoriesIncome,
  '20260729070000_recurrence_scheduling': recurrenceScheduling,
  '20260729080000_reporting_indexes': reportingIndexes,
  '20260729090000_goals': goals,
} satisfies Record<string, Migration>;

export class RegisteredMigrationProvider implements MigrationProvider {
  getMigrations(): Promise<Record<string, Migration>> {
    return Promise.resolve(registeredMigrations);
  }
}
