import { Inject, Injectable, OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Queue, type ConnectionOptions, type JobType } from 'bullmq';
import { RedisSecurityService } from '../../identity/redis-security.service';
import { OperationsMetricsService } from './operations-metrics.service';

export const OPERATED_QUEUE_NAMES = [
  'mymoneymap-email-delivery',
  'mymoneymap-fx-refresh',
  'mymoneymap-privacy-deletion',
  'mymoneymap-privacy-export',
  'mymoneymap-recurrence',
  'mymoneymap-securities-refresh',
] as const;

const COUNTED_STATES = [
  'wait',
  'active',
  'completed',
  'failed',
  'delayed',
  'paused',
  'prioritized',
  'waiting-children',
] as const satisfies readonly JobType[];

export interface QueueOperationsSnapshot {
  queue: string;
  counts: Record<(typeof COUNTED_STATES)[number], number>;
  oldestPendingSeconds: number;
  alertCodes: string[];
}

@Injectable()
export class OperationsService implements OnApplicationShutdown {
  private readonly connection: ConnectionOptions;
  private readonly timeoutMs: number;
  private readonly queues = new Map<string, Queue>();

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(OperationsMetricsService) private readonly metrics: OperationsMetricsService,
    @Inject(RedisSecurityService) private readonly redis: RedisSecurityService,
  ) {
    this.connection = redisConnection(config.getOrThrow<string>('REDIS_URL'));
    this.timeoutMs = config.get<number>('REDIS_CONNECT_TIMEOUT_MS') ?? 2_000;
  }

  async queueSnapshot(now = new Date()): Promise<{
    generatedAt: string;
    queues: QueueOperationsSnapshot[];
    providerCircuits: { frankfurter: 'open' | 'closed'; finnhub: 'open' | 'closed' };
  }> {
    const queues = await withTimeout(
      Promise.all(OPERATED_QUEUE_NAMES.map((queueName) => this.inspectQueue(queueName, now))),
      this.timeoutMs,
    );
    const redis = await withTimeout(this.redis.ready(), this.timeoutMs);
    const [frankfurter, finnhub] = await Promise.all([
      redis.exists('mymoneymap:fx:circuit:frankfurter'),
      redis.exists('mymoneymap:securities:circuit:finnhub'),
    ]);
    return {
      generatedAt: now.toISOString(),
      queues,
      providerCircuits: {
        frankfurter: frankfurter > 0 ? 'open' : 'closed',
        finnhub: finnhub > 0 ? 'open' : 'closed',
      },
    };
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all([...this.queues.values()].map((queue) => queue.close()));
  }

  private async inspectQueue(queueName: string, now: Date): Promise<QueueOperationsSnapshot> {
    const queue = this.queue(queueName);
    const counts = (await queue.getJobCounts(...COUNTED_STATES)) as Record<
      (typeof COUNTED_STATES)[number],
      number
    >;
    const [oldest] = await queue.getJobs(['wait', 'delayed'], 0, 0, true);
    const oldestPendingSeconds = oldest
      ? Math.max(0, Math.floor((now.getTime() - oldest.timestamp) / 1_000))
      : 0;
    const alertCodes = [
      ...(counts.failed > 0 ? ['PERMANENT_FAILURE_PRESENT'] : []),
      ...(counts.wait + counts.prioritized > 100 ? ['QUEUE_BACKLOG_HIGH'] : []),
      ...(oldestPendingSeconds > 300 ? ['OLDEST_PENDING_OVER_300_SECONDS'] : []),
    ];
    this.metrics.observeQueue({ queue: queueName, counts, oldestPendingSeconds });
    return { queue: queueName, counts, oldestPendingSeconds, alertCodes };
  }

  private queue(name: string): Queue {
    const existing = this.queues.get(name);
    if (existing) return existing;
    const queue = new Queue(name, { connection: this.connection });
    this.queues.set(name, queue);
    return queue;
  }
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
    connectTimeout: 2_000,
    maxRetriesPerRequest: null,
  };
}

async function withTimeout<T>(promise: Promise<T>, timeoutMs: number): Promise<T> {
  let timeout: NodeJS.Timeout | undefined;
  try {
    return await Promise.race([
      promise,
      new Promise<T>((_resolve, reject) => {
        timeout = setTimeout(() => reject(new Error('Operations dependency timed out')), timeoutMs);
      }),
    ]);
  } finally {
    if (timeout) clearTimeout(timeout);
  }
}
