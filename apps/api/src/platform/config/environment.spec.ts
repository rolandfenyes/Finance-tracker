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
  LOG_LEVEL: 'info',
  TRUST_PROXY: 'false',
  OPENAPI_ENABLED: 'true',
};

describe('environment validation', () => {
  it('parses an explicit development contract', () => {
    expect(validateEnvironment(validEnvironment)).toEqual({
      ...validEnvironment,
      API_PORT: 3000,
      DATABASE_POOL_MAX: 4,
      DATABASE_CONNECTION_TIMEOUT_MS: 2000,
      DATABASE_IDLE_TIMEOUT_MS: 10000,
      DATABASE_MAX_LIFETIME_SECONDS: 300,
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

  it('fails closed for an insecure production public URL', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        NODE_ENV: 'production',
        APP_BASE_URL: 'http://localhost:3000',
        DATABASE_TLS_MODE: 'verify-full',
      }),
    ).toThrow('Invalid application configuration: APP_BASE_URL');
  });

  it('requires verified database TLS in production', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        NODE_ENV: 'production',
        APP_BASE_URL: 'https://api.example.test',
        DATABASE_TLS_MODE: 'require',
      }),
    ).toThrow('Invalid application configuration: DATABASE_TLS_MODE');
  });

  it('rejects an ambiguous sslmode embedded in the connection URL', () => {
    expect(() =>
      validateEnvironment({
        ...validEnvironment,
        DATABASE_URL: `${validEnvironment.DATABASE_URL}?sslmode=disable`,
      }),
    ).toThrow('Invalid application configuration: DATABASE_URL');
  });

  it('does not load an environment file in production', () => {
    expect(environmentFileFor('production')).toEqual([]);
    expect(environmentFileFor('test')).toEqual(['.env.backend.test', '.env.backend']);
  });
});
