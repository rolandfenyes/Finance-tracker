import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';
import { EncryptedSetting, type EncryptedSettingPort } from './encrypted-setting.port';
import { SecretValue } from './secret-value';

const FORMAT_VERSION = 'v1';

@Injectable()
export class AesGcmEncryptedSettingAdapter implements EncryptedSettingPort {
  constructor(@Inject(ConfigService) private readonly config: ConfigService) {}

  encrypt(secret: SecretValue): Promise<EncryptedSetting> {
    const key = this.key();
    const iv = randomBytes(12);
    const cipher = createCipheriv('aes-256-gcm', key, iv);
    const encrypted = secret.use((plaintext) =>
      Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]),
    );
    return Promise.resolve(
      EncryptedSetting.create(
        [
          FORMAT_VERSION,
          iv.toString('base64url'),
          cipher.getAuthTag().toString('base64url'),
          encrypted.toString('base64url'),
        ].join('.'),
      ),
    );
  }

  decrypt(setting: EncryptedSetting): Promise<SecretValue> {
    return Promise.resolve(
      setting.useCiphertext((ciphertext) => {
        const [version, ivText, tagText, encryptedText, extra] = ciphertext.split('.');
        if (
          version !== FORMAT_VERSION ||
          !ivText ||
          !tagText ||
          !encryptedText ||
          extra !== undefined
        ) {
          throw new Error('Unsupported encrypted setting format');
        }
        const decipher = createDecipheriv(
          'aes-256-gcm',
          this.key(),
          Buffer.from(ivText, 'base64url'),
        );
        decipher.setAuthTag(Buffer.from(tagText, 'base64url'));
        const plaintext = Buffer.concat([
          decipher.update(Buffer.from(encryptedText, 'base64url')),
          decipher.final(),
        ]).toString('utf8');
        return SecretValue.create(plaintext);
      }),
    );
  }

  private key(): Buffer {
    const encoded = this.config.get<string>('SETTINGS_ENCRYPTION_KEY');
    if (!encoded) throw new Error('SETTINGS_ENCRYPTION_KEY is required to use encrypted settings');
    const key = Buffer.from(encoded, 'base64');
    if (key.length !== 32) {
      throw new Error('SETTINGS_ENCRYPTION_KEY must be a base64-encoded 32-byte key');
    }
    return key;
  }
}
