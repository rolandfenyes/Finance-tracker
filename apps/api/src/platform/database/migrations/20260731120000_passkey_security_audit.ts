import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)}
      ADD CONSTRAINT passkeys_device_type_check
        CHECK (device_type IN ('singleDevice','multiDevice')),
      ADD CONSTRAINT passkeys_transports_check
        CHECK (
          transports <@ ARRAY['ble','cable','hybrid','internal','nfc','smart-card','usb']::text[]
        );

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      DROP CONSTRAINT security_audit_action_check,
      DROP CONSTRAINT security_audit_target_type_check;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      ADD CONSTRAINT security_audit_action_check CHECK (
        action IN (
          'privacy.export_requested',
          'privacy.export_completed',
          'privacy.export_failed',
          'privacy.export_accessed',
          'privacy.deletion_requested',
          'privacy.deletion_completed',
          'privacy.deletion_failed',
          'passkey.registered',
          'passkey.deleted'
        )
      ),
      ADD CONSTRAINT security_audit_target_type_check CHECK (
        target_type IN ('privacy_export','privacy_deletion','user','passkey')
      )
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      DISABLE TRIGGER security_audit_events_immutable;
    DELETE FROM ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      WHERE action IN ('passkey.registered','passkey.deleted');
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      ENABLE TRIGGER security_audit_events_immutable;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      DROP CONSTRAINT security_audit_action_check,
      DROP CONSTRAINT security_audit_target_type_check;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.security_audit_events`)}
      ADD CONSTRAINT security_audit_action_check CHECK (
        action IN (
          'privacy.export_requested',
          'privacy.export_completed',
          'privacy.export_failed',
          'privacy.export_accessed',
          'privacy.deletion_requested',
          'privacy.deletion_completed',
          'privacy.deletion_failed'
        )
      ),
      ADD CONSTRAINT security_audit_target_type_check CHECK (
        target_type IN ('privacy_export','privacy_deletion','user')
      );

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.passkeys`)}
      DROP CONSTRAINT IF EXISTS passkeys_transports_check,
      DROP CONSTRAINT IF EXISTS passkeys_device_type_check
  `.execute(database);
}
