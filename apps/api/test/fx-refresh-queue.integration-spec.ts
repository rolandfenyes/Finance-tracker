import { ConfigService } from '@nestjs/config';
import { Queue, type ConnectionOptions } from 'bullmq';
import { CurrencyRepository } from '../src/currency/currency.repository';
import { DeterministicFxProvider } from '../src/currency/deterministic-fx.provider';
import {
  FX_REFRESH_QUEUE,
  FxRefreshProcessor,
  FxRefreshQueueService,
} from '../src/currency/fx-refresh-queue.service';
import { RedisSecurityService } from '../src/identity/redis-security.service';
import { migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('FX refresh queue and circuit breaker', () => {
  it('deduplicates stable jobs and retains bounded retry/dead-letter configuration', async () => {
    const config = new ConfigService({
      FX_REFRESH_ENABLED: true,
      REDIS_URL: process.env.REDIS_URL,
    });
    const redis = new RedisSecurityService(config);
    const processor = {} as FxRefreshProcessor;
    const service = new FxRefreshQueueService(config, processor, true);
    service.onModuleInit();
    const inspector = new Queue(FX_REFRESH_QUEUE, {
      connection: connection(process.env.REDIS_URL!),
    });
    try {
      await inspector.obliterate({ force: true });
      const first = await service.enqueue('USD', '2026-07-24');
      const second = await service.enqueue('USD', '2026-07-24');
      expect(second).toBe(first);
      const job = await inspector.getJob(first!);
      expect(job).not.toBeUndefined();
      expect(job?.opts).toMatchObject({
        attempts: 3,
        backoff: { type: 'exponential', delay: 500 },
        removeOnFail: false,
      });
    } finally {
      await service.onApplicationShutdown();
      await inspector.obliterate({ force: true });
      await inspector.close();
      await redis.onApplicationShutdown();
    }
  });

  it('opens the provider circuit after bounded synthetic failures and stores no quote', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const config = new ConfigService({ REDIS_URL: process.env.REDIS_URL });
      const redis = new RedisSecurityService(config);
      const client = await redis.ready();
      await client.del([
        'mymoneymap:fx:circuit:frankfurter',
        'mymoneymap:fx:circuit:frankfurter:failures',
      ]);
      const repository = new CurrencyRepository(database);
      const provider = new DeterministicFxProvider(
        { '2026-07-24:USD': '1.25' },
        new Date('2026-07-24T12:00:00Z'),
        3,
      );
      const processor = new FxRefreshProcessor(repository, provider, redis);
      try {
        for (let attempt = 0; attempt < 3; attempt += 1) {
          await expect(processor.process({ currency: 'USD', asOf: '2026-07-24' })).rejects.toThrow(
            'Synthetic provider failure',
          );
        }
        await expect(processor.process({ currency: 'USD', asOf: '2026-07-24' })).rejects.toThrow(
          'circuit is open',
        );
        const rows = await pool.query(
          `SELECT 1 FROM mymoneymap.fx_quotes
            WHERE provider = 'frankfurter' AND quote_code = 'USD'`,
        );
        expect(rows.rowCount).toBe(0);
      } finally {
        await client.del([
          'mymoneymap:fx:circuit:frankfurter',
          'mymoneymap:fx:circuit:frankfurter:failures',
        ]);
        await redis.onApplicationShutdown();
      }
    });
  });
});

function connection(redisUrl: string): ConnectionOptions {
  const url = new URL(redisUrl);
  return {
    host: url.hostname,
    port: Number.parseInt(url.port || '6379', 10),
    maxRetriesPerRequest: null,
  };
}
