import { promises as fs } from 'node:fs';
import path from 'node:path';
import { registeredMigrations } from './migration-provider';

const migrationNamePattern = /^(?<order>\d{14})_[a-z0-9]+(?:_[a-z0-9]+)*$/;

export function validateMigrationNames(names: readonly string[]): void {
  if (names.length === 0) {
    throw new Error('At least one database migration is required');
  }

  const duplicateNames = names.filter((name, index) => names.indexOf(name) !== index);
  if (duplicateNames.length > 0) {
    throw new Error(`Duplicate migration names: ${[...new Set(duplicateNames)].join(', ')}`);
  }

  const invalidNames = names.filter((name) => !migrationNamePattern.test(name));
  if (invalidNames.length > 0) {
    throw new Error(`Invalid migration names: ${invalidNames.join(', ')}`);
  }

  const orders = names.map((name) => migrationNamePattern.exec(name)?.groups?.order);
  const duplicateOrders = orders.filter((order, index) => orders.indexOf(order) !== index);
  if (duplicateOrders.length > 0) {
    throw new Error(
      `Duplicate migration order prefixes: ${[...new Set(duplicateOrders)].join(', ')}`,
    );
  }

  const sorted = [...names].sort((left, right) => left.localeCompare(right));
  if (names.some((name, index) => name !== sorted[index])) {
    throw new Error('Migration registry must be in strict ascending order');
  }
}

export async function checkMigrationIntegrity(migrationDirectory = __dirname): Promise<void> {
  const registeredNames = Object.keys(registeredMigrations);
  validateMigrationNames(registeredNames);

  const sourceFiles = (await fs.readdir(migrationDirectory))
    .filter((name) => /^\d.*\.(?:ts|js)$/.test(name) && !name.endsWith('.d.ts'))
    .map((name) => path.parse(name).name)
    .sort((left, right) => left.localeCompare(right));

  if (JSON.stringify(sourceFiles) !== JSON.stringify(registeredNames)) {
    throw new Error('Migration files and the ordered registry do not match');
  }
}
