import { z } from 'zod';

const booleanText = z.enum(['true', 'false']).transform((value) => value === 'true');
const positiveMilliseconds = z.coerce.number().int().min(100).max(300_000);
const positiveSeconds = z.coerce.number().int().min(1).max(31_536_000);
const rateLimit = z.coerce.number().int().min(1).max(100_000);

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
    HTTP_JSON_BODY_LIMIT_BYTES: z.coerce
      .number()
      .int()
      .min(16_384)
      .max(10_485_760)
      .default(2_097_152),
    HTTP_REQUEST_TIMEOUT_MS: positiveMilliseconds.default(15_000),
    HTTP_HEADERS_TIMEOUT_MS: positiveMilliseconds.default(10_000),
    HTTP_KEEP_ALIVE_TIMEOUT_MS: positiveMilliseconds.default(5_000),
    HTTP_RATE_LIMIT_WINDOW_SECONDS: z.coerce.number().int().min(10).max(3_600).default(60),
    HTTP_RATE_LIMIT_MAX_REQUESTS: rateLimit.default(300),
    HTTP_ADMIN_RATE_LIMIT_MAX_REQUESTS: rateLimit.default(120),
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
    DATABASE_STATEMENT_TIMEOUT_MS: positiveMilliseconds.default(10_000),
    DATABASE_IDLE_TIMEOUT_MS: positiveMilliseconds,
    DATABASE_MAX_LIFETIME_SECONDS: z.coerce.number().int().min(30).max(86_400),
    REDIS_URL: z
      .string()
      .min(1)
      .refine((value) => value.startsWith('redis://') || value.startsWith('rediss://'), {
        message: 'must use the redis:// or rediss:// scheme',
      }),
    REDIS_CONNECT_TIMEOUT_MS: positiveMilliseconds.default(2_000),
    FX_REFRESH_ENABLED: booleanText,
    RECURRENCE_ENABLED: booleanText.default(false),
    FX_PROVIDER_TIMEOUT_MS: positiveMilliseconds,
    SECURITIES_MARKET_DATA_ENABLED: booleanText.default(false),
    SECURITIES_MARKET_DATA_PRODUCTION_APPROVED: booleanText.default(false),
    SECURITIES_PROVIDER: z.enum(['disabled', 'finnhub']).default('disabled'),
    SECURITIES_PROVIDER_TIMEOUT_MS: positiveMilliseconds.default(5000),
    FINNHUB_API_KEY: z.string().min(1).optional(),
    FINNHUB_BASE_URL: z.url().default('https://finnhub.io/api/v1'),
    EMAIL_DELIVERY_ENABLED: booleanText.default(false),
    EMAIL_DELIVERY_PRODUCTION_APPROVED: booleanText.default(false),
    EMAIL_PROVIDER: z.enum(['disabled', 'log', 'postmark', 'smtp']).default('disabled'),
    EMAIL_FROM_ADDRESS: z.email().optional(),
    EMAIL_FROM_NAME: z.string().trim().min(1).max(160).default('MyMoneyMap'),
    EMAIL_REPLY_TO_ADDRESS: z.email().optional(),
    POSTMARK_SERVER_TOKEN: z.string().min(1).optional(),
    POSTMARK_BASE_URL: z.url().default('https://api.postmarkapp.com'),
    SMTP_HOST: z.string().trim().min(1).max(253).optional(),
    SMTP_PORT: z.coerce.number().int().min(1).max(65_535).default(587),
    SMTP_USERNAME: z.string().min(1).optional(),
    SMTP_PASSWORD: z.string().min(1).optional(),
    SMTP_SECURITY: z.enum(['none', 'starttls', 'tls']).default('starttls'),
    SMTP_CONNECTION_TIMEOUT_MS: positiveMilliseconds.default(15_000),
    PRIVACY_EXPORTS_ENABLED: booleanText.default(false),
    PRIVACY_EXPORT_STORAGE_PROVIDER: z.enum(['disabled', 's3']).default('disabled'),
    PRIVACY_EXPORT_S3_BUCKET: z.string().min(3).max(63).optional(),
    PRIVACY_EXPORT_S3_REGION: z.string().min(2).max(64).optional(),
    PRIVACY_EXPORT_EXPIRY_SECONDS: positiveSeconds.optional(),
    PRIVACY_EXPORT_SIGNED_URL_SECONDS: positiveSeconds.optional(),
    LEGACY_MIGRATION_ENABLED: booleanText.default(false),
    LEGACY_MIGRATION_MODE: z.enum(['rehearsal', 'cutover']).default('rehearsal'),
    LEGACY_MIGRATION_CUTOVER_APPROVED: booleanText.default(false),
    LEGACY_DATABASE_URL: z
      .string()
      .min(1)
      .refine((value) => value.startsWith('postgresql://'), {
        message: 'must use the postgresql:// scheme',
      })
      .optional(),
    SETTINGS_ENCRYPTION_KEY: z.string().optional(),
    ACCOUNT_RECOVERY_TTL_SECONDS: positiveSeconds.default(3600),
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
    OPERATIONS_METRICS_ENABLED: booleanText.default(false),
    OPERATIONS_METRICS_TOKEN: z.string().min(32).optional(),
    SENTRY_ENABLED: booleanText.default(false),
    SENTRY_PRODUCTION_APPROVED: booleanText.default(false),
    SENTRY_DSN: z.url().optional(),
    SENTRY_ENVIRONMENT: z.string().min(1).max(64).optional(),
    SENTRY_TRACES_SAMPLE_RATE: z.coerce.number().min(0).max(1).default(0),
    TRUST_PROXY: trustProxy,
    OPENAPI_ENABLED: booleanText,
  })
  .superRefine((environment, context) => {
    if (environment.LEGACY_MIGRATION_ENABLED && !environment.LEGACY_DATABASE_URL) {
      context.addIssue({
        code: 'custom',
        path: ['LEGACY_DATABASE_URL'],
        message: 'enabled legacy migration requires an explicit read-only source URL',
      });
    }

    if (environment.OPERATIONS_METRICS_ENABLED && !environment.OPERATIONS_METRICS_TOKEN) {
      context.addIssue({
        code: 'custom',
        path: ['OPERATIONS_METRICS_TOKEN'],
        message: 'enabled metrics require an independent bearer token',
      });
    }

    if (environment.SENTRY_ENABLED && !environment.SENTRY_DSN) {
      context.addIssue({
        code: 'custom',
        path: ['SENTRY_DSN'],
        message: 'enabled error tracking requires an explicit Sentry DSN',
      });
    }

    if (environment.EMAIL_DELIVERY_ENABLED && environment.EMAIL_PROVIDER === 'postmark') {
      if (!environment.POSTMARK_SERVER_TOKEN) {
        context.addIssue({
          code: 'custom',
          path: ['POSTMARK_SERVER_TOKEN'],
          message: 'enabled Postmark delivery requires a server token',
        });
      }
      if (!environment.EMAIL_FROM_ADDRESS) {
        context.addIssue({
          code: 'custom',
          path: ['EMAIL_FROM_ADDRESS'],
          message: 'enabled email delivery requires a sender address',
        });
      }
    }

    if (environment.EMAIL_DELIVERY_ENABLED && environment.EMAIL_PROVIDER === 'smtp') {
      for (const [key, value] of [
        ['EMAIL_FROM_ADDRESS', environment.EMAIL_FROM_ADDRESS],
        ['SMTP_HOST', environment.SMTP_HOST],
        ['SMTP_USERNAME', environment.SMTP_USERNAME],
        ['SMTP_PASSWORD', environment.SMTP_PASSWORD],
      ] as const) {
        if (!value) {
          context.addIssue({
            code: 'custom',
            path: [key],
            message: 'enabled SMTP delivery requires complete authenticated configuration',
          });
        }
      }
    }

    if (environment.EMAIL_DELIVERY_ENABLED && environment.EMAIL_PROVIDER === 'disabled') {
      context.addIssue({
        code: 'custom',
        path: ['EMAIL_PROVIDER'],
        message: 'enabled email delivery requires a configured provider',
      });
    }

    if (
      environment.LEGACY_MIGRATION_MODE === 'cutover' &&
      !environment.LEGACY_MIGRATION_CUTOVER_APPROVED
    ) {
      context.addIssue({
        code: 'custom',
        path: ['LEGACY_MIGRATION_CUTOVER_APPROVED'],
        message: 'cutover mode requires explicit owner approval',
      });
    }

    if (environment.PRIVACY_EXPORTS_ENABLED) {
      if (
        environment.PRIVACY_EXPORT_STORAGE_PROVIDER !== 's3' ||
        !environment.PRIVACY_EXPORT_S3_BUCKET ||
        !environment.PRIVACY_EXPORT_S3_REGION
      ) {
        context.addIssue({
          code: 'custom',
          path: ['PRIVACY_EXPORT_STORAGE_PROVIDER'],
          message: 'enabled privacy exports require approved private S3 storage',
        });
      }
      if (
        environment.PRIVACY_EXPORT_EXPIRY_SECONDS === undefined ||
        environment.PRIVACY_EXPORT_SIGNED_URL_SECONDS === undefined
      ) {
        context.addIssue({
          code: 'custom',
          path: ['PRIVACY_EXPORT_EXPIRY_SECONDS'],
          message: 'enabled privacy exports require owner-approved explicit TTLs',
        });
      } else if (
        environment.PRIVACY_EXPORT_SIGNED_URL_SECONDS > environment.PRIVACY_EXPORT_EXPIRY_SECONDS
      ) {
        context.addIssue({
          code: 'custom',
          path: ['PRIVACY_EXPORT_SIGNED_URL_SECONDS'],
          message: 'signed access cannot outlive the export artifact',
        });
      }
    }

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

    if (!environment.OPERATIONS_METRICS_ENABLED) {
      context.addIssue({
        code: 'custom',
        path: ['OPERATIONS_METRICS_ENABLED'],
        message: 'must be enabled in production',
      });
    }

    if (!environment.SENTRY_ENABLED || !environment.SENTRY_PRODUCTION_APPROVED) {
      context.addIssue({
        code: 'custom',
        path: ['SENTRY_PRODUCTION_APPROVED'],
        message: 'production error tracking requires the approved PII-scrubbed Sentry gate',
      });
    }

    if (!environment.SETTINGS_ENCRYPTION_KEY) {
      context.addIssue({
        code: 'custom',
        path: ['SETTINGS_ENCRYPTION_KEY'],
        message: 'must be configured in production',
      });
    } else if (Buffer.from(environment.SETTINGS_ENCRYPTION_KEY, 'base64').length !== 32) {
      context.addIssue({
        code: 'custom',
        path: ['SETTINGS_ENCRYPTION_KEY'],
        message: 'must encode exactly 32 bytes',
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

    if (environment.EMAIL_DELIVERY_ENABLED && !environment.EMAIL_DELIVERY_PRODUCTION_APPROVED) {
      context.addIssue({
        code: 'custom',
        path: ['EMAIL_DELIVERY_PRODUCTION_APPROVED'],
        message: 'must remain false until the Step 21 email production gate is approved',
      });
    }

    if (environment.EMAIL_DELIVERY_ENABLED && environment.EMAIL_PROVIDER === 'log') {
      context.addIssue({
        code: 'custom',
        path: ['EMAIL_PROVIDER'],
        message: 'production delivery requires an approved external provider',
      });
    }

    if (
      environment.EMAIL_DELIVERY_ENABLED &&
      environment.EMAIL_PROVIDER === 'smtp' &&
      environment.SMTP_SECURITY === 'none'
    ) {
      context.addIssue({
        code: 'custom',
        path: ['SMTP_SECURITY'],
        message: 'production SMTP delivery requires transport encryption',
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
