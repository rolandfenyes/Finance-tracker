import expectedSchema from './expected-schema.json';
import type { SchemaFingerprint } from './schema-fingerprint';

export const expectedSchemaFingerprint: SchemaFingerprint = expectedSchema;

export function assertSchemaMatchesExpected(
  actual: SchemaFingerprint,
  expected: SchemaFingerprint = expectedSchemaFingerprint,
): void {
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error('PostgreSQL schema drift detected');
  }
}
