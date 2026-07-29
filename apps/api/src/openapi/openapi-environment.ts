export function setOpenApiGenerationEnvironment(): void {
  Object.assign(process.env, {
    NODE_ENV: 'test',
    API_HOST: '127.0.0.1',
    API_PORT: '3000',
    APP_BASE_URL: 'http://127.0.0.1:3000',
    DATABASE_URL: 'postgresql://openapi:synthetic@127.0.0.1:5433/openapi',
    REDIS_URL: 'redis://127.0.0.1:6380',
    LOG_LEVEL: 'fatal',
    TRUST_PROXY: 'false',
    OPENAPI_ENABLED: 'false',
  });
}
