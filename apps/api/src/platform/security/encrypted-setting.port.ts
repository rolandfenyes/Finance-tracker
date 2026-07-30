import { inspect } from 'node:util';
import { REDACTED_SECRET, type SecretValue } from './secret-value';

export const ENCRYPTED_SETTING_PORT = Symbol('ENCRYPTED_SETTING_PORT');

export class EncryptedSetting {
  private constructor(private readonly ciphertext: string) {}

  static create(ciphertext: string): EncryptedSetting {
    if (ciphertext.length === 0) {
      throw new Error('Encrypted setting ciphertext must not be empty');
    }
    return new EncryptedSetting(ciphertext);
  }

  useCiphertext<T>(consumer: (ciphertext: string) => T): T {
    return consumer(this.ciphertext);
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

export interface EncryptedSettingPort {
  encrypt(secret: SecretValue): Promise<EncryptedSetting>;
  decrypt(setting: EncryptedSetting): Promise<SecretValue>;
}
