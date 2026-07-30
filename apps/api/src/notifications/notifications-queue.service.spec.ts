/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { NotificationsProcessor } from './notifications-queue.service';
import { Logger } from '@nestjs/common';

const delivery = {
  id: 'delivery-1',
  correlation_id: 'correlation-1',
  recipient_email: 'ada@example.test',
  template_code: 'welcome',
  locale: 'en' as const,
  template_data: { user_first_name: 'Ada' },
  status: 'queued',
  max_attempts: 3,
};
const clock = { now: () => ({ toDate: () => new Date('2026-07-30T08:00:00.000Z') }) };

describe('NotificationsProcessor', () => {
  it('records delivered provider status without logging message content', async () => {
    const log = jest.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    const repository = {
      delivery: jest.fn().mockResolvedValue(delivery),
      isSuppressed: jest.fn().mockResolvedValue(false),
      updateAttempt: jest.fn().mockResolvedValue(undefined),
    };
    const provider = { send: jest.fn().mockResolvedValue({ messageId: 'provider-1' }) };
    const renderer = {
      render: jest.fn().mockReturnValue({ subject: 'Welcome', textBody: 'Hello Ada' }),
    };
    const processor = new NotificationsProcessor(
      repository as never,
      renderer as never,
      provider,
      clock as never,
    );
    await processor.process({ deliveryId: delivery.id }, 1);
    expect(repository.updateAttempt).toHaveBeenLastCalledWith(
      delivery.id,
      expect.objectContaining({ status: 'delivered', providerMessageId: 'provider-1' }),
    );
    const logged = JSON.stringify(log.mock.calls);
    expect(logged).not.toContain(delivery.recipient_email);
    expect(logged).not.toContain('Hello Ada');
    expect(logged).not.toContain('Welcome');
    log.mockRestore();
  });

  it('records retryable failure then dead-letter at the bounded attempt', async () => {
    const repository = {
      delivery: jest.fn().mockResolvedValue(delivery),
      isSuppressed: jest.fn().mockResolvedValue(false),
      updateAttempt: jest.fn().mockResolvedValue(undefined),
    };
    const provider = { send: jest.fn().mockRejectedValue(new Error('provider failure')) };
    const renderer = {
      render: jest.fn().mockReturnValue({ subject: 'Welcome', textBody: 'Hello Ada' }),
    };
    const processor = new NotificationsProcessor(
      repository as never,
      renderer as never,
      provider,
      clock as never,
    );
    await expect(processor.process({ deliveryId: delivery.id }, 1)).rejects.toThrow();
    await expect(processor.process({ deliveryId: delivery.id }, 3)).rejects.toThrow();
    expect(
      repository.updateAttempt.mock.calls.some(
        (call: unknown[]) => (call[1] as { status: string }).status === 'retryable_failed',
      ),
    ).toBe(true);
    expect(
      repository.updateAttempt.mock.calls.some(
        (call: unknown[]) => (call[1] as { status: string }).status === 'dead_letter',
      ),
    ).toBe(true);
  });

  it('suppresses delivery before calling the provider', async () => {
    const repository = {
      delivery: jest.fn().mockResolvedValue(delivery),
      isSuppressed: jest.fn().mockResolvedValue(true),
      updateAttempt: jest.fn().mockResolvedValue(undefined),
    };
    const provider = { send: jest.fn() };
    const processor = new NotificationsProcessor(
      repository as never,
      {} as never,
      provider,
      clock as never,
    );
    await processor.process({ deliveryId: delivery.id }, 1);
    expect(provider.send).not.toHaveBeenCalled();
    expect(repository.updateAttempt).toHaveBeenCalledWith(
      delivery.id,
      expect.objectContaining({ status: 'suppressed' }),
    );
  });
});
