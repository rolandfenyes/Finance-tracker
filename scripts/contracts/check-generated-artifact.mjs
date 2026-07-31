import { mkdtemp, readFile, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const [kind] = process.argv.slice(2);
const root = process.cwd();
const temp = await mkdtemp(path.join(os.tmpdir(), `mymoneymap-${kind}-`));

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: root,
    encoding: 'utf8',
    env: process.env,
    ...options,
  });
  if (result.status !== 0) {
    process.stderr.write(result.stdout || '');
    process.stderr.write(result.stderr || '');
    throw new Error(`${command} ${args.join(' ')} failed`);
  }
}

try {
  if (kind === 'postman') {
    const expected = path.join(root, 'postman/MyMoneyMap-backend-v1.postman_collection.json');
    const before = await readFile(expected, 'utf8');
    run(process.execPath, ['scripts/contracts/generate-postman.mjs']);
    const after = await readFile(expected, 'utf8');
    if (before !== after)
      throw new Error('Postman collection drift detected; run pnpm postman:generate');
  } else if (kind === 'angular') {
    const generated = path.join(temp, 'generated');
    const config = {
      ...JSON.parse(await readFile(path.join(root, 'ng-openapi-gen.json'), 'utf8')),
      output: generated,
    };
    const configPath = path.join(temp, 'config.json');
    await import('node:fs/promises').then(({ writeFile }) =>
      writeFile(configPath, JSON.stringify(config)),
    );
    run('pnpm', ['exec', 'ng-openapi-gen', '-c', configPath]);
    run('diff', ['-ru', path.join(root, 'libs/generated/api-client/src'), generated]);
  } else {
    throw new Error('Expected artifact kind "postman" or "angular"');
  }
  console.log(`${kind} generated artifact is current.`);
} finally {
  await rm(temp, { recursive: true, force: true });
}
