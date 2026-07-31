import { ConfigService } from '@nestjs/config';
import { createHash, randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { FixedClock } from '../src/platform/time/clock';
import { UtcInstant } from '../src/platform/time/utc-instant';
import type { PrivateObjectStorage } from '../src/privacy/private-object-storage';
import { PrivacyDeletionProcessor } from '../src/privacy/privacy-deletion.service';
import {
  PrivacyExportBuilder,
  PrivacyExportProcessor,
  PrivacyExportService,
} from '../src/privacy/privacy-export.service';
import {
  DATABASE_LIFECYCLE_MANIFEST,
  EXPORT_DATASETS,
  PRIVACY_MANIFEST_VERSION,
} from '../src/privacy/privacy-manifest';
import { PrivacyRepository } from '../src/privacy/privacy.repository';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { rollbackMigrationsAfter, withIsolatedPostgresDatabase } from './postgres-test-database';

jest.setTimeout(30_000);
const NOW = new Date('2026-07-30T10:00:00.000Z');

describe('privacy, export, deletion, and audit PostgreSQL contract', () => {
  const now = NOW;
  const clock = new FixedClock(UtcInstant.fromDate(NOW));

  it('classifies the live schema and keeps security and privileged audit rows immutable', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const tables = (
        await pool.query<{ tablename: string }>(
          `SELECT tablename FROM pg_tables
           WHERE schemaname='mymoneymap' ORDER BY tablename`,
        )
      ).rows.map(({ tablename }) => tablename);
      expect(DATABASE_LIFECYCLE_MANIFEST.map(({ table }) => table).sort()).toEqual(tables);

      const adminId = randomUUID();
      await insertUser(pool, adminId, `privacy-admin-${adminId}@example.test`, 'admin');
      const loginId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.login_audit_events
         (id,user_id,email_hash,outcome,method,ip_hash,user_agent_hash,created_at)
         VALUES($1,$2,$3,'success','password',$3,$3,$4)`,
        [loginId, adminId, 'a'.repeat(64), now],
      );
      const privilegedId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.privileged_audit_events
         (id,actor_user_id,action,target_type,target_id,details,created_at)
         VALUES($1,$2,'system.settings_updated','system_settings',NULL,'{}',$3)`,
        [privilegedId, adminId, now],
      );
      const securityId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.security_audit_events
         (id,actor_user_id,subject_user_id,subject_hash,action,target_type,target_id,details,created_at)
         VALUES($1,$2,$2,$3,'privacy.export_requested','privacy_export',$4,'{}',$5)`,
        [securityId, adminId, 'b'.repeat(64), randomUUID(), now],
      );
      await expect(
        pool.query('DELETE FROM mymoneymap.login_audit_events WHERE id=$1', [loginId]),
      ).rejects.toThrow('security audit events are immutable');
      await expect(
        pool.query('UPDATE mymoneymap.security_audit_events SET target_id=NULL WHERE id=$1', [
          securityId,
        ]),
      ).rejects.toThrow('security audit events are immutable');
      await expect(
        pool.query('DELETE FROM mymoneymap.privileged_audit_events WHERE id=$1', [privilegedId]),
      ).rejects.toThrow('privileged audit events are immutable');
      expect(
        (
          await pool.query(
            `SELECT retention_policy_version,retain_until
             FROM mymoneymap.security_audit_events WHERE id=$1`,
            [securityId],
          )
        ).rows[0],
      ).toEqual({ retention_policy_version: null, retain_until: null });
    });
  });

  it('exports every manifest dataset, excludes secrets, and is processor-idempotent', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = randomUUID();
      const otherId = randomUUID();
      await insertUser(pool, userId, `privacy-export-${userId}@example.test`);
      await insertUser(pool, otherId, `privacy-other-${otherId}@example.test`);
      await pool.query(
        `INSERT INTO mymoneymap.email_verification_tokens
         (id,user_id,token_hash,expires_at,created_at)
         VALUES($1,$2,$3,$4,$5)`,
        [randomUUID(), userId, 'c'.repeat(64), new Date(now.getTime() + 60_000), now],
      );
      await pool.query(
        `INSERT INTO mymoneymap.passkeys
         (id,user_id,credential_id,public_key,counter,revision,transports,device_type,backed_up,
          label,created_at)
         VALUES($1,$2,$3,$4,0,0,'{}','singleDevice',false,'Synthetic',$5)`,
        [randomUUID(), userId, 'credential-secret', Buffer.from('public-key'), now],
      );
      await pool.query(
        `INSERT INTO mymoneymap.user_email_preferences
         (user_id,educational_enabled,updated_at) VALUES($1,false,$2)`,
        [userId, now],
      );

      const repository = new PrivacyRepository(pool);
      const request = await repository.createExportRequest({
        userId,
        manifestVersion: PRIVACY_MANIFEST_VERSION,
        idempotencyKeyHash: createHash('sha256').update('export-stable-key').digest('hex'),
        now,
      });
      const storage = new MemoryStorage();
      const config = new ConfigService({
        PRIVACY_EXPORT_EXPIRY_SECONDS: 3600,
        PRIVACY_EXPORT_SIGNED_URL_SECONDS: 60,
        PRIVACY_EXPORTS_ENABLED: true,
      });
      const processor = new PrivacyExportProcessor(
        config,
        repository,
        new PrivacyExportBuilder(),
        storage,
        clock,
      );
      await processor.process(request.id, 1);
      const firstPutCount = storage.objects.size;
      await processor.process(request.id, 2);
      expect(storage.objects.size).toBe(firstPutCount);

      const complete = [...storage.objects.entries()].find(([key]) =>
        key.endsWith('/complete_export.json'),
      );
      expect(complete).toBeDefined();
      const serialized = Buffer.from(complete![1]).toString('utf8');
      const payload = JSON.parse(serialized) as {
        manifestVersion: number;
        datasets: Record<string, unknown[]>;
      };
      expect(payload.manifestVersion).toBe(PRIVACY_MANIFEST_VERSION);
      expect(Object.keys(payload.datasets).sort()).toEqual(
        EXPORT_DATASETS.map(({ key }) => key).sort(),
      );
      expect(serialized).not.toContain('stored-password-hash');
      expect(serialized).not.toContain('credential-secret');
      expect(serialized).not.toContain('public-key');
      expect(serialized).not.toContain('c'.repeat(64));
      expect(payload.datasets.email_preferences).toEqual([
        { educational_enabled: false, updated_at: now.toISOString() },
      ]);

      const service = new PrivacyExportService(config, repository, storage, clock);
      await expect(service.status(otherId, request.id)).rejects.toMatchObject({
        code: 'NOT_FOUND',
      });
      await expect(service.status(userId, request.id)).resolves.toMatchObject({
        status: 'completed',
        artifacts: expect.arrayContaining([
          expect.objectContaining({ dataset: 'complete_export', format: 'json' }),
        ]),
      });
    });
  });

  it('deletes owned database data, retains pseudonymous suppression/audit, and is retry-safe', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = randomUUID();
      const email = `privacy-delete-${userId}@example.test`;
      await insertUser(pool, userId, email);
      await pool.query(
        `INSERT INTO mymoneymap.feedback
         (id,user_id,kind,title,message,status,created_at,updated_at)
         VALUES($1,$2,'bug','Synthetic','Synthetic feedback','open',$3,$3)`,
        [randomUUID(), userId, now],
      );
      await pool.query(
        `INSERT INTO mymoneymap.user_email_preferences(user_id,educational_enabled,updated_at)
         VALUES($1,false,$2)`,
        [userId, now],
      );
      const suppressionHash = createHash('sha256').update(email).digest('hex');
      await pool.query(
        `INSERT INTO mymoneymap.email_suppressions
         (email_hash,reason,provider,created_at) VALUES($1,'manual','internal',$2)`,
        [suppressionHash, now],
      );
      const loginId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.login_audit_events
         (id,user_id,email_hash,outcome,method,ip_hash,user_agent_hash,created_at)
         VALUES($1,$2,$3,'success','password',$3,$3,$4)`,
        [loginId, userId, 'd'.repeat(64), now],
      );
      const repository = new PrivacyRepository(pool);
      const deletion = await repository.createDeletionRequest({
        userId,
        idempotencyKeyHash: createHash('sha256').update('delete-stable-key').digest('hex'),
        now,
      });
      const cleanup = { cleanup: jest.fn().mockResolvedValue(undefined) };
      const processor = new PrivacyDeletionProcessor(repository, cleanup as never, clock);
      await processor.process(deletion.id, 1);
      await processor.process(deletion.id, 2);
      expect(cleanup.cleanup).toHaveBeenCalledTimes(1);
      expect(
        (await pool.query('SELECT 1 FROM mymoneymap.users WHERE id=$1', [userId])).rowCount,
      ).toBe(0);
      expect(
        (await pool.query('SELECT 1 FROM mymoneymap.feedback WHERE user_id=$1', [userId])).rowCount,
      ).toBe(0);
      expect(
        (
          await pool.query('SELECT 1 FROM mymoneymap.email_suppressions WHERE email_hash=$1', [
            suppressionHash,
          ])
        ).rowCount,
      ).toBe(1);
      expect(
        (
          await pool.query<{ user_id: string | null }>(
            'SELECT user_id FROM mymoneymap.login_audit_events WHERE id=$1',
            [loginId],
          )
        ).rows[0]?.user_id,
      ).toBeNull();
      expect(
        (
          await pool.query<{ user_id: string | null; status: string }>(
            'SELECT user_id,status FROM mymoneymap.privacy_deletion_requests WHERE id=$1',
            [deletion.id],
          )
        ).rows[0],
      ).toEqual({ user_id: null, status: 'completed' });
    });
  });

  it('persists bounded retry and dead-letter state without deleting data on failure', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const userId = randomUUID();
      await insertUser(pool, userId, `privacy-retry-${userId}@example.test`);
      const repository = new PrivacyRepository(pool);
      const exportRequest = await repository.createExportRequest({
        userId,
        manifestVersion: PRIVACY_MANIFEST_VERSION,
        idempotencyKeyHash: createHash('sha256').update('export-retry-key').digest('hex'),
        now,
      });
      const failingStorage: PrivateObjectStorage = {
        put: jest.fn().mockRejectedValue(new Error('synthetic_storage_failure')),
        delete: jest.fn().mockResolvedValue(undefined),
        signedGetUrl: jest.fn().mockRejectedValue(new Error('not_used')),
      };
      const config = new ConfigService({
        PRIVACY_EXPORT_EXPIRY_SECONDS: 3600,
        PRIVACY_EXPORT_SIGNED_URL_SECONDS: 60,
        PRIVACY_EXPORTS_ENABLED: true,
      });
      const exportProcessor = new PrivacyExportProcessor(
        config,
        repository,
        new PrivacyExportBuilder(),
        failingStorage,
        clock,
      );

      await expect(exportProcessor.process(exportRequest.id, 1)).rejects.toThrow(
        'synthetic_storage_failure',
      );
      await expect(exportProcessor.process(exportRequest.id, 3)).rejects.toThrow(
        'synthetic_storage_failure',
      );
      await expect(repository.exportRequest(exportRequest.id)).resolves.toMatchObject({
        status: 'dead_letter',
        attempt_count: 3,
      });

      const deletion = await repository.createDeletionRequest({
        userId,
        idempotencyKeyHash: createHash('sha256').update('deletion-retry-key').digest('hex'),
        now,
      });
      const cleanup = {
        cleanup: jest.fn().mockRejectedValue(new Error('synthetic_queue_job_failure')),
      };
      const deletionProcessor = new PrivacyDeletionProcessor(repository, cleanup as never, clock);
      await expect(deletionProcessor.process(deletion.id, 1)).rejects.toThrow(
        'synthetic_queue_job_failure',
      );
      await expect(deletionProcessor.process(deletion.id, 3)).rejects.toThrow(
        'synthetic_queue_job_failure',
      );
      await expect(repository.deletionRequest(deletion.id)).resolves.toMatchObject({
        status: 'dead_letter',
        attempt_count: 3,
      });
      expect(
        (await pool.query('SELECT 1 FROM mymoneymap.users WHERE id=$1', [userId])).rowCount,
      ).toBe(1);
      expect(
        (
          await pool.query<{ action: string }>(
            `SELECT action FROM mymoneymap.security_audit_events
             WHERE target_id IN ($1,$2) AND action IN ('privacy.export_failed','privacy.deletion_failed')
             ORDER BY action`,
            [exportRequest.id, deletion.id],
          )
        ).rows.map(({ action }) => action),
      ).toEqual(['privacy.deletion_failed', 'privacy.export_failed']);
    });
  });

  it('rolls back and deterministically reapplies Step 19', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      await rollbackMigrationsAfter(database, '20260729160000_notifications_email');
      expect(
        (
          await pool.query<{ relation: string | null }>(
            `SELECT to_regclass('mymoneymap.privacy_export_requests')::text AS relation`,
          )
        ).rows[0]?.relation,
      ).toBeNull();
      await migrateToLatest(database);
      expect(
        (
          await pool.query<{ relation: string | null }>(
            `SELECT to_regclass('mymoneymap.security_audit_events')::text AS relation`,
          )
        ).rows[0]?.relation,
      ).toBe('mymoneymap.security_audit_events');
    });
  });
});

class MemoryStorage implements PrivateObjectStorage {
  readonly objects = new Map<string, Uint8Array>();

  put(input: { key: string; body: Uint8Array }): Promise<void> {
    this.objects.set(input.key, input.body);
    return Promise.resolve();
  }

  delete(key: string): Promise<void> {
    this.objects.delete(key);
    return Promise.resolve();
  }

  signedGetUrl(key: string): Promise<string> {
    return Promise.resolve(`https://private.example.test/${encodeURIComponent(key)}`);
  }
}

async function insertUser(pool: Pool, id: string, email: string, role = 'free'): Promise<void> {
  await pool.query(
    `INSERT INTO mymoneymap.users
     (id,email,password_hash,full_name,date_of_birth,role,status,email_verified_at,created_at,
      updated_at,theme,desired_language,onboard_step,needs_tutorial,tutorial_seen)
     VALUES($1,$2,'stored-password-hash','Synthetic User','1990-01-01',$3,'active',$4,$4,$4,
            'verdant-horizon','en',1,false,true)`,
    [id, email, role, NOW],
  );
}
