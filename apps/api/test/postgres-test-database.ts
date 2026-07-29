import { randomUUID } from 'node:crypto';
import { Pool } from 'pg';
import { createDatabase } from '../src/platform/database/create-database';
import {
  createPostgresPoolConfig,
  type PostgresConnectionPolicy,
} from '../src/platform/database/postgres-config';

export interface IsolatedPostgresDatabase {
  name: string;
  policy: PostgresConnectionPolicy;
  database: ReturnType<typeof createDatabase>['database'];
  pool: Pool;
}

export async function withIsolatedPostgresDatabase<T>(
  work: (isolated: IsolatedPostgresDatabase) => Promise<T>,
): Promise<T> {
  const basePolicy = testPostgresPolicy();
  const databaseName = `mymoneymap_step02_${randomUUID().replaceAll('-', '')}`;
  const maintenanceUrl = new URL(basePolicy.connectionString);
  maintenanceUrl.pathname = '/postgres';
  const maintenancePool = new Pool(
    createPostgresPoolConfig({
      ...basePolicy,
      connectionString: maintenanceUrl.toString(),
      poolMax: 1,
    }),
  );

  await maintenancePool.query(`CREATE DATABASE ${quoteIdentifier(databaseName)}`);

  const isolatedUrl = new URL(basePolicy.connectionString);
  isolatedUrl.pathname = `/${databaseName}`;
  const policy = { ...basePolicy, connectionString: isolatedUrl.toString() };
  const { database, pool } = createDatabase(policy);

  try {
    return await work({ name: databaseName, policy, database, pool });
  } finally {
    await database.destroy();
    await maintenancePool.query(
      `SELECT pg_terminate_backend(pid)
         FROM pg_stat_activity
        WHERE datname = $1
          AND pid <> pg_backend_pid()`,
      [databaseName],
    );
    await maintenancePool.query(`DROP DATABASE ${quoteIdentifier(databaseName)}`);
    await maintenancePool.end();
  }
}

export function testPostgresPolicy(): PostgresConnectionPolicy {
  const connectionString = process.env.DATABASE_MIGRATION_URL ?? process.env.DATABASE_URL;
  if (!connectionString) {
    throw new Error('DATABASE_URL is required for PostgreSQL integration tests');
  }

  return {
    connectionString,
    tlsMode:
      process.env.DATABASE_TLS_MODE === 'require' || process.env.DATABASE_TLS_MODE === 'verify-full'
        ? process.env.DATABASE_TLS_MODE
        : 'disable',
    tlsCa: process.env.DATABASE_TLS_CA,
    poolMax: 4,
    connectionTimeoutMs: Number(process.env.DATABASE_CONNECTION_TIMEOUT_MS ?? 2_000),
    idleTimeoutMs: 1_000,
    maxLifetimeSeconds: 300,
  };
}

function quoteIdentifier(identifier: string): string {
  if (!/^[a-z][a-z0-9_]+$/.test(identifier)) {
    throw new Error('Unsafe synthetic database identifier');
  }
  return `"${identifier}"`;
}
