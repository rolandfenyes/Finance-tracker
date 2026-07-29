import { inspect } from 'node:util';
import { EntityId } from './identifiers/entity-id';
import { IdempotencyKey, IdempotencyOperation } from './idempotency/idempotency';
import { PageCursor, PaginationLimit } from './pagination/cursor-pagination';
import { EncryptedSetting } from './security/encrypted-setting.port';
import { REDACTED_SECRET, SecretValue } from './security/secret-value';

describe('shared-kernel support primitives', () => {
  it('generates and validates canonical UUID v4 identifiers', () => {
    const generated = EntityId.generate<'synthetic'>();

    expect(EntityId.create<'synthetic'>(generated.toString()).equals(generated)).toBe(true);
    expect(() => EntityId.create('00000000-0000-0000-0000-000000000000')).toThrow();
    expect(() => EntityId.create('01910c40-1234-7abc-8def-1234567890ab')).toThrow();
  });

  it('redacts secret values from strings, JSON, and inspection', () => {
    const secret = SecretValue.create('synthetic-secret');

    expect(secret.toString()).toBe(REDACTED_SECRET);
    expect(JSON.stringify({ secret })).toBe(`{"secret":"${REDACTED_SECRET}"}`);
    expect(inspect(secret)).toBe(REDACTED_SECRET);
    expect(secret.use((plaintext) => plaintext.length)).toBe(16);
    expect(() => SecretValue.create('')).toThrow();
    expect(() => EncryptedSetting.create('')).toThrow();
    const encrypted = EncryptedSetting.create('synthetic-ciphertext');
    expect(JSON.stringify(encrypted)).toBe(`"${REDACTED_SECRET}"`);
    expect(inspect(encrypted)).toBe(REDACTED_SECRET);
  });

  it('validates endpoint-owned pagination without inventing a global page maximum', () => {
    expect(PaginationLimit.create(25, 100).value).toBe(25);
    expect(PageCursor.create('opaque-cursor').toString()).toBe('opaque-cursor');
    expect(() => PaginationLimit.create(101, 100)).toThrow();
    expect(() => PageCursor.create('  ')).toThrow();
  });

  it('hashes raw idempotency keys and validates stable operation names', () => {
    const key = IdempotencyKey.create('synthetic-client-key');

    expect(key.toHash()).toMatch(/^[0-9a-f]{64}$/);
    expect(key.toHash()).not.toContain('synthetic-client-key');
    expect(IdempotencyOperation.create('ledger.transaction.create').toString()).toBe(
      'ledger.transaction.create',
    );
    expect(() => IdempotencyOperation.create('Ledger Create')).toThrow();
  });
});
