import { createHash } from 'node:crypto';
import type { EntityId } from '../identifiers/entity-id';

const OPERATION_PATTERN = /^[a-z][a-z0-9._:-]{0,127}$/;

export class IdempotencyKey {
  private constructor(private readonly hash: string) {}

  static create(value: string): IdempotencyKey {
    const byteLength = Buffer.byteLength(value, 'utf8');
    if (byteLength < 1 || byteLength > 255) {
      throw new Error('Idempotency key must contain between 1 and 255 UTF-8 bytes');
    }
    return new IdempotencyKey(sha256(value));
  }

  toHash(): string {
    return this.hash;
  }
}

export class IdempotencyOperation {
  private constructor(private readonly value: string) {}

  static create(value: string): IdempotencyOperation {
    if (!OPERATION_PATTERN.test(value)) {
      throw new Error('Idempotency operation must use the stable operation-name format');
    }
    return new IdempotencyOperation(value);
  }

  toString(): string {
    return this.value;
  }
}

export class RequestFingerprint {
  private constructor(private readonly hash: string) {}

  static fromCanonicalRequest(value: string): RequestFingerprint {
    return new RequestFingerprint(sha256(value));
  }

  toHash(): string {
    return this.hash;
  }
}

export interface IdempotencyExecution {
  scopeId: EntityId;
  operation: IdempotencyOperation;
  key: IdempotencyKey;
  requestFingerprint: RequestFingerprint;
}

function sha256(value: string): string {
  return createHash('sha256').update(value, 'utf8').digest('hex');
}
