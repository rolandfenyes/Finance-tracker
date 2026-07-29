import { PasswordService } from './password.service';

describe('PasswordService', () => {
  const service = new PasswordService();

  it('uses the approved Argon2id minimum and verifies without exposing the password', async () => {
    const hash = await service.hash('synthetic-password');
    expect(hash).toMatch(/^\$argon2id\$v=19\$m=19456,t=2,p=1\$/);
    await expect(service.verify(hash, 'synthetic-password')).resolves.toBe(true);
    await expect(service.verify(hash, 'wrong-password')).resolves.toBe(false);
    expect(hash).not.toContain('synthetic-password');
  });

  it('fails closed for malformed hashes', async () => {
    await expect(service.verify('not-a-password-hash', 'synthetic-password')).resolves.toBe(false);
  });
});
