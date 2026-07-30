import { environmentFileFor, validateEnvironment } from './environment';

const validEnvironment = {
  NODE_ENV: 'development',
  API_HOST: '127.0.0.1',
  API_PORT: '3000',
  APP_BASE_URL: 'http://127.0.0.1:3000',
  DATABASE_URL: 'postgresql://api:synthetic@127.0.0.1:5433/api_test',
  DATABASE_TLS_MODE: 'disable',
  DATABASE_POOL_MAX: '4',
  DATABASE_CONNECTION_TIMEOUT_MS: '2000',
  DATABASE_IDLE_TIMEOUT_MS: '10000',
  DATABASE_MAX_LIFETIME_SECONDS: '300',
  REDIS_URL: 'redis://127.0.0.1:6380',
  FX_REFRESH_ENABLED: 'false',
  FX_PROVIDER_TIMEOUT_MS: '5000',
  SETTINGS_ENCRYPTION_KEY: Buffer.from('synthetic-test-key-is-32-bytes!!').toString('base64'),
  ACCOUNT_RECOVERY_TTL_SECONDS: '3600',
  SESSION_SECRET: 'synthetic-session-secret-at-least-32-characters',
  SESSION_COOKIE_NAME: 'mymoneymap.sid',
  SESSION_IDLE_TTL_SECONDS: '1800',
  SESSION_ABSOLUTE_TTL_SECONDS: '43200',
  REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS: '2592000',
  EMAIL_VERIFICATION_TTL_SECONDS: '86400',
  EMAIL_VERIFICATION_RESEND_SECONDS: '300',
  LOGIN_RATE_LIMIT_WINDOW_SECONDS: '900',
  LOGIN_RATE_LIMIT_MAX_ATTEMPTS: '5',
  LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS: '25',
  WEBAUTHN_RP_NAME: 'MyMoneyMap',
  WEBAUTHN_RP_ID: 'localhost',
  WEBAUTHN_EXPECTED_ORIGINS: 'http://localhost:4200',
  WEBAUTHN_CHALLENGE_TTL_SECONDS: '300',
  LOG_LEVEL: 'info',
  TRUST_PROXY: 'false',
  OPENAPI_ENABLED: 'true',
};
const validProductionEnvironment = {
  ...validEnvironment,
  NODE_ENV: 'production',
  APP_BASE_URL: 'https://api.example.test',
  DATABASE_TLS_MODE: 'verify-full',
  RECURRENCE_ENABLED: 'true',
  WEBAUTHN_EXPECTED_ORIGINS: 'https://app.example.test',
  OPERATIONS_METRICS_ENABLED: 'true',
  OPERATIONS_METRICS_TOKEN: 'synthetic-operations-token-at-least-32-characters',
  SENTRY_ENABLED: 'true',
  SENTRY_PRODUCTION_APPROVED: 'true',
  SENTRY_DSN: 'https://public@example.invalid/1',
  SENTRY_ENVIRONMENT: 'production',
  SENTRY_TRACES_SAMPLE_RATE: '0.05',
};

describe('environment validation', () => {
  it('parses an explicit development contract', () => {
    expect(validateEnvironment(validEnvironment)).toEqual({
      ...validEnvironment,
      API_PORT: 3000,
      HTTP_JSON_BODY_LIMIT_BYTES: 2_097_152,
      HTTP_REQUEST_TIMEOUT_MS: 15_000,
      HTTP_HEADERS_TIMEOUT_MS: 10_000,
      HTTP_KEEP_ALIVE_TIMEOUT_MS: 5_000,
      HTTP_RATE_LIMIT_WINDOW_SECONDS: 60,
      HTTP_RATE_LIMIT_MAX_REQUESTS: 300,
      HTTP_ADMIN_RATE_LIMIT_MAX_REQUESTS: 120,
      DATABASE_POOL_MAX: 4,
      DATABASE_CONNECTION_TIMEOUT_MS: 2000,
      DATABASE_STATEMENT_TIMEOUT_MS: 10_000,
      DATABASE_IDLE_TIMEOUT_MS: 10000,
      DATABASE_MAX_LIFETIME_SECONDS: 300,
      FX_REFRESH_ENABLED: false,
      RECURRENCE_ENABLED: false,
      FX_PROVIDER_TIMEOUT_MS: 5000,
      SECURITIES_MARKET_DATA_ENABLED: false,
      SECURITIES_MARKET_DATA_PRODUCTION_APPROVED: false,
      SECURITIES_PROVIDER: 'disabled',
      SECURITIES_PROVIDER_TIMEOUT_MS: 5000,
      FINNHUB_BASE_URL: 'https://finnhub.io/api/v1',
      EMAIL_DELIVERY_ENABLED: false,
      EMAIL_DELIVERY_PRODUCTION_APPROVED: false,
      EMAIL_PROVIDER: 'disabled',
      POSTMARK_BASE_URL: 'https://api.postmarkapp.com',
      PRIVACY_EXPORTS_ENABLED: false,
      PRIVACY_EXPORT_STORAGE_PROVIDER: 'disabled',
      LEGACY_MIGRATION_ENABLED: false,
      LEGACY_MIGRATION_MODE: 'rehearsal',
      LEGACY_MIGRATION_CUTOVER_APPROVED: false,
      ACCOUNT_RECOVERY_TTL_SECONDS: 3600,
      SESSION_IDLE_TTL_SECONDS: 1800,
      SESSION_ABSOLUTE_TTL_SECONDS: 43200,
      REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS: 2592000,
      EMAIL_VERIFICATION_TTL_SECONDS: 86400,
      EMAIL_VERIFICATION_RESEND_SECONDS: 300,
      LOGIN_RATE_LIMIT_WINDOW_SECONDS: 900,
      LOGIN_RATE_LIMIT_MAX_ATTEMPTS: 5,
      LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS: 25,
      REDIS_CONNECT_TIMEOUT_MS: 2_000,
      OPERATIONS_METRICS_ENABLED: false,
      SENTRY_ENABLED: false,
      SENTRY_PRODUCTION_APPROVED: false,
      SENTRY_TRACES_SAMPLE_RATE: 0,
      WEBAUTHN_EXPECTED_ORIGINS: ['http://localhost:4200'],
      WEBAUTHN_CHALLENGE_TTL_SECONDS: 300,
      TRUST_PROXY: false,
      OPENAPI_ENABLED: true,
    });
  });

  it('reports only invalid keys and never their secret values', () => {
    const secretValue = 'must-not-appear-in-an-error';

    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        DATABASE_URL: secretValue,
        REDIS_URL: secretValue,
      }),
    ).toThrow('Invalid application configuration: DATABASE_URL, REDIS_URL');

    try {
      validateEnvironment({ ...validEnvironment, DATABASE_URL: secretValue });
    } catch (error) {
      expect(String(error)).not.toContain(secretValue);
    }
  });

  it('requires explicit approved private storage and TTLs when privacy exports are enabled', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        PRIVACY_EXPORTS_ENABLED: 'true',
      }),
    ).toThrow(
      'Invalid application configuration: PRIVACY_EXPORT_EXPIRY_SECONDS, PRIVACY_EXPORT_STORAGE_PROVIDER',
    );

    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        PRIVACY_EXPORTS_ENABLED: 'true',
        PRIVACY_EXPORT_STORAGE_PROVIDER: 's3',
        PRIVACY_EXPORT_S3_BUCKET: 'synthetic-private-exports',
        PRIVACY_EXPORT_S3_REGION: 'eu-central-1',
        PRIVACY_EXPORT_EXPIRY_SECONDS: '60',
        PRIVACY_EXPORT_SIGNED_URL_SECONDS: '120',
      }),
    ).toThrow('Invalid application configuration: PRIVACY_EXPORT_SIGNED_URL_SECONDS');
  });

  it('fails closed for an insecure production public URL', () => {
    expect(() =>
      validateEnvironment({
        ...validProductionEnvironment,
        APP_BASE_URL: 'http://localhost:3000',
      }),
    ).toThrow('Invalid application configuration: APP_BASE_URL');
  });

  it('requires verified database TLS in production', () => {
    expect(() =>
      validateEnvironment({
        ...validProductionEnvironment,
        DATABASE_TLS_MODE: 'require',
      }),
    ).toThrow('Invalid application configuration: DATABASE_TLS_MODE');
  });

  it('keeps production securities market data disabled until provider approval is recorded', () => {
    expect(() =>
      validateEnvironment({
        ...validProductionEnvironment,
        SECURITIES_MARKET_DATA_ENABLED: 'true',
        SECURITIES_PROVIDER: 'finnhub',
        FINNHUB_API_KEY: 'synthetic-provider-key',
      }),
    ).toThrow('Invalid application configuration: SECURITIES_MARKET_DATA_PRODUCTION_APPROVED');
  });

  it('requires the recurrence worker in production', () => {
    expect(() =>
      validateEnvironment({
        ...validProductionEnvironment,
        RECURRENCE_ENABLED: 'false',
      }),
    ).toThrow('Invalid application configuration: RECURRENCE_ENABLED');
  });

  it('requires protected metrics and PII-scrubbed error tracking in production', () => {
    expect(() =>
      validateEnvironment({
        ...validProductionEnvironment,
        OPERATIONS_METRICS_ENABLED: 'false',
        SENTRY_PRODUCTION_APPROVED: 'false',
      }),
    ).toThrow(
      'Invalid application configuration: OPERATIONS_METRICS_ENABLED, SENTRY_PRODUCTION_APPROVED',
    );
  });

  it('rejects an ambiguous sslmode embedded in the connection URL', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        DATABASE_URL: `${validEnvironment.DATABASE_URL}?sslmode=disable`,
      }),
    ).toThrow('Invalid application configuration: DATABASE_URL');
  });

  it('requires a source URL when migration is enabled and explicit approval for cutover mode', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        LEGACY_MIGRATION_ENABLED: 'true',
      }),
    ).toThrow('Invalid application configuration: LEGACY_DATABASE_URL');

    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        LEGACY_MIGRATION_ENABLED: 'true',
        LEGACY_DATABASE_URL: 'postgresql://legacy_readonly:synthetic@127.0.0.1:5433/legacy',
        LEGACY_MIGRATION_MODE: 'cutover',
      }),
    ).toThrow('Invalid application configuration: LEGACY_MIGRATION_CUTOVER_APPROVED');
  });

  it('does not load an environment file in production', () => {
    expect(environmentFileFor('production')).toEqual([]);
    expect(environmentFileFor('test')).toEqual(['.env.backend.test', '.env.backend']);
  });
});
