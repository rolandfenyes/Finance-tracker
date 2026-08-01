import {
  QueuedRecoveryNotifier,
  QueuedVerificationNotifier,
} from './notification-notifier.adapters';

describe('notification notifier adapters', () => {
  const config = {
    getOrThrow: jest.fn(() => 'http://localhost:4200'),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('uses the approved Angular email-verification route for registration mail', async () => {
    const { notifications, queue } = dependencies();
    const notifier = new QueuedVerificationNotifier(
      notifications as never,
      queue as never,
      config as never,
    );

    await notifier.sendVerification({
      email: 'synthetic@example.test',
      fullName: 'Synthetic User',
      token: 'synthetic/token?with=special&characters',
    });

    expect(notifications.prepare).toHaveBeenNthCalledWith(
      1,
      expect.objectContaining({
        data: expect.objectContaining({
          verification_link:
            'http://localhost:4200/auth/verify-email?token=synthetic%2Ftoken%3Fwith%3Dspecial%26characters',
        }),
      }),
    );
  });

  it('uses the approved Angular email-verification route for resend mail', async () => {
    const { notifications, queue } = dependencies();
    const notifier = new QueuedRecoveryNotifier(
      notifications as never,
      queue as never,
      config as never,
    );

    await notifier.sendEmailVerification({
      email: 'synthetic@example.test',
      fullName: 'Synthetic User',
      token: 'synthetic-token',
    });

    expect(notifications.prepare).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          verification_link: 'http://localhost:4200/auth/verify-email?token=synthetic-token',
        }),
      }),
    );
  });
});

function dependencies(): {
  notifications: { prepare: jest.Mock };
  queue: { enqueuePrepared: jest.Mock };
} {
  return {
    notifications: {
      prepare: jest.fn(({ templateCode }: { templateCode: string }) =>
        Promise.resolve({
          id: `delivery-${templateCode}`,
          status: 'queued',
          shouldQueue: true,
        }),
      ),
    },
    queue: { enqueuePrepared: jest.fn(() => Promise.resolve(undefined)) },
  };
}
