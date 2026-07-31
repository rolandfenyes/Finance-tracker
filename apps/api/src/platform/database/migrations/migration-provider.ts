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
import * as emergencyReserve from './20260729100000_emergency_reserve';
import * as loans from './20260729110000_loans';
import * as genericInvestments from './20260729120000_generic_investments';
import * as securities from './20260729130000_securities';
import * as securitiesAccountGuardRevision from './20260729130100_securities_account_guard_revision';
import * as securitiesLedgerGuardRevision from './20260729130200_securities_ledger_guard_revision';
import * as adminFeedbackSystem from './20260729140000_admin_feedback_system';
import * as billing from './20260729150000_billing';
import * as notificationsEmail from './20260729160000_notifications_email';
import * as privacyAudit from './20260729170000_privacy_audit';
import * as legacyMigration from './20260729180000_legacy_migration';
import * as passkeySecurityAudit from './20260731120000_passkey_security_audit';
import * as smtpEmailProvider from './20260731130000_smtp_email_provider';

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
  '20260729100000_emergency_reserve': emergencyReserve,
  '20260729110000_loans': loans,
  '20260729120000_generic_investments': genericInvestments,
  '20260729130000_securities': securities,
  '20260729130100_securities_account_guard_revision': securitiesAccountGuardRevision,
  '20260729130200_securities_ledger_guard_revision': securitiesLedgerGuardRevision,
  '20260729140000_admin_feedback_system': adminFeedbackSystem,
  '20260729150000_billing': billing,
  '20260729160000_notifications_email': notificationsEmail,
  '20260729170000_privacy_audit': privacyAudit,
  '20260729180000_legacy_migration': legacyMigration,
  '20260731120000_passkey_security_audit': passkeySecurityAudit,
  '20260731130000_smtp_email_provider': smtpEmailProvider,
} satisfies Record<string, Migration>;

export class RegisteredMigrationProvider implements MigrationProvider {
  getMigrations(): Promise<Record<string, Migration>> {
    return Promise.resolve(registeredMigrations);
  }
}
