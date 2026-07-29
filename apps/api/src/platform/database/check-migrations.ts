import { checkMigrationIntegrity } from './migrations/migration-integrity';

void checkMigrationIntegrity().catch((error: unknown) => {
  console.error(error instanceof Error ? error.message : 'Migration integrity check failed');
  process.exitCode = 1;
});
