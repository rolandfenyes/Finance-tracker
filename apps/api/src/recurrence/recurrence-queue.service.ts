import { Inject, Injectable, OnApplicationShutdown, OnModuleInit, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Job, Queue, Worker, type ConnectionOptions } from 'bullmq';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { expandRecurrence, InvalidRecurrenceRuleError } from './recurrence-rule';
import { RecurrenceRepository } from './recurrence.repository';

export const RECURRENCE_QUEUE = 'mymoneymap-recurrence';
export const RECURRENCE_MAX_ATTEMPTS = 3;

interface RecurrenceJob {
  dueThrough?: string;
}

export class RecurrenceCatchUpLimitError extends Error {
  constructor() {
    super('Recurrence catch-up exceeded the 2,000-iteration safety boundary');
    this.name = 'RecurrenceCatchUpLimitError';
  }
}

@Injectable()
export class RecurrenceProcessor {
  constructor(
    @Inject(RecurrenceRepository) private readonly repository: RecurrenceRepository,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async process(
    data: RecurrenceJob,
    attempt = 1,
    maxAttempts = RECURRENCE_MAX_ATTEMPTS,
    queueJobId = 'direct-test',
  ): Promise<{ status: 'completed' | 'duplicate'; occurrences: number }> {
    const dueThrough = data.dueThrough ?? this.clock.now().toString().slice(0, 10);
    CalendarDate.create(dueThrough);
    const jobKey = recurrenceJobKey(dueThrough);
    const now = this.clock.now().toDate();
    await this.repository.prepareExecution(jobKey, queueJobId, dueThrough, maxAttempts, now);
    const executionId = await this.repository.claimExecution(jobKey, attempt, now);
    if (!executionId) return { status: 'duplicate', occurrences: 0 };

    try {
      let occurrences = 0;
      await this.repository.transaction(async (transaction) => {
        const rules = await this.repository.materializedRules(transaction, dueThrough);
        for (const rule of rules) {
          const expansion = expandRecurrence(rule.startsOn, rule.rrule, rule.startsOn, dueThrough);
          if (expansion.truncated) throw new RecurrenceCatchUpLimitError();
          for (const dueOn of expansion.dates) {
            await this.repository.insertOccurrence(transaction, rule, dueOn, executionId, now);
            occurrences += 1;
          }
        }
        await this.repository.completeExecution(transaction, executionId, attempt, now);
      });
      return { status: 'completed', occurrences };
    } catch (error) {
      await this.repository.recordFailure(
        jobKey,
        attempt,
        errorCode(error),
        this.clock.now().toDate(),
      );
      throw error;
    }
  }
}

@Injectable()
export class RecurrenceQueueService implements OnModuleInit, OnApplicationShutdown {
  private readonly enabled: boolean;
  private readonly connection: ConnectionOptions;
  private queue?: Queue<RecurrenceJob>;
  private worker?: Worker<RecurrenceJob>;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(RecurrenceProcessor) private readonly processor: RecurrenceProcessor,
    @Inject(RecurrenceRepository) private readonly repository: RecurrenceRepository,
    @Inject(CLOCK) private readonly clock: Clock,
    @Optional()
    @Inject('RECURRENCE_QUEUE_WORKER_DISABLED')
    private readonly workerDisabled?: boolean,
  ) {
    this.enabled = config.getOrThrow<boolean>('RECURRENCE_ENABLED');
    this.connection = bullConnection(config.getOrThrow<string>('REDIS_URL'));
  }

  async onModuleInit(): Promise<void> {
    if (!this.enabled) return;
    this.queue = new Queue<RecurrenceJob>(RECURRENCE_QUEUE, { connection: this.connection });
    await this.queue.upsertJobScheduler(
      'daily-utc-recurrence-sweep',
      { pattern: '5 0 * * *', tz: 'UTC' },
      {
        name: 'materialize-due-forecasts',
        data: {},
        opts: queueOptions(),
      },
    );
    if (!this.workerDisabled) {
      this.worker = new Worker<RecurrenceJob>(
        RECURRENCE_QUEUE,
        async (job: Job<RecurrenceJob>) =>
          this.processor.process(
            job.data,
            job.attemptsMade + 1,
            job.opts.attempts ?? RECURRENCE_MAX_ATTEMPTS,
            job.id ?? 'bullmq',
          ),
        { connection: this.connection, concurrency: 2 },
      );
    }
  }

  async enqueueDueThrough(dueThrough?: string): Promise<string | null> {
    if (!this.enabled) return null;
    const date = dueThrough ?? this.clock.now().toString().slice(0, 10);
    CalendarDate.create(date);
    const jobId = recurrenceJobKey(date);
    const queue =
      this.queue ??
      new Queue<RecurrenceJob>(RECURRENCE_QUEUE, {
        connection: this.connection,
      });
    this.queue = queue;
    await this.repository.prepareExecution(
      recurrenceJobKey(date),
      jobId,
      date,
      RECURRENCE_MAX_ATTEMPTS,
      this.clock.now().toDate(),
    );
    const job = await queue.add(
      'materialize-due-forecasts',
      { dueThrough: date },
      { ...queueOptions(), jobId },
    );
    return job.id ?? null;
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all([this.worker?.close(), this.queue?.close()]);
  }
}

export function recurrenceJobKey(dueThrough: string): string {
  return `recurrence-catch-up-${dueThrough}`;
}

function queueOptions(): {
  attempts: number;
  backoff: { type: 'exponential'; delay: number };
  removeOnComplete: { age: number; count: number };
  removeOnFail: false;
} {
  return {
    attempts: RECURRENCE_MAX_ATTEMPTS,
    backoff: { type: 'exponential', delay: 500 },
    removeOnComplete: { age: 86_400, count: 1_000 },
    removeOnFail: false,
  };
}

function errorCode(error: unknown): string {
  if (error instanceof RecurrenceCatchUpLimitError) return 'catch_up_limit_exceeded';
  if (error instanceof InvalidRecurrenceRuleError) return 'invalid_stored_rrule';
  return 'recurrence_processing_failed';
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
