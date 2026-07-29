import { z } from 'zod';

const booleanText = z.enum(['true', 'false']).transform((value) => value === 'true');

const trustProxy = z
  .union([z.enum(['true', 'false']), z.string().regex(/^[1-9]\d*$/)])
  .transform((value) => {
    if (value === 'true') {
      return true;
    }

    if (value === 'false') {
      return false;
    }

    return Number.parseInt(value, 10);
  });

const environmentSchema = z
  .object({
    NODE_ENV: z.enum(['development', 'test', 'production']),
    API_HOST: z.string().min(1),
    API_PORT: z.coerce.number().int().min(1).max(65_535),
    APP_BASE_URL: z.url(),
    DATABASE_URL: z
      .string()
      .min(1)
      .refine((value) => value.startsWith('postgresql://'), {
        message: 'must use the postgresql:// scheme',
      }),
    REDIS_URL: z
      .string()
      .min(1)
      .refine((value) => value.startsWith('redis://') || value.startsWith('rediss://'), {
        message: 'must use the redis:// or rediss:// scheme',
      }),
    LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace']),
    TRUST_PROXY: trustProxy,
    OPENAPI_ENABLED: booleanText,
  })
  .superRefine((environment, context) => {
    if (environment.NODE_ENV !== 'production') {
      return;
    }

    const publicUrl = new URL(environment.APP_BASE_URL);
    if (publicUrl.protocol !== 'https:') {
      context.addIssue({
        code: 'custom',
        path: ['APP_BASE_URL'],
        message: 'must use HTTPS in production',
      });
    }

    if (['localhost', '127.0.0.1', '::1'].includes(publicUrl.hostname)) {
      context.addIssue({
        code: 'custom',
        path: ['APP_BASE_URL'],
        message: 'must not use a loopback host in production',
      });
    }
  });

export type AppEnvironment = z.infer<typeof environmentSchema>;

export function validateEnvironment(input: Record<string, unknown>): AppEnvironment {
  const result = environmentSchema.safeParse(input);
  if (result.success) {
    return result.data;
  }

  const invalidKeys = [...new Set(result.error.issues.map((issue) => issue.path.join('.')))]
    .filter(Boolean)
    .sort();

  throw new Error(`Invalid application configuration: ${invalidKeys.join(', ')}`);
}

export function environmentFileFor(nodeEnvironment: string | undefined): string[] {
  if (nodeEnvironment === 'production') {
    return [];
  }

  if (nodeEnvironment === 'test') {
    return ['.env.backend.test', '.env.backend'];
  }

  return ['.env.backend'];
}
