import { ConfigService } from '@nestjs/config';
import { applyHttpServerTimeouts, consumeFixedWindowRateLimit } from './http-hardening';

describe('HTTP server hardening', () => {
  it('applies bounded request, header, and keep-alive timeouts', () => {
    const server = { requestTimeout: 0, headersTimeout: 0, keepAliveTimeout: 0 };
    const config = new ConfigService({
      HTTP_REQUEST_TIMEOUT_MS: 15_000,
      HTTP_HEADERS_TIMEOUT_MS: 10_000,
      HTTP_KEEP_ALIVE_TIMEOUT_MS: 5_000,
    });

    applyHttpServerTimeouts(server, config);

    expect(server).toEqual({
      requestTimeout: 15_000,
      headersTimeout: 10_000,
      keepAliveTimeout: 5_000,
    });
  });

  it('uses an expiring HMAC-pseudonymized shared rate-limit bucket', async () => {
    const counts = new Map<string, number>();
    const expire = jest.fn<Promise<unknown>, [string, number]>(() => Promise.resolve(undefined));
    const redis = {
      incr: (key: string): Promise<number> => {
        const next = (counts.get(key) ?? 0) + 1;
        counts.set(key, next);
        return Promise.resolve(next);
      },
      expire,
    };
    const input = {
      scope: 'api' as const,
      subject: '203.0.113.10',
      secret: 'synthetic-session-secret-at-least-32-characters',
      maximum: 2,
      windowSeconds: 60,
      nowMs: 1_000,
    };

    expect(await consumeFixedWindowRateLimit(redis, input)).toEqual({
      remaining: 1,
      rejected: false,
    });
    expect(await consumeFixedWindowRateLimit(redis, input)).toEqual({
      remaining: 0,
      rejected: false,
    });
    expect(await consumeFixedWindowRateLimit(redis, input)).toEqual({
      remaining: 0,
      rejected: true,
    });
    expect(expire).toHaveBeenCalledTimes(1);
    expect(expire).toHaveBeenCalledWith(expect.not.stringContaining(input.subject), 61);
  });
});
