import { Inject, Injectable, OnApplicationShutdown, OnModuleInit, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Job, Queue, Worker, type ConnectionOptions } from 'bullmq';
import { RedisSecurityService } from '../identity/redis-security.service';
import { CalendarDate } from '../platform/time/calendar-date';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { CurrencyRepository } from './currency.repository';
import type { FxProvider } from './currency.types';
import { FrankfurterFxProvider } from './frankfurter-fx.provider';

export const FX_REFRESH_QUEUE = 'mymoneymap-fx-refresh';

interface FxRefreshJob {
  currency: string;
  asOf: string;
}

@Injectable()
export class FxRefreshProcessor {
  constructor(
    @Inject(CurrencyRepository) private readonly repository: CurrencyRepository,
    @Inject(FrankfurterFxProvider) private readonly provider: FxProvider,
    @Inject(RedisSecurityService) private readonly redis: RedisSecurityService,
  ) {}

  async process(data: FxRefreshJob): Promise<{ status: 'stored' | 'unavailable' }> {
    CurrencyCode.create(data.currency);
    CalendarDate.create(data.asOf);
    const redis = await this.redis.ready();
    const circuitKey = 'mymoneymap:fx:circuit:frankfurter';
    if (await redis.get(circuitKey)) throw new Error('Frankfurter circuit is open');
    try {
      const quote = await this.provider.fetchEurQuote(data.currency, data.asOf);
      if (!quote) return { status: 'unavailable' };
      await this.repository.storeQuote(quote);
      await redis.del([`${circuitKey}:failures`, circuitKey]);
      return { status: 'stored' };
    } catch (error) {
      const failures = await redis.incr(`${circuitKey}:failures`);
      await redis.expire(`${circuitKey}:failures`, 300);
      if (failures >= 3) await redis.set(circuitKey, 'open', { EX: 60 });
      throw error;
    }
  }
}

@Injectable()
export class FxRefreshQueueService implements OnModuleInit, OnApplicationShutdown {
  private readonly enabled: boolean;
  private readonly connection: ConnectionOptions;
  private queue?: Queue<FxRefreshJob>;
  private worker?: Worker<FxRefreshJob>;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(FxRefreshProcessor) private readonly processor: FxRefreshProcessor,
    @Optional() @Inject('FX_QUEUE_WORKER_DISABLED') private readonly workerDisabled?: boolean,
  ) {
    this.enabled = config.getOrThrow<boolean>('FX_REFRESH_ENABLED');
    this.connection = bullConnection(config.getOrThrow<string>('REDIS_URL'));
  }

  onModuleInit(): void {
    if (!this.enabled) return;
    this.queue = new Queue<FxRefreshJob>(FX_REFRESH_QUEUE, { connection: this.connection });
    if (!this.workerDisabled) {
      this.worker = new Worker<FxRefreshJob>(
        FX_REFRESH_QUEUE,
        async (job: Job<FxRefreshJob>) => this.processor.process(job.data),
        { connection: this.connection, concurrency: 2 },
      );
    }
  }

  async enqueue(currency: string, asOf: string): Promise<string | null> {
    if (!this.enabled) return null;
    CurrencyCode.create(currency);
    CalendarDate.create(asOf);
    const queue =
      this.queue ??
      new Queue<FxRefreshJob>(FX_REFRESH_QUEUE, {
        connection: this.connection,
      });
    this.queue = queue;
    const job = await queue.add(
      'refresh-dated-eur-quote',
      { currency, asOf },
      {
        jobId: `frankfurter-${asOf}-${currency}`,
        attempts: 3,
        backoff: { type: 'exponential', delay: 500 },
        removeOnComplete: { age: 86_400, count: 1_000 },
        removeOnFail: false,
      },
    );
    return job.id ?? null;
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all([this.worker?.close(), this.queue?.close()]);
  }
}

function bullConnection(redisUrl: string): ConnectionOptions {
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
