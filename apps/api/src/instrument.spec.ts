import { scrubSentryEvent, type ScrubbableSentryEvent } from './instrument';

describe('Sentry event privacy boundary', () => {
  it('removes request, identity, financial, and breadcrumb message data', () => {
    const event: ScrubbableSentryEvent = {
      request: {
        cookies: { session: 'secret' },
        data: { amount: '123.45' },
        headers: { authorization: 'secret' },
        query_string: 'email=person@example.test',
        url: 'https://api.example.test/api/v1/reports?user=secret',
      },
      user: { email: 'person@example.test' },
      extra: { balance: '999.99' },
      contexts: { financial: { netWorth: '999.99' } },
      breadcrumbs: [
        {
          category: 'http',
          level: 'info',
          message: 'person@example.test withdrew 123.45',
          timestamp: 1,
          type: 'http',
        },
      ],
    };

    expect(scrubSentryEvent(event)).toEqual({
      request: {
        cookies: undefined,
        data: undefined,
        headers: undefined,
        query_string: undefined,
        url: undefined,
      },
      user: undefined,
      extra: undefined,
      contexts: undefined,
      breadcrumbs: [
        {
          category: 'http',
          level: 'info',
          timestamp: 1,
          type: 'http',
        },
      ],
    });
  });
});
