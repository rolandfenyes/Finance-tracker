import { promises as fs } from 'node:fs';
import path from 'node:path';
import {
  loadDatabaseCommandEnvironment,
  loadLocalEnvironmentFile,
} from './database-command-config';
import { createDatabase } from './create-database';
import { readSchemaFingerprint } from './schema-fingerprint';

async function main(): Promise<void> {
  loadLocalEnvironmentFile();
  const { database, pool } = createDatabase(loadDatabaseCommandEnvironment());
  try {
    const fingerprint = await readSchemaFingerprint(pool);
    await fs.writeFile(
      path.resolve('apps/api/src/platform/database/expected-schema.json'),
      `${JSON.stringify(fingerprint, null, 2)}\n`,
      'utf8',
    );
  } finally {
    await database.destroy();
  }
}

void main().catch((error: unknown) => {
  console.error(
    `Expected-schema generation failed: ${error instanceof Error ? error.name : 'UnknownError'}`,
  );
  process.exitCode = 1;
});
