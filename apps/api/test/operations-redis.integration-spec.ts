import { ConfigService } from '@nestjs/config';
import { Queue, QueueEvents, Worker, type ConnectionOptions } from 'bullmq';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { OperationsMetricsService } from '../src/platform/operations/operations-metrics.service';
import {
  OPERATED_QUEUE_NAMES,
  OperationsService,
} from '../src/platform/operations/operations.service';

jest.setTimeout(30_000);

describe('operations Redis diagnostics', () => {
  it('exposes PII-safe backlog, permanent failure, age, and shared circuit signals', async () => {
    const redisUrl = process.env.REDIS_URL;
    if (!redisUrl) throw new Error('REDIS_URL is required for operations integration tests');
    const config = new ConfigService({ REDIS_URL: redisUrl });
    const redis = new RedisSecurityService(config);
    const metrics = new OperationsMetricsService();
    const operations = new OperationsService(config, metrics, redis);
    const queueName = OPERATED_QUEUE_NAMES[0];
    const options = { connection: connection(redisUrl) };
    const queue = new Queue(queueName, options);
    const events = new QueueEvents(queueName, options);
    const worker = new Worker(
      queueName,
      () => Promise.reject(new Error('synthetic safe failure')),
      options,
    );
    const client = await redis.ready();

    try {
      await queue.obliterate({ force: true });
      await client.set('mymoneymap:securities:circuit:finnhub', '1', { EX: 60 });
      const job = await queue.add(
        'synthetic',
        { intentionally: 'non-sensitive' },
        { attempts: 1, removeOnFail: false },
      );
      await expect(job.waitUntilFinished(events, 10_000)).rejects.toThrow('synthetic safe failure');
      await worker.pause();
      await queue.add(
        'synthetic-delayed',
        {},
        { delay: 600_000, timestamp: job.timestamp - 301_000 },
      );

      const snapshot = await operations.queueSnapshot(new Date(job.timestamp));
      const observed = snapshot.queues.find((item) => item.queue === queueName)!;
      expect(observed.counts.failed).toBe(1);
      expect(observed.alertCodes).toContain('PERMANENT_FAILURE_PRESENT');
      expect(observed.alertCodes).toContain('OLDEST_PENDING_OVER_300_SECONDS');
      expect(snapshot.providerCircuits.finnhub).toBe('open');
      expect(JSON.stringify(snapshot)).not.toContain('non-sensitive');
      expect(JSON.stringify(snapshot)).not.toContain('synthetic safe failure');
      expect(await metrics.metrics()).toContain('mymoneymap_queue_jobs');
    } finally {
      await worker.close();
      await events.close();
      await queue.obliterate({ force: true });
      await queue.close();
      await client.del('mymoneymap:securities:circuit:finnhub');
      await operations.onApplicationShutdown();
      await redis.onApplicationShutdown();
    }
  });
});

function connection(redisUrl: string): ConnectionOptions {
  const url = new URL(redisUrl);
  const database =
    url.pathname === '' || url.pathname === '/' ? 0 : Number.parseInt(url.pathname.slice(1), 10);
  return {
    host: url.hostname,
    port: Number.parseInt(url.port || '6379', 10),
    username: url.username || undefined,
    password: url.password || undefined,
    db: Number.isSafeInteger(database) ? database : 0,
    maxRetriesPerRequest: null,
  };
}
