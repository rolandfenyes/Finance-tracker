import { spawn, spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { randomBytes, randomUUID } from 'node:crypto';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const apiRequire = createRequire(path.join(root, 'apps/api/package.json'));
const { Client } = apiRequire('pg');
const argon2 = apiRequire('argon2');
const databasePrefix = 'mymoneymap_step22_';
const databaseName = `${databasePrefix}${randomBytes(6).toString('hex')}`;
const sourceUrl = process.env.DATABASE_MIGRATION_URL || process.env.DATABASE_URL;

if (!sourceUrl) throw new Error('DATABASE_MIGRATION_URL or DATABASE_URL is required');
if (!new RegExp(`^${databasePrefix}[0-9a-f]{12}$`).test(databaseName)) {
  throw new Error('Refusing to use an invalid acceptance database name');
}

const targetUrl = new URL(sourceUrl);
targetUrl.pathname = `/${databaseName}`;
const adminUrl = new URL(sourceUrl);
adminUrl.pathname = '/postgres';
const port = process.env.POSTMAN_API_PORT || '3010';
const baseUrl = `http://127.0.0.1:${port}`;
const tempDirectory = await mkdtemp(path.join(os.tmpdir(), 'mymoneymap-step22-'));
const environmentPath = path.join(tempDirectory, 'runtime.postman_environment.json');
const artifacts = path.join(root, 'artifacts/newman');
const password = `Synthetic-${randomBytes(18).toString('base64url')}!`;
const registrationPassword = `Synthetic-${randomBytes(18).toString('base64url')}!`;
const suffix = randomBytes(6).toString('hex');
const users = [
  { key: 'premium', role: 'premium', verified: true },
  { key: 'other', role: 'premium', verified: true },
  { key: 'free', role: 'free', verified: true },
  { key: 'admin', role: 'admin', verified: true },
  { key: 'unverified', role: 'premium', verified: false },
  { key: 'expiry', role: 'free', verified: true },
  { key: 'rateLimit', role: 'free', verified: true },
].map((user) => ({
  ...user,
  id: randomUUID(),
  email: `step22-${user.key.toLowerCase()}-${suffix}@example.test`,
}));

const runtimeEnvironment = {
  id: randomUUID(),
  name: 'MyMoneyMap Step 22 ephemeral acceptance',
  values: [
    { key: 'baseUrl', value: baseUrl, enabled: true },
    ...users.flatMap((user) => [
      { key: `${user.key}Email`, value: user.email, enabled: true },
      { key: `${user.key}Password`, value: password, enabled: true, type: 'secret' },
    ]),
    {
      key: 'registrationEmail',
      value: `step22-registration-${suffix}@example.test`,
      enabled: true,
    },
    {
      key: 'registrationPassword',
      value: registrationPassword,
      enabled: true,
      type: 'secret',
    },
  ],
  _postman_variable_scope: 'environment',
  _postman_exported_using: 'MyMoneyMap ephemeral acceptance runner',
};

const serviceEnvironment = {
  ...process.env,
  NODE_ENV: 'test',
  API_HOST: '127.0.0.1',
  API_PORT: port,
  APP_BASE_URL: baseUrl,
  LOG_LEVEL: 'fatal',
  TRUST_PROXY: 'false',
  OPENAPI_ENABLED: 'false',
  DATABASE_URL: targetUrl.toString(),
  DATABASE_MIGRATION_URL: targetUrl.toString(),
  DATABASE_TLS_MODE: 'disable',
  DATABASE_POOL_MAX: '6',
  DATABASE_CONNECTION_TIMEOUT_MS: '3000',
  DATABASE_STATEMENT_TIMEOUT_MS: '10000',
  DATABASE_IDLE_TIMEOUT_MS: '10000',
  DATABASE_MAX_LIFETIME_SECONDS: '300',
  REDIS_URL: process.env.REDIS_URL || 'redis://127.0.0.1:6380',
  SESSION_SECRET: 'step22-synthetic-session-secret-at-least-32-characters',
  SETTINGS_ENCRYPTION_KEY: 'c3RlcDIyLXN5bnRoZXRpYy0zMi1ieXRlLWtleSEhISE=',
  ACCOUNT_RECOVERY_TTL_SECONDS: '3600',
  SESSION_COOKIE_NAME: 'mymoneymap.sid',
  SESSION_IDLE_TTL_SECONDS: '2',
  SESSION_ABSOLUTE_TTL_SECONDS: '43200',
  REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS: '2592000',
  EMAIL_VERIFICATION_TTL_SECONDS: '86400',
  EMAIL_VERIFICATION_RESEND_SECONDS: '300',
  LOGIN_RATE_LIMIT_WINDOW_SECONDS: '900',
  LOGIN_RATE_LIMIT_MAX_ATTEMPTS: '5',
  LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS: '25',
  HTTP_RATE_LIMIT_WINDOW_SECONDS: '60',
  HTTP_RATE_LIMIT_MAX_REQUESTS: '300',
  HTTP_ADMIN_RATE_LIMIT_MAX_REQUESTS: '120',
  WEBAUTHN_RP_NAME: 'MyMoneyMap',
  WEBAUTHN_RP_ID: 'localhost',
  WEBAUTHN_EXPECTED_ORIGINS: baseUrl,
  WEBAUTHN_CHALLENGE_TTL_SECONDS: '300',
  FX_REFRESH_ENABLED: 'false',
  FX_PROVIDER_TIMEOUT_MS: '5000',
  RECURRENCE_ENABLED: 'false',
  SECURITIES_MARKET_DATA_ENABLED: 'false',
  SECURITIES_MARKET_DATA_PRODUCTION_APPROVED: 'false',
  SECURITIES_PROVIDER: 'disabled',
  EMAIL_DELIVERY_ENABLED: 'false',
  EMAIL_DELIVERY_PRODUCTION_APPROVED: 'false',
  EMAIL_PROVIDER: 'disabled',
  PRIVACY_EXPORTS_ENABLED: 'false',
  PRIVACY_EXPORT_STORAGE_PROVIDER: 'disabled',
  LEGACY_MIGRATION_ENABLED: 'false',
  LEGACY_MIGRATION_MODE: 'rehearsal',
  LEGACY_MIGRATION_CUTOVER_APPROVED: 'false',
  SENTRY_ENABLED: 'false',
  SENTRY_PRODUCTION_APPROVED: 'false',
  OPERATIONS_METRICS_ENABLED: 'false',
};

function run(command, args, environment = serviceEnvironment) {
  const result = spawnSync(command, args, {
    cwd: root,
    env: environment,
    stdio: 'inherit',
  });
  if (result.status !== 0) throw new Error(`${command} ${args.join(' ')} failed`);
}

async function waitForApi(child) {
  const deadline = Date.now() + 30_000;
  while (Date.now() < deadline) {
    if (child.exitCode !== null) throw new Error(`API exited before readiness (${child.exitCode})`);
    try {
      const response = await fetch(`${baseUrl}/api/v1/health/ready`);
      if (response.ok) return;
    } catch {
      // Expected while the isolated API starts.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error('API did not become ready within 30 seconds');
}

async function terminate(child) {
  if (!child || child.exitCode !== null) return;
  child.kill('SIGTERM');
  await Promise.race([
    new Promise((resolve) => child.once('exit', resolve)),
    new Promise((resolve) =>
      setTimeout(() => {
        child.kill('SIGKILL');
        resolve();
      }, 5_000),
    ),
  ]);
}

let api;
const admin = new Client({ connectionString: adminUrl.toString() });
try {
  await admin.connect();
  await admin.query(`CREATE DATABASE "${databaseName}"`);
  run('pnpm', ['nx', 'run', 'api:build']);
  run('pnpm', ['nx', 'run', 'api:db-migrate']);
  run('pnpm', ['nx', 'run', 'api:db-rollback']);
  run('pnpm', ['nx', 'run', 'api:db-migrate']);
  run('pnpm', ['nx', 'run', 'api:db-drift']);

  const database = new Client({ connectionString: targetUrl.toString() });
  await database.connect();
  try {
    const hash = await argon2.hash(password, { type: argon2.argon2id });
    for (const user of users) {
      await database.query(
        `INSERT INTO mymoneymap.users
           (id,email,password_hash,full_name,date_of_birth,role,status,email_verified_at,created_at,updated_at)
         VALUES ($1,$2,$3,$4,'1990-01-15',$5,'active',$6,now(),now())`,
        [
          user.id,
          user.email,
          hash,
          `Synthetic ${user.key} User`,
          user.role,
          user.verified ? new Date() : null,
        ],
      );
    }
  } finally {
    await database.end();
  }

  await mkdir(artifacts, { recursive: true });
  await writeFile(environmentPath, `${JSON.stringify(runtimeEnvironment, null, 2)}\n`, {
    mode: 0o600,
  });
  api = spawn('node', ['apps/api/dist/main.js'], {
    cwd: root,
    env: serviceEnvironment,
    stdio: 'inherit',
  });
  await waitForApi(api);
  run('pnpm', [
    'exec',
    'newman',
    'run',
    'postman/MyMoneyMap-backend-v1.postman_collection.json',
    '--folder',
    'Acceptance',
    '--environment',
    environmentPath,
    '--reporters',
    'cli,junit',
    '--reporter-junit-export',
    'artifacts/newman/results.xml',
    '--color',
    'off',
  ]);
  await writeFile(
    path.join(artifacts, 'summary.txt'),
    [
      'MyMoneyMap Step 22 Newman acceptance: PASS',
      `Collection: postman/MyMoneyMap-backend-v1.postman_collection.json`,
      'Data: isolated synthetic PostgreSQL database removed after execution',
      'Providers: email, securities market data, and privacy export storage disabled',
      '',
    ].join('\n'),
  );
} finally {
  await terminate(api);
  await rm(tempDirectory, { recursive: true, force: true });
  try {
    await admin.query(
      `SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()`,
      [databaseName],
    );
    await admin.query(`DROP DATABASE IF EXISTS "${databaseName}"`);
  } finally {
    await admin.end().catch(() => undefined);
  }
}
