import { assertSchemaMatchesExpected } from './expected-schema';
import type { SchemaFingerprint } from './schema-fingerprint';

const emptyFingerprint: SchemaFingerprint = {
  schemas: [],
  relations: [],
  columns: [],
  constraints: [],
  indexes: [],
};

describe('schema drift comparison', () => {
  it('accepts structurally identical fingerprints', () => {
    expect(() => assertSchemaMatchesExpected(emptyFingerprint, emptyFingerprint)).not.toThrow();
  });

  it('rejects an unexpected relation', () => {
    expect(() =>
      assertSchemaMatchesExpected(
        {
          ...emptyFingerprint,
          relations: [{ schema: 'mymoneymap', name: 'unexpected', kind: 'r' }],
        },
        emptyFingerprint,
      ),
    ).toThrow('PostgreSQL schema drift detected');
  });
});
