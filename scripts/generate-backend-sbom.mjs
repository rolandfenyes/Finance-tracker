import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { createHash } from 'node:crypto';

const check = process.argv.includes('--check');
const outputPath = resolve('apps/api/sbom.cdx.json');
const listed = JSON.parse(
  execFileSync(
    'pnpm',
    ['--filter', '@mymoneymap/api', 'list', '--prod', '--json', '--depth', 'Infinity'],
    {
      encoding: 'utf8',
    },
  ),
);
const components = new Map();

function collect(dependencies = {}) {
  for (const [name, value] of Object.entries(dependencies)) {
    const version = value.version ?? 'unknown';
    const key = `${name}@${version}`;
    components.set(key, {
      type: 'library',
      name,
      version,
      purl: `pkg:npm/${encodeURIComponent(name)}@${encodeURIComponent(version)}`,
    });
    collect(value.dependencies);
  }
}

for (const project of listed) collect(project.dependencies);
const document = {
  bomFormat: 'CycloneDX',
  specVersion: '1.5',
  serialNumber: `urn:uuid:${createHash('sha256')
    .update([...components.keys()].sort().join('\n'))
    .digest('hex')
    .slice(0, 32)
    .replace(/^(.{8})(.{4})(.{4})(.{4})(.{12}).*$/, '$1-$2-$3-$4-$5')}`,
  version: 1,
  metadata: {
    component: { type: 'application', name: '@mymoneymap/api', version: '0.0.0' },
    properties: [{ name: 'mymoneymap:generator', value: 'scripts/generate-backend-sbom.mjs' }],
  },
  components: [...components.values()].sort((left, right) =>
    `${left.name}@${left.version}`.localeCompare(`${right.name}@${right.version}`),
  ),
};
const rendered = `${JSON.stringify(document, null, 2)}\n`;

if (check) {
  if (readFileSync(outputPath, 'utf8') !== rendered) {
    throw new Error('Backend SBOM drift detected; run pnpm security:sbom');
  }
} else {
  writeFileSync(outputPath, rendered);
}
