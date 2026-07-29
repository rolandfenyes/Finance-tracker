import { loadDatabaseCommandEnvironment } from './database-command-config';

const environment = {
  NODE_ENV: 'test',
  DATABASE_URL: 'postgresql://runtime:synthetic@localhost:5432/runtime',
  DATABASE_MIGRATION_URL: 'postgresql://migrator:synthetic@localhost:5432/runtime',
  DATABASE_TLS_MODE: 'disable',
  DATABASE_CONNECTION_TIMEOUT_MS: '2000',
};

describe('database command configuration', () => {
  it('uses the distinct migration credential when supplied', () => {
    expect(loadDatabaseCommandEnvironment(environment).connectionString).toBe(
      environment.DATABASE_MIGRATION_URL,
    );
  });

  it('allows the runtime credential only for local and test rehearsals', () => {
    const withoutMigrationUrl = { ...environment, DATABASE_MIGRATION_URL: undefined };
    expect(loadDatabaseCommandEnvironment(withoutMigrationUrl).connectionString).toBe(
      environment.DATABASE_URL,
    );
  });

  it('requires a distinct migration credential and verified TLS in production', () => {
    expect(() =>
      loadDatabaseCommandEnvironment({
        ...environment,
        NODE_ENV: 'production',
        DATABASE_MIGRATION_URL: undefined,
        DATABASE_TLS_MODE: 'require',
      }),
    ).toThrow('Invalid database command configuration: DATABASE_MIGRATION_URL, DATABASE_TLS_MODE');
  });

  it('allows production migration commands to receive only the migration credential', () => {
    expect(
      loadDatabaseCommandEnvironment({
        ...environment,
        NODE_ENV: 'production',
        DATABASE_URL: undefined,
        DATABASE_TLS_MODE: 'verify-full',
      }).connectionString,
    ).toBe(environment.DATABASE_MIGRATION_URL);
  });

  it('never includes credential values in validation errors', () => {
    const secret = 'must-not-be-logged';
    expect(() =>
      loadDatabaseCommandEnvironment({ ...environment, DATABASE_MIGRATION_URL: secret }),
    ).toThrow('Invalid database command configuration: DATABASE_MIGRATION_URL');

    try {
      loadDatabaseCommandEnvironment({ ...environment, DATABASE_MIGRATION_URL: secret });
    } catch (error) {
      expect(String(error)).not.toContain(secret);
    }
  });

  it('rejects sslmode in the migration URL', () => {
    expect(() =>
      loadDatabaseCommandEnvironment({
        ...environment,
        DATABASE_MIGRATION_URL: `${environment.DATABASE_MIGRATION_URL}?sslmode=disable`,
      }),
    ).toThrow('Invalid database command configuration: DATABASE_MIGRATION_URL');
  });
});
