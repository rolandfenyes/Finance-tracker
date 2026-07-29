import { checkMigrationIntegrity } from './migrations/migration-integrity';
import {
  loadDatabaseCommandEnvironment,
  loadLocalEnvironmentFile,
} from './database-command-config';
import { createDatabase } from './create-database';
import { assertSchemaMatchesExpected } from './expected-schema';
import { getMigrationStatus, migrateOneDown, migrateToLatest } from './migration-runner';
import { readSchemaFingerprint } from './schema-fingerprint';

type DatabaseCommand = 'migrate' | 'rollback' | 'status' | 'drift' | 'fingerprint';

async function main(): Promise<void> {
  loadLocalEnvironmentFile();
  const command = process.argv[2] as DatabaseCommand | undefined;
  if (!command || !['migrate', 'rollback', 'status', 'drift', 'fingerprint'].includes(command)) {
    throw new Error('Expected database command: migrate, rollback, status, drift, or fingerprint');
  }

  await checkMigrationIntegrity();
  const { database, pool } = createDatabase(loadDatabaseCommandEnvironment());

  try {
    if (command === 'migrate') {
      const result = await migrateToLatest(database);
      console.info(`Applied migrations: ${result.results?.length ?? 0}`);
      return;
    }

    if (command === 'rollback') {
      const result = await migrateOneDown(database);
      console.info(`Rolled back migrations: ${result.results?.length ?? 0}`);
      return;
    }

    if (command === 'status') {
      const status = await getMigrationStatus(database);
      for (const migration of status) {
        console.info(`${migration.executedAt ? 'applied' : 'pending'} ${migration.name}`);
      }
      return;
    }

    const fingerprint = await readSchemaFingerprint(pool);
    if (command === 'fingerprint') {
      console.info(JSON.stringify(fingerprint, null, 2));
      return;
    }

    const pending = (await getMigrationStatus(database)).filter(
      (migration) => !migration.executedAt,
    );
    if (pending.length > 0) {
      throw new Error(`Pending database migrations: ${pending.map(({ name }) => name).join(', ')}`);
    }
    assertSchemaMatchesExpected(fingerprint);
    console.info('PostgreSQL schema matches the committed baseline');
  } finally {
    await database.destroy();
  }
}

void main().catch((error: unknown) => {
  const message =
    error instanceof Error &&
    (error.message.startsWith('Invalid database command configuration') ||
      error.message.startsWith('Database migration failed') ||
      error.message.startsWith('Expected database command') ||
      error.message.startsWith('Pending database migrations') ||
      error.message === 'PostgreSQL schema drift detected')
      ? error.message
      : `Database command failed: ${error instanceof Error ? error.name : 'UnknownError'}`;

  console.error(message);
  process.exitCode = 1;
});
