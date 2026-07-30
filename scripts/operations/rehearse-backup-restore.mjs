import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';

const source = process.env.DATABASE_URL;
if (!source) throw new Error('DATABASE_URL is required');
const sourceUrl = new URL(source);
if (!['127.0.0.1', 'localhost'].includes(sourceUrl.hostname)) {
  throw new Error('Restore rehearsal only accepts a local synthetic PostgreSQL source');
}
if (
  !sourceUrl.pathname.toLowerCase().includes('test') &&
  process.env.RESTORE_REHEARSAL_APPROVED !== 'true'
) {
  throw new Error(
    'Restore rehearsal requires a test-named database or RESTORE_REHEARSAL_APPROVED=true',
  );
}

const databaseName = `mymoneymap_restore_${randomUUID().replaceAll('-', '')}`;
const target = new URL(source);
target.pathname = `/${databaseName}`;
const directory = mkdtempSync(join(tmpdir(), 'mymoneymap-restore-'));
const dump = join(directory, 'synthetic.dump');
const started = Date.now();

try {
  execFileSync('pg_dump', ['--format=custom', '--no-owner', '--no-acl', '--file', dump, source], {
    stdio: 'inherit',
  });
  execFileSync('createdb', ['--maintenance-db', source, databaseName], { stdio: 'inherit' });
  execFileSync('pg_restore', ['--no-owner', '--no-acl', '--dbname', target.toString(), dump], {
    stdio: 'inherit',
  });
  execFileSync(
    'psql',
    [
      target.toString(),
      '--no-psqlrc',
      '--tuples-only',
      '--command',
      "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'mymoneymap'",
    ],
    { stdio: 'inherit' },
  );
  process.stdout.write(
    `${JSON.stringify({
      event: 'backup_restore_rehearsal_complete',
      elapsedSeconds: Math.ceil((Date.now() - started) / 1000),
      source: 'local-synthetic-only',
    })}\n`,
  );
} finally {
  execFileSync('dropdb', ['--maintenance-db', source, '--if-exists', databaseName], {
    stdio: 'inherit',
  });
  rmSync(directory, { recursive: true, force: true });
}
