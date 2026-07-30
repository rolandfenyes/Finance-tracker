/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { NotificationsService } from './notifications.service';

const clock = { now: () => ({ toDate: () => new Date('2026-07-30T08:00:00.000Z') }) };
const config = {
  getOrThrow: (key: string) =>
    (
      ({ EMAIL_DELIVERY_ENABLED: true, EMAIL_DELIVERY_PRODUCTION_APPROVED: false }) as Record<
        string,
        unknown
      >
    )[key],
};

function setup(overrides: Record<string, unknown> = {}) {
  const repository = {
    userForEmail: jest.fn().mockResolvedValue({ id: 'user-1', desired_language: 'es' }),
    preference: jest.fn().mockResolvedValue({ educationalEnabled: true }),
    isSuppressed: jest.fn().mockResolvedValue(false),
    createDelivery: jest
      .fn()
      .mockResolvedValue({ id: 'delivery-1', status: 'queued', queue_job_id: null }),
    setPreference: jest.fn(),
    channel: jest.fn(),
    updateChannel: jest.fn(),
    ...overrides,
  };
  return {
    service: new NotificationsService(repository as never, config as never, clock as never),
    repository,
  };
}

describe('NotificationsService', () => {
  it('falls back unsupported locales to English and renders all evidenced locales', () => {
    const { service } = setup();
    const data = { user_first_name: 'Ada' };
    expect(service.render('welcome', 'es-MX', data).locale).toBe('es');
    expect(service.render('welcome', 'hu-HU', data).locale).toBe('hu');
    expect(service.render('welcome', 'fr', data).locale).toBe('en');
  });

  it('rejects missing and unknown template fields', () => {
    const { service } = setup();
    expect(() => service.render('welcome', 'en', {})).toThrow(/contract/);
    expect(() => service.render('welcome', 'en', { user_first_name: 'Ada', extra: 'x' })).toThrow(
      /contract/,
    );
  });

  it('uses the same event key for duplicate-event idempotency', async () => {
    const { service, repository } = setup();
    const input = {
      eventKey: 'user.registered:123',
      recipientEmail: 'ADA@EXAMPLE.TEST',
      templateCode: 'welcome',
      data: { user_first_name: 'Ada' },
    };
    await service.prepare(input);
    await service.prepare(input);
    const keys = repository.createDelivery.mock.calls.map(
      (call: unknown[]) => (call[0] as { eventKey: string }).eventKey,
    );
    expect(keys[0]).toBe(keys[1]);
    expect(keys[0]).not.toContain('ADA@EXAMPLE.TEST');
  });

  it('blocks educational mail by preference but not transactional verification', async () => {
    const { service, repository } = setup({
      preference: jest.fn().mockResolvedValue({ educationalEnabled: false }),
    });
    await service.prepare({
      eventKey: 'tips:1',
      recipientEmail: 'ada@example.test',
      templateCode: 'tips_and_tricks',
      data: {
        user_first_name: 'Ada',
        tip_title: 'Review',
        tip_body: 'Review weekly',
        tip_link: 'https://example.test',
      },
    });
    await service.prepare({
      eventKey: 'verify:1',
      recipientEmail: 'ada@example.test',
      templateCode: 'registration_validation',
      data: { user_first_name: 'Ada', verification_link: 'https://example.test/verify' },
    });
    expect(repository.createDelivery.mock.calls[0][0].status).toBe('preference_blocked');
    expect(repository.createDelivery.mock.calls[1][0].status).toBe('queued');
  });

  it('does not expose provider secrets in template or channel responses', async () => {
    const { service } = setup({
      channel: jest.fn().mockResolvedValue({ enabled: false, provider: 'disabled' }),
    });
    expect(JSON.stringify(service.templates())).not.toMatch(/token|secret/i);
    expect(JSON.stringify(await service.channel())).not.toMatch(/token|secret/i);
  });
});
