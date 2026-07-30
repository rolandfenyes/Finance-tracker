/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable, OnApplicationShutdown, OnModuleInit, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Job, Queue, Worker, type ConnectionOptions } from 'bullmq';
import { randomUUID } from 'node:crypto';
import { CLOCK, type Clock } from '../platform/time/clock';
import { SecuritiesRepository } from './securities.repository';
import {
  SECURITIES_MARKET_DATA_PROVIDER,
  type SecuritiesMarketDataProvider,
} from './securities.types';

export const SECURITIES_REFRESH_QUEUE = 'mymoneymap-securities-refresh';
const ATTEMPTS = 3;

interface RefreshJobData {
  requestId: string;
  instruments: Array<{ id: string; symbol: string; market: string; currency: string }>;
}

@Injectable()
export class SecuritiesRefreshProcessor {
  private consecutiveFailures = 0;
  private circuitOpenedAt: number | null = null;

  constructor(
    @Inject(SecuritiesRepository) private readonly repository: SecuritiesRepository,
    @Inject(SECURITIES_MARKET_DATA_PROVIDER)
    private readonly provider: SecuritiesMarketDataProvider,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async process(data: RefreshJobData, attempt: number): Promise<void> {
    const now = this.clock.now().toDate();
    if (this.circuitOpenedAt !== null && now.getTime() - this.circuitOpenedAt < 60_000) {
      throw new Error('Market-data circuit is temporarily open');
    }
    await this.repository.updateRefreshJob(data.requestId, {
      status: 'running',
      attemptCount: attempt,
      errorCode: null,
      startedAt: now,
    });
    try {
      const through = now.toISOString().slice(0, 10);
      const from = new Date(now.getTime() - 370 * 86_400_000).toISOString().slice(0, 10);
      for (const instrument of data.instruments) {
        const identity = { symbol: instrument.symbol, market: instrument.market };
        const [quote, prices, metadata] = await Promise.all([
          this.provider.quote(identity),
          this.provider.history(identity, from, through),
          this.provider.metadata(identity),
        ]);
        const currency = metadata?.currency ?? instrument.currency;
        await this.repository.upsertQuote({ ...quote, currency });
        await this.repository.upsertPrices(prices.map((price) => ({ ...price, currency })));
        if (metadata) await this.repository.updateMetadata(metadata);
      }
      this.consecutiveFailures = 0;
      this.circuitOpenedAt = null;
      await this.repository.updateRefreshJob(data.requestId, {
        status: 'completed',
        attemptCount: attempt,
        errorCode: null,
        finishedAt: this.clock.now().toDate(),
      });
    } catch (error) {
      this.consecutiveFailures += 1;
      if (this.consecutiveFailures >= 5) this.circuitOpenedAt = Date.now();
      await this.repository.updateRefreshJob(data.requestId, {
        status: attempt >= ATTEMPTS ? 'dead_letter' : 'retryable_failed',
        attemptCount: attempt,
        errorCode: providerErrorCode(error),
        finishedAt: attempt >= ATTEMPTS ? this.clock.now().toDate() : undefined,
      });
      throw error;
    }
  }
}

@Injectable()
export class SecuritiesRefreshQueueService implements OnModuleInit, OnApplicationShutdown {
  private readonly enabled: boolean;
  private readonly connection: ConnectionOptions;
  private queue?: Queue<RefreshJobData>;
  private worker?: Worker<RefreshJobData>;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(SecuritiesRepository) private readonly repository: SecuritiesRepository,
    @Inject(SecuritiesRefreshProcessor) private readonly processor: SecuritiesRefreshProcessor,
    @Inject(CLOCK) private readonly clock: Clock,
    @Optional()
    @Inject('SECURITIES_REFRESH_WORKER_DISABLED')
    private readonly workerDisabled?: boolean,
  ) {
    this.enabled = config.getOrThrow<boolean>('SECURITIES_MARKET_DATA_ENABLED');
    this.connection = connection(config.getOrThrow<string>('REDIS_URL'));
  }

  onModuleInit(): void {
    if (!this.enabled) return;
    this.queue = new Queue(SECURITIES_REFRESH_QUEUE, { connection: this.connection });
    if (!this.workerDisabled) {
      this.worker = new Worker(
        SECURITIES_REFRESH_QUEUE,
        (job: Job<RefreshJobData>) => this.processor.process(job.data, job.attemptsMade + 1),
        {
          connection: this.connection,
          concurrency: 1,
          limiter: { max: 30, duration: 60_000 },
        },
      );
    }
  }

  async enqueue(userId: string, requested: string[]) {
    if (!this.enabled) {
      return { status: 'disabled' as const, reason: 'production provider approval is pending' };
    }
    const instruments = await this.repository.instrumentsForRefresh(userId, requested);
    const requestId = randomUUID();
    const queueJobId = `securities-refresh-${requestId}`;
    const queue =
      this.queue ??
      new Queue<RefreshJobData>(SECURITIES_REFRESH_QUEUE, { connection: this.connection });
    this.queue = queue;
    await this.repository.createRefreshJob(
      userId,
      requestId,
      queueJobId,
      this.clock.now().toDate(),
    );
    await queue.add(
      'refresh-market-data',
      { requestId, instruments },
      {
        jobId: queueJobId,
        attempts: ATTEMPTS,
        backoff: { type: 'exponential', delay: 1_000 },
        removeOnComplete: { age: 86_400, count: 1_000 },
        removeOnFail: false,
      },
    );
    return { id: requestId, status: 'queued' as const, instrumentCount: instruments.length };
  }

  status(userId: string, jobId: string) {
    return this.repository.refreshJob(userId, jobId);
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all([this.worker?.close(), this.queue?.close()]);
  }
}

function providerErrorCode(error: unknown): string {
  if (error instanceof Error && error.name === 'TimeoutError') return 'provider_timeout';
  if (error instanceof Error && error.message.includes('429')) return 'provider_rate_limited';
  if (error instanceof Error && error.message.includes('configured')) return 'provider_disabled';
  return 'provider_failure';
}

function connection(redisUrl: string): ConnectionOptions {
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
