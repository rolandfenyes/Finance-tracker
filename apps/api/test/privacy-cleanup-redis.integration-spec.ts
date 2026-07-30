import { ConfigService } from '@nestjs/config';
import { Queue, type ConnectionOptions } from 'bullmq';
import { createHash, randomUUID } from 'node:crypto';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { NOTIFICATIONS_QUEUE } from '../src/notifications/notifications-queue.service';
import type { PrivateObjectStorage } from '../src/privacy/private-object-storage';
import { PrivacyCleanupService } from '../src/privacy/privacy-cleanup.service';
import { PRIVACY_EXPORT_QUEUE } from '../src/privacy/privacy-queue.constants';
import { SECURITIES_REFRESH_QUEUE } from '../src/securities/securities-refresh-queue.service';

jest.setTimeout(30_000);

describe('privacy external-state cleanup', () => {
  it('removes owned object, queue, session, and login-throttle state and is retry-safe', async () => {
    const redisUrl = process.env.REDIS_URL;
    if (!redisUrl) throw new Error('REDIS_URL is required for privacy cleanup integration tests');
    const config = new ConfigService({ REDIS_URL: redisUrl });
    const connection = redisConnection(redisUrl);
    const queues = [
      new Queue(PRIVACY_EXPORT_QUEUE, { connection }),
      new Queue(NOTIFICATIONS_QUEUE, { connection }),
      new Queue(SECURITIES_REFRESH_QUEUE, { connection }),
    ];
    const redis = new RedisSecurityService(config);
    const client = await redis.ready();
    const suffix = randomUUID();
    const userId = randomUUID();
    const email = `privacy-cleanup-${suffix}@example.test`;
    const sessionId = `privacy-session-${suffix}`;
    const objectKey = `privacy-exports/${suffix}/complete_export.json`;
    const jobIds = [
      `privacy-export-${suffix}`,
      `privacy-email-${suffix}`,
      `privacy-securities-${suffix}`,
    ];
    const storage = new MemoryStorage();
    storage.objects.add(objectKey);
    const repository = {
      cleanupInventory: jest.fn().mockResolvedValue({
        objectKeys: [objectKey],
        exportQueueJobIds: [jobIds[0]],
        emailQueueJobIds: [jobIds[1]],
        securitiesQueueJobIds: [jobIds[2]],
        email,
      }),
    };
    const cleanup = new PrivacyCleanupService(config, redis, repository as never, storage);
    const registryKey = `mymoneymap:user-sessions:${userId}`;
    const sessionKey = `mymoneymap:session:${sessionId}`;
    const loginRateKey = loginThrottleKey(email);

    try {
      await Promise.all(
        queues.map((queue, index) =>
          queue.add('synthetic-owned-job', { synthetic: true }, { jobId: jobIds[index] }),
        ),
      );
      await client.sAdd(registryKey, sessionId);
      await client.set(sessionKey, 'synthetic-session');
      await client.set(loginRateKey, '1');

      await cleanup.cleanup(userId);
      await cleanup.cleanup(userId);

      expect(storage.objects.has(objectKey)).toBe(false);
      await expect(
        Promise.all(queues.map((queue, index) => queue.getJob(jobIds[index]!))),
      ).resolves.toEqual([undefined, undefined, undefined]);
      await expect(client.exists([registryKey, sessionKey, loginRateKey])).resolves.toBe(0);
      expect(repository.cleanupInventory).toHaveBeenCalledTimes(2);
    } finally {
      await Promise.all([cleanup.onApplicationShutdown(), ...queues.map((queue) => queue.close())]);
      await redis.onApplicationShutdown();
    }
  });
});

class MemoryStorage implements PrivateObjectStorage {
  readonly objects = new Set<string>();

  put(input: { key: string }): Promise<void> {
    this.objects.add(input.key);
    return Promise.resolve();
  }

  delete(key: string): Promise<void> {
    this.objects.delete(key);
    return Promise.resolve();
  }

  signedGetUrl(): Promise<string> {
    return Promise.reject(new Error('not_used'));
  }
}

function loginThrottleKey(email: string): string {
  return `mymoneymap:login-rate:${createHash('sha256')
    .update(`account:${email.trim().toLowerCase()}`)
    .digest('hex')}`;
}

function redisConnection(redisUrl: string): ConnectionOptions {
  const url = new URL(redisUrl);
  const database = url.pathname === '/' ? 0 : Number.parseInt(url.pathname.slice(1), 10);
  return {
    host: url.hostname,
    port: Number.parseInt(url.port || '6379', 10),
    username: url.username || undefined,
    password: url.password || undefined,
    db: Number.isSafeInteger(database) ? database : 0,
    tls: url.protocol === 'rediss:' ? {} : undefined,
    maxRetriesPerRequest: null,
  };
}
