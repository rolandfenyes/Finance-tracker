import { promises as fs } from 'node:fs';
import path from 'node:path';
import { checkMigrationIntegrity, validateMigrationNames } from './migration-integrity';

describe('migration integrity', () => {
  it('accepts the committed timestamp-based order and matching registry', async () => {
    await expect(checkMigrationIntegrity()).resolves.toBeUndefined();
  });

  it.each([
    [['001_first', '001_second'], 'Invalid migration names'],
    [['20260729000000_first', '20260729000000_second'], 'Duplicate migration order prefixes'],
    [
      ['20260729000001_second', '20260729000000_first'],
      'Migration registry must be in strict ascending order',
    ],
  ])('rejects ambiguous migration order %#', (names, expectedMessage) => {
    expect(() => validateMigrationNames(names)).toThrow(expectedMessage);
  });

  it('contains no credential or default-administrator seed in target migrations', async () => {
    const migrationDirectory = __dirname;
    const sources = await Promise.all(
      (await fs.readdir(migrationDirectory))
        .filter((fileName) => /^\d.*\.ts$/.test(fileName))
        .map((fileName) => fs.readFile(path.join(migrationDirectory, fileName), 'utf8')),
    );
    const targetMigrationSource = sources.join('\n').toLowerCase();

    expect(targetMigrationSource).not.toMatch(/default[_ -]?admin/);
    expect(targetMigrationSource).not.toMatch(/password_hash/);
    expect(targetMigrationSource).not.toMatch(/insert\s+into\s+users/);
  });
});
