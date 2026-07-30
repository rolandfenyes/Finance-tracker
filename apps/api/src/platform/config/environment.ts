import { z } from 'zod';

const booleanText = z.enum(['true', 'false']).transform((value) => value === 'true');
const positiveMilliseconds = z.coerce.number().int().min(100).max(300_000);
const positiveSeconds = z.coerce.number().int().min(1).max(31_536_000);

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
      })
      .refine(
        (value) => {
          try {
            return !new URL(value).searchParams.has('sslmode');
          } catch {
            return true;
          }
        },
        {
          message: 'must configure TLS with DATABASE_TLS_MODE',
        },
      ),
    DATABASE_TLS_MODE: z.enum(['disable', 'require', 'verify-full']),
    DATABASE_TLS_CA: z.string().min(1).optional(),
    DATABASE_POOL_MAX: z.coerce.number().int().min(1).max(50),
    DATABASE_CONNECTION_TIMEOUT_MS: positiveMilliseconds,
    DATABASE_IDLE_TIMEOUT_MS: positiveMilliseconds,
    DATABASE_MAX_LIFETIME_SECONDS: z.coerce.number().int().min(30).max(86_400),
    REDIS_URL: z
      .string()
      .min(1)
      .refine((value) => value.startsWith('redis://') || value.startsWith('rediss://'), {
        message: 'must use the redis:// or rediss:// scheme',
      }),
    FX_REFRESH_ENABLED: booleanText,
    RECURRENCE_ENABLED: booleanText.default(false),
    FX_PROVIDER_TIMEOUT_MS: positiveMilliseconds,
    SECURITIES_MARKET_DATA_ENABLED: booleanText.default(false),
    SECURITIES_MARKET_DATA_PRODUCTION_APPROVED: booleanText.default(false),
    SECURITIES_PROVIDER: z.enum(['disabled', 'finnhub']).default('disabled'),
    SECURITIES_PROVIDER_TIMEOUT_MS: positiveMilliseconds.default(5000),
    FINNHUB_API_KEY: z.string().min(1).optional(),
    FINNHUB_BASE_URL: z.url().default('https://finnhub.io/api/v1'),
    SESSION_SECRET: z.string().min(32),
    SESSION_COOKIE_NAME: z.string().regex(/^[A-Za-z0-9._-]+$/),
    SESSION_IDLE_TTL_SECONDS: positiveSeconds,
    SESSION_ABSOLUTE_TTL_SECONDS: positiveSeconds,
    REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS: positiveSeconds,
    EMAIL_VERIFICATION_TTL_SECONDS: positiveSeconds,
    EMAIL_VERIFICATION_RESEND_SECONDS: positiveSeconds,
    LOGIN_RATE_LIMIT_WINDOW_SECONDS: positiveSeconds,
    LOGIN_RATE_LIMIT_MAX_ATTEMPTS: z.coerce.number().int().min(1).max(100),
    LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS: z.coerce.number().int().min(1).max(1000),
    WEBAUTHN_RP_NAME: z.string().min(1).max(100),
    WEBAUTHN_RP_ID: z
      .string()
      .regex(
        /^(?:localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)$/i,
      ),
    WEBAUTHN_EXPECTED_ORIGINS: z
      .string()
      .transform((value) => value.split(',').map((origin) => origin.trim()))
      .pipe(z.array(z.url()).min(1)),
    WEBAUTHN_CHALLENGE_TTL_SECONDS: z.coerce.number().int().min(30).max(600),
    LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace']),
    TRUST_PROXY: trustProxy,
    OPENAPI_ENABLED: booleanText,
  })
  .superRefine((environment, context) => {
    if (environment.NODE_ENV !== 'production') {
      return;
    }

    if (!environment.RECURRENCE_ENABLED) {
      context.addIssue({
        code: 'custom',
        path: ['RECURRENCE_ENABLED'],
        message: 'must be enabled in production',
      });
    }

    if (
      environment.SECURITIES_MARKET_DATA_ENABLED &&
      !environment.SECURITIES_MARKET_DATA_PRODUCTION_APPROVED
    ) {
      context.addIssue({
        code: 'custom',
        path: ['SECURITIES_MARKET_DATA_PRODUCTION_APPROVED'],
        message: 'must remain false until delay, coverage, quota, and redistribution are approved',
      });
    }

    if (environment.DATABASE_TLS_MODE !== 'verify-full') {
      context.addIssue({
        code: 'custom',
        path: ['DATABASE_TLS_MODE'],
        message: 'must be verify-full in production',
      });
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

    if (environment.WEBAUTHN_EXPECTED_ORIGINS.some((origin) => !origin.startsWith('https://'))) {
      context.addIssue({
        code: 'custom',
        path: ['WEBAUTHN_EXPECTED_ORIGINS'],
        message: 'must use HTTPS origins in production',
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
