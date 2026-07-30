import { performance } from 'node:perf_hooks';
import { readFileSync } from 'node:fs';

const baseUrl = process.env.LOAD_TEST_BASE_URL ?? 'http://127.0.0.1:3000';
const userCookie = cookie('LOAD_TEST_USER_COOKIE');
const adminCookie = cookie('LOAD_TEST_ADMIN_COOKIE');
const exportId = required('LOAD_TEST_EXPORT_ID');
const durationSeconds = positiveNumber('LOAD_TEST_DURATION_SECONDS', 600);
const requestsPerSecond = positiveNumber('LOAD_TEST_REQUESTS_PER_SECOND', 4);
const concurrency = positiveNumber('LOAD_TEST_CONCURRENCY', 25);
const readBudgetMs = positiveNumber('LOAD_TEST_READ_P95_MS', 500);
const errorBudget = positiveNumber('LOAD_TEST_ERROR_PERCENT', 1);
const targets = [
  { name: 'activity', path: '/api/v1/journal/entries?limit=50', cookie: userCookie },
  { name: 'reports', path: '/api/v1/reports/months/current', cookie: userCookie },
  { name: 'schedules', path: '/api/v1/recurring-rules', cookie: userCookie },
  { name: 'portfolio', path: '/api/v1/securities/portfolio', cookie: userCookie },
  { name: 'admin', path: '/api/v1/admin/users?limit=50', cookie: adminCookie },
  { name: 'export', path: `/api/v1/privacy/exports/${exportId}`, cookie: userCookie },
];
const results = new Map(targets.map((target) => [target.name, []]));
const errors = new Map(targets.map((target) => [target.name, 0]));
const active = new Set();
const endAt = performance.now() + durationSeconds * 1_000;
let sequence = 0;

while (performance.now() < endAt) {
  if (active.size < concurrency) {
    const target = targets[sequence % targets.length];
    sequence += 1;
    const request = sample(target).finally(() => active.delete(request));
    active.add(request);
  }
  await wait(1_000 / requestsPerSecond);
}
await Promise.all(active);

let failed = false;
const summary = targets.map((target) => {
  const samples = results.get(target.name);
  const errorCount = errors.get(target.name);
  const count = samples.length + errorCount;
  const p95 = percentile(samples, 0.95);
  const errorPercent = count === 0 ? 100 : (errorCount / count) * 100;
  if (p95 > readBudgetMs || errorPercent > errorBudget) failed = true;
  return {
    target: target.name,
    requests: count,
    p50Ms: percentile(samples, 0.5),
    p95Ms: p95,
    errorPercent: Number(errorPercent.toFixed(2)),
  };
});

process.stdout.write(
  `${JSON.stringify({
    event: 'backend_read_load_complete',
    durationSeconds,
    requestsPerSecond,
    concurrency,
    readBudgetMs,
    errorBudgetPercent: errorBudget,
    summary,
  })}\n`,
);
if (failed) process.exitCode = 1;

async function sample(target) {
  const started = performance.now();
  try {
    const response = await fetch(new URL(target.path, baseUrl), {
      headers: { cookie: target.cookie },
      signal: AbortSignal.timeout(15_000),
    });
    if (response.status !== 200) {
      errors.set(target.name, errors.get(target.name) + 1);
      await response.arrayBuffer();
      return;
    }
    await response.arrayBuffer();
    results.get(target.name).push(performance.now() - started);
  } catch {
    errors.set(target.name, errors.get(target.name) + 1);
  }
}

function percentile(values, quantile) {
  if (values.length === 0) return Number.POSITIVE_INFINITY;
  const ordered = [...values].sort((left, right) => left - right);
  return Number(ordered[Math.ceil(ordered.length * quantile) - 1].toFixed(2));
}

function required(name) {
  const value = process.env[name];
  if (!value) throw new Error(`${name} is required`);
  return value;
}

function cookie(name) {
  const direct = process.env[name];
  if (direct) return direct;
  const file = process.env[`${name}_FILE`];
  if (!file) throw new Error(`${name} or ${name}_FILE is required`);
  const row = readFileSync(file, 'utf8')
    .split('\n')
    .findLast((line) => line && (!line.startsWith('#') || line.startsWith('#HttpOnly_')));
  if (!row) throw new Error(`${name}_FILE contains no cookie`);
  const columns = row.split('\t');
  const cookieName = columns.at(-2);
  const value = columns.at(-1);
  if (!cookieName || !value) throw new Error(`${name}_FILE has an invalid cookie row`);
  return `${cookieName}=${value}`;
}

function positiveNumber(name, fallback) {
  const value = Number(process.env[name] ?? fallback);
  if (!Number.isFinite(value) || value <= 0) throw new Error(`${name} must be positive`);
  return value;
}

function wait(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}
