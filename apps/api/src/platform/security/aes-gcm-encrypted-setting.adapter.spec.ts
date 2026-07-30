import { ConfigService } from '@nestjs/config';
import { AesGcmEncryptedSettingAdapter } from './aes-gcm-encrypted-setting.adapter';
import { REDACTED_SECRET, SecretValue } from './secret-value';

describe('AesGcmEncryptedSettingAdapter', () => {
  const plaintext = 'synthetic-write-only-secret';
  const adapter = new AesGcmEncryptedSettingAdapter(
    new ConfigService({
      SETTINGS_ENCRYPTION_KEY: Buffer.from('synthetic-test-key-is-32-bytes!!').toString('base64'),
    }),
  );

  it('authenticates encryption, decrypts only through the port, and redacts serialization', async () => {
    const encrypted = await adapter.encrypt(SecretValue.create(plaintext));
    const ciphertext = encrypted.useCiphertext((value) => value);
    expect(ciphertext).toMatch(/^v1\./);
    expect(ciphertext).not.toContain(plaintext);
    expect(JSON.stringify(encrypted)).toBe(JSON.stringify(REDACTED_SECRET));

    const decrypted = await adapter.decrypt(encrypted);
    expect(decrypted.use((value) => value)).toBe(plaintext);
    expect(decrypted.toString()).toBe(REDACTED_SECRET);
  });

  it('rejects missing and wrong-length encryption keys', () => {
    const missing = new AesGcmEncryptedSettingAdapter({
      get: () => undefined,
    } as unknown as ConfigService);
    expect(() => missing.encrypt(SecretValue.create(plaintext))).toThrow('SETTINGS_ENCRYPTION_KEY');

    const short = new AesGcmEncryptedSettingAdapter({
      get: () => Buffer.from('short').toString('base64'),
    } as unknown as ConfigService);
    expect(() => short.encrypt(SecretValue.create(plaintext))).toThrow('32-byte');
  });
});
