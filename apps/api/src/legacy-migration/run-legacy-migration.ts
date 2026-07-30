import { Pool } from 'pg';
import { createPostgresPoolConfig } from '../platform/database/postgres-config';
import {
  loadDatabaseCommandEnvironment,
  loadLocalEnvironmentFile,
} from '../platform/database/database-command-config';
import { LegacyMigrationRepository } from './legacy-migration.repository';
import { LegacySourceExtractor } from './legacy-source-extractor';
import { LegacyTransformer } from './legacy-transformer';

async function main(): Promise<void> {
  loadLocalEnvironmentFile();
  if (process.env.LEGACY_MIGRATION_ENABLED !== 'true') {
    throw new Error('LEGACY_MIGRATION_DISABLED');
  }
  const legacyUrl = process.env.LEGACY_DATABASE_URL;
  if (!legacyUrl?.startsWith('postgresql://')) {
    throw new Error('LEGACY_DATABASE_URL_REQUIRED');
  }
  const mode = process.env.LEGACY_MIGRATION_MODE === 'cutover' ? 'cutover' : 'rehearsal';
  if (mode === 'cutover' && process.env.LEGACY_MIGRATION_CUTOVER_APPROVED !== 'true') {
    throw new Error('LEGACY_MIGRATION_CUTOVER_NOT_APPROVED');
  }

  const targetPolicy = loadDatabaseCommandEnvironment();
  const sourcePool = new Pool({
    connectionString: legacyUrl,
    max: 1,
    connectionTimeoutMillis: targetPolicy.connectionTimeoutMs,
    idleTimeoutMillis: 1_000,
  });
  const targetPool = new Pool(createPostgresPoolConfig(targetPolicy));
  try {
    const source = await new LegacySourceExtractor(sourcePool).extract();
    const plan = new LegacyTransformer().transform(source);
    const batch = await new LegacyMigrationRepository(targetPool).persistRehearsal(
      plan,
      mode,
      new Date(),
    );
    console.info(
      JSON.stringify({
        batchId: batch.id,
        mode,
        status: batch.status,
        reused: batch.reused,
        sourceSchemaVersion: plan.sourceSchemaVersion,
        sourceRows: batch.sourceRowCount,
        plannedRows: batch.plannedRowCount,
        quarantinedRows: batch.quarantineRowCount,
        blockingCodes: plan.blockingCodes,
      }),
    );
    if (batch.status === 'blocked') {
      process.exitCode = 1;
    }
  } finally {
    await Promise.all([sourcePool.end(), targetPool.end()]);
  }
}

void main().catch((error: unknown) => {
  const code = error instanceof Error ? error.message : 'UNKNOWN_ERROR';
  const safeCode = /^[A-Z][A-Z0-9_]{1,79}$/.test(code)
    ? code
    : `LEGACY_MIGRATION_FAILED_${error instanceof Error ? error.name.toUpperCase() : 'UNKNOWN'}`;
  console.error(safeCode);
  process.exitCode = 1;
});
