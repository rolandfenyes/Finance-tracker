import type { PoolConfig } from 'pg';

export type DatabaseTlsMode = 'disable' | 'require' | 'verify-full';

export interface PostgresConnectionPolicy {
  connectionString: string;
  tlsMode: DatabaseTlsMode;
  tlsCa?: string;
  poolMax: number;
  connectionTimeoutMs: number;
  statementTimeoutMs: number;
  idleTimeoutMs: number;
  maxLifetimeSeconds: number;
}

export function createPostgresPoolConfig(policy: PostgresConnectionPolicy): PoolConfig {
  const ssl =
    policy.tlsMode === 'disable'
      ? false
      : {
          rejectUnauthorized: policy.tlsMode === 'verify-full',
          ...(policy.tlsCa ? { ca: policy.tlsCa } : {}),
        };

  return {
    connectionString: policy.connectionString,
    ssl,
    max: policy.poolMax,
    connectionTimeoutMillis: policy.connectionTimeoutMs,
    statement_timeout: policy.statementTimeoutMs,
    idleTimeoutMillis: policy.idleTimeoutMs,
    maxLifetimeSeconds: policy.maxLifetimeSeconds,
  };
}
