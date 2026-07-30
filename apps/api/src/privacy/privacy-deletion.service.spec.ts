import { UtcInstant } from '../platform/time/utc-instant';
import { FixedClock } from '../platform/time/clock';
import { PrivacyDeletionService } from './privacy-deletion.service';

describe('privacy deletion reauthentication', () => {
  const clock = new FixedClock(UtcInstant.fromDate(new Date('2026-07-30T10:00:00.000Z')));

  it('requires the current email and password and hashes the idempotency key', async () => {
    const repository = {
      userForReauthentication: jest.fn().mockResolvedValue({
        id: 'user-1',
        email: 'synthetic@example.test',
        password_hash: 'stored-hash',
        role: 'free',
      }),
      createDeletionRequest: jest.fn().mockResolvedValue({ id: 'request-1', status: 'queued' }),
    };
    const passwords = { verify: jest.fn().mockResolvedValue(true) };
    const service = new PrivacyDeletionService(repository as never, passwords as never, clock);
    await expect(
      service.prepare(
        'user-1',
        { confirmEmail: 'SYNTHETIC@example.test', password: 'current-password' },
        'delete-stable-key',
      ),
    ).resolves.toMatchObject({ id: 'request-1' });
    expect(repository.createDeletionRequest).toHaveBeenCalledWith(
      expect.objectContaining({
        userId: 'user-1',
        idempotencyKeyHash: expect.stringMatching(/^[0-9a-f]{64}$/),
      }),
    );
  });

  it('does not expose which confirmation factor was incorrect', async () => {
    const repository = {
      userForReauthentication: jest.fn().mockResolvedValue({
        email: 'synthetic@example.test',
        password_hash: 'stored-hash',
        role: 'free',
      }),
    };
    const passwords = { verify: jest.fn().mockResolvedValue(false) };
    const service = new PrivacyDeletionService(repository as never, passwords as never, clock);
    await expect(
      service.prepare(
        'user-1',
        { confirmEmail: 'synthetic@example.test', password: 'wrong-password' },
        'delete-stable-key',
      ),
    ).rejects.toMatchObject({ code: 'FORBIDDEN' });
  });
});
