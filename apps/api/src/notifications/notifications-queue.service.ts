/* eslint-disable @typescript-eslint/explicit-function-return-type */
import {
  Inject,
  Injectable,
  Logger,
  OnApplicationShutdown,
  OnModuleInit,
  Optional,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Job, Queue, Worker, type ConnectionOptions } from 'bullmq';
import { CLOCK, type Clock } from '../platform/time/clock';
import { EMAIL_PROVIDER_PORT, type EmailProvider } from './email-provider';
import { NotificationsRepository } from './notifications.repository';
import { NotificationsService } from './notifications.service';

export const NOTIFICATIONS_QUEUE = 'mymoneymap-email-delivery';
const ATTEMPTS = 3;
interface EmailJobData {
  deliveryId: string;
}

@Injectable()
export class NotificationsProcessor {
  private readonly logger = new Logger(NotificationsProcessor.name);
  constructor(
    @Inject(NotificationsRepository) private readonly repository: NotificationsRepository,
    @Inject(NotificationsService) private readonly notifications: NotificationsService,
    @Inject(EMAIL_PROVIDER_PORT) private readonly provider: EmailProvider,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async process(data: EmailJobData, attempt: number): Promise<void> {
    const delivery = await this.repository.delivery(data.deliveryId);
    if (!delivery || delivery.status === 'delivered') return;
    if (await this.repository.isSuppressed(delivery.recipient_email)) {
      await this.repository.updateAttempt(delivery.id, {
        status: 'suppressed',
        attempt,
        errorCode: 'recipient_suppressed',
        now: this.clock.now().toDate(),
      });
      this.logState(delivery, 'suppressed', attempt, 'recipient_suppressed');
      return;
    }
    await this.repository.updateAttempt(delivery.id, {
      status: 'running',
      attempt,
      now: this.clock.now().toDate(),
    });
    try {
      const rendered = this.notifications.render(
        delivery.template_code,
        delivery.locale,
        delivery.template_data,
      );
      const result = await this.provider.send({
        to: delivery.recipient_email,
        subject: rendered.subject,
        textBody: rendered.textBody,
        correlationId: delivery.correlation_id,
      });
      await this.repository.updateAttempt(delivery.id, {
        status: 'delivered',
        attempt,
        providerMessageId: result.messageId,
        now: this.clock.now().toDate(),
      });
      this.logState(delivery, 'delivered', attempt);
    } catch (error) {
      const terminal = attempt >= delivery.max_attempts;
      await this.repository.updateAttempt(delivery.id, {
        status: terminal ? 'dead_letter' : 'retryable_failed',
        attempt,
        errorCode: providerErrorCode(error),
        now: this.clock.now().toDate(),
      });
      this.logState(
        delivery,
        terminal ? 'dead_letter' : 'retryable_failed',
        attempt,
        providerErrorCode(error),
      );
      throw error;
    }
  }

  private logState(
    delivery: {
      id: string;
      correlation_id: string;
      template_code: string;
    },
    status: string,
    attempt: number,
    errorCode?: string,
  ): void {
    this.logger.log(
      {
        deliveryId: delivery.id,
        correlationId: delivery.correlation_id,
        templateCode: delivery.template_code,
        status,
        attempt,
        ...(errorCode ? { errorCode } : {}),
      },
      'Email delivery state changed',
    );
  }
}

@Injectable()
export class NotificationsQueueService implements OnModuleInit, OnApplicationShutdown {
  private queue?: Queue<EmailJobData>;
  private worker?: Worker<EmailJobData>;
  private readonly connection: ConnectionOptions;
  constructor(
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(NotificationsRepository) private readonly repository: NotificationsRepository,
    @Inject(NotificationsProcessor) private readonly processor: NotificationsProcessor,
    @Inject(CLOCK) private readonly clock: Clock,
    @Optional() @Inject('EMAIL_WORKER_DISABLED') private readonly workerDisabled?: boolean,
  ) {
    this.connection = redisConnection(config.getOrThrow<string>('REDIS_URL'));
  }
  onModuleInit(): void {
    if (!this.config.getOrThrow<boolean>('EMAIL_DELIVERY_ENABLED')) return;
    this.queue = new Queue(NOTIFICATIONS_QUEUE, { connection: this.connection });
    if (!this.workerDisabled) {
      this.worker = new Worker(
        NOTIFICATIONS_QUEUE,
        (job: Job<EmailJobData>) => this.processor.process(job.data, job.attemptsMade + 1),
        {
          connection: this.connection,
          concurrency: 4,
        },
      );
    }
  }
  async enqueuePrepared(delivery: { id: string; status: string; shouldQueue: boolean }) {
    if (!delivery.shouldQueue) return delivery;
    const queue =
      this.queue ?? new Queue<EmailJobData>(NOTIFICATIONS_QUEUE, { connection: this.connection });
    this.queue = queue;
    const queueJobId = `email-${delivery.id}`;
    await queue.add(
      'deliver-email',
      { deliveryId: delivery.id },
      {
        jobId: queueJobId,
        attempts: ATTEMPTS,
        backoff: { type: 'exponential', delay: 1_000 },
        removeOnComplete: { age: 86_400, count: 1_000 },
        removeOnFail: false,
      },
    );
    await this.repository.markQueued(delivery.id, queueJobId, this.clock.now().toDate());
    return { id: delivery.id, status: 'queued' };
  }
  async onApplicationShutdown(): Promise<void> {
    await Promise.all([this.worker?.close(), this.queue?.close()]);
  }
}

function providerErrorCode(error: unknown): string {
  if (error instanceof Error && error.name === 'TimeoutError') return 'provider_timeout';
  if (error instanceof Error && error.message.includes('disabled')) return 'provider_disabled';
  if (error instanceof Error && error.message.includes('429')) return 'provider_rate_limited';
  return 'provider_failure';
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
