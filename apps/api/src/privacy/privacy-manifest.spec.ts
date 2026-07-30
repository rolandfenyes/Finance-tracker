import expectedSchema from '../platform/database/expected-schema.json';
import {
  DATABASE_LIFECYCLE_MANIFEST,
  EXPORT_DATASETS,
  NON_DATABASE_LIFECYCLE_MANIFEST,
} from './privacy-manifest';

describe('versioned privacy lifecycle manifest', () => {
  it('classifies every application table exactly once', () => {
    const schemaTables = expectedSchema.relations
      .filter(({ schema, kind }) => schema === 'mymoneymap' && kind === 'r')
      .map(({ name }) => name)
      .sort();
    const manifestTables = DATABASE_LIFECYCLE_MANIFEST.map(({ table }) => table).sort();
    expect(new Set(manifestTables).size).toBe(manifestTables.length);
    expect(manifestTables).toEqual(schemaTables);
  });

  it('links every export classification to a safe-column dataset', () => {
    const datasets = new Set(EXPORT_DATASETS.map(({ key }) => key));
    for (const item of DATABASE_LIFECYCLE_MANIFEST) {
      if (item.exportDataset) expect(datasets.has(item.exportDataset)).toBe(true);
      if (item.classification === 'user_export_delete') {
        expect(item.exportDataset).toBeDefined();
      }
    }
  });

  it('excludes authentication, queue, storage, and secret internals', () => {
    const exportedColumns = EXPORT_DATASETS.flatMap(({ columns }) => columns);
    expect(exportedColumns).not.toEqual(
      expect.arrayContaining([
        'password_hash',
        'token_hash',
        'credential_id',
        'public_key',
        'counter',
        'idempotency_key_hash',
        'queue_job_id',
        'object_key',
        'api_key_encrypted',
        'email_hash',
        'ip_hash',
        'user_agent_hash',
      ]),
    );
  });

  it('classifies caches, jobs, objects, logs, and backups without invented purge claims', () => {
    expect(NON_DATABASE_LIFECYCLE_MANIFEST.map(({ category }) => category)).toEqual([
      'redis_sessions',
      'redis_login_rate_cache',
      'bullmq_user_jobs',
      'private_export_objects',
      'application_logs_and_traces',
      'database_and_object_storage_backups',
    ]);
    expect(JSON.stringify(NON_DATABASE_LIFECYCLE_MANIFEST)).not.toMatch(
      /\b(?:7|14|30|60|90|365)[ -]?days?\b/i,
    );
  });
});
