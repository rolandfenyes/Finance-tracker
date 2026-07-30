import { z } from 'zod';
import type { PostgresConnectionPolicy } from './postgres-config';

const postgresUrl = z
  .string()
  .startsWith('postgresql://')
  .refine(
    (value) => {
      try {
        return !new URL(value).searchParams.has('sslmode');
      } catch {
        return true;
      }
    },
    { message: 'must configure TLS with DATABASE_TLS_MODE' },
  );

const commandEnvironmentSchema = z
  .object({
    NODE_ENV: z.enum(['development', 'test', 'production']),
    DATABASE_URL: postgresUrl.optional(),
    DATABASE_MIGRATION_URL: postgresUrl.optional(),
    DATABASE_TLS_MODE: z.enum(['disable', 'require', 'verify-full']),
    DATABASE_TLS_CA: z.string().min(1).optional(),
    DATABASE_CONNECTION_TIMEOUT_MS: z.coerce.number().int().min(100).max(300_000),
    DATABASE_STATEMENT_TIMEOUT_MS: z.coerce.number().int().min(100).max(300_000).default(10_000),
  })
  .superRefine((environment, context) => {
    if (!environment.DATABASE_URL && !environment.DATABASE_MIGRATION_URL) {
      context.addIssue({
        code: 'custom',
        path: ['DATABASE_URL'],
        message: 'or DATABASE_MIGRATION_URL is required',
      });
    }

    if (environment.NODE_ENV === 'production' && !environment.DATABASE_MIGRATION_URL) {
      context.addIssue({
        code: 'custom',
        path: ['DATABASE_MIGRATION_URL'],
        message: 'is required in production',
      });
    }

    if (environment.NODE_ENV === 'production' && environment.DATABASE_TLS_MODE !== 'verify-full') {
      context.addIssue({
        code: 'custom',
        path: ['DATABASE_TLS_MODE'],
        message: 'must be verify-full in production',
      });
    }
  });

export function loadDatabaseCommandEnvironment(
  environment: NodeJS.ProcessEnv = process.env,
): PostgresConnectionPolicy {
  const result = commandEnvironmentSchema.safeParse(environment);
  if (!result.success) {
    const invalidKeys = [...new Set(result.error.issues.map((issue) => issue.path.join('.')))]
      .filter(Boolean)
      .sort();
    throw new Error(`Invalid database command configuration: ${invalidKeys.join(', ')}`);
  }

  return {
    connectionString: result.data.DATABASE_MIGRATION_URL ?? result.data.DATABASE_URL!,
    tlsMode: result.data.DATABASE_TLS_MODE,
    tlsCa: result.data.DATABASE_TLS_CA,
    poolMax: 1,
    connectionTimeoutMs: result.data.DATABASE_CONNECTION_TIMEOUT_MS,
    statementTimeoutMs: result.data.DATABASE_STATEMENT_TIMEOUT_MS,
    idleTimeoutMs: 1_000,
    maxLifetimeSeconds: 300,
  };
}

export function loadLocalEnvironmentFile(): void {
  if (process.env.NODE_ENV === 'production') {
    return;
  }

  try {
    process.loadEnvFile('.env.backend');
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== 'ENOENT') {
      throw error;
    }
  }
}
