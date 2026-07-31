import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';

const file = path.join(
  process.cwd(),
  'docs/backend-implementation/steps/22-postman-backend-freeze/ENDPOINT-COVERAGE-MATRIX.md',
);
const before = await readFile(file, 'utf8');
const result = spawnSync(process.execPath, ['scripts/contracts/generate-route-coverage.mjs'], {
  cwd: process.cwd(),
  encoding: 'utf8',
});
if (result.status !== 0) {
  process.stderr.write(result.stdout || '');
  process.stderr.write(result.stderr || '');
  process.exit(result.status || 1);
}
const after = await readFile(file, 'utf8');
if (before !== after) throw new Error('Endpoint coverage drift; run pnpm route-coverage:generate');
console.log('Endpoint coverage matrix is current.');
