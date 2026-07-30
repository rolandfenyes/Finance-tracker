import { createPostgresPoolConfig } from './postgres-config';

const basePolicy = {
  connectionString: 'postgresql://synthetic:synthetic@localhost:5432/synthetic',
  tlsMode: 'disable' as const,
  poolMax: 7,
  connectionTimeoutMs: 2_000,
  statementTimeoutMs: 10_000,
  idleTimeoutMs: 10_000,
  maxLifetimeSeconds: 300,
};

describe('PostgreSQL connection policy', () => {
  it('maps explicit pool limits without URL-derived TLS ambiguity', () => {
    expect(createPostgresPoolConfig(basePolicy)).toMatchObject({
      connectionString: basePolicy.connectionString,
      ssl: false,
      max: 7,
      connectionTimeoutMillis: 2_000,
      statement_timeout: 10_000,
      idleTimeoutMillis: 10_000,
      maxLifetimeSeconds: 300,
    });
  });

  it('verifies the server certificate and accepts a runtime CA bundle', () => {
    expect(
      createPostgresPoolConfig({
        ...basePolicy,
        tlsMode: 'verify-full',
        tlsCa: 'synthetic-ca',
      }).ssl,
    ).toEqual({
      rejectUnauthorized: true,
      ca: 'synthetic-ca',
    });
  });

  it('makes encrypted-but-unverified mode explicit', () => {
    expect(createPostgresPoolConfig({ ...basePolicy, tlsMode: 'require' }).ssl).toEqual({
      rejectUnauthorized: false,
    });
  });
});
