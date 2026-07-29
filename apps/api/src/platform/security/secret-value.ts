import { inspect } from 'node:util';

export const REDACTED_SECRET = '[REDACTED]';

export class SecretValue {
  private constructor(private readonly plaintext: string) {}

  static create(plaintext: string): SecretValue {
    if (plaintext.length === 0) {
      throw new Error('Secret value must not be empty');
    }
    return new SecretValue(plaintext);
  }

  use<T>(consumer: (plaintext: string) => T): T {
    return consumer(this.plaintext);
  }

  toJSON(): string {
    return REDACTED_SECRET;
  }

  toString(): string {
    return REDACTED_SECRET;
  }

  [inspect.custom](): string {
    return REDACTED_SECRET;
  }
}
