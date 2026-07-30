/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable, OnApplicationShutdown, OnModuleInit, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Job, Queue, Worker, type ConnectionOptions } from 'bullmq';
import { PrivacyDeletionProcessor, PrivacyDeletionService } from './privacy-deletion.service';
import { PrivacyExportProcessor, PrivacyExportService } from './privacy-export.service';
import {
  PRIVACY_DELETION_QUEUE,
  PRIVACY_EXPORT_QUEUE,
  PRIVACY_JOB_ATTEMPTS,
} from './privacy-queue.constants';
import { PrivacyRepository } from './privacy.repository';

interface PrivacyJobData {
  requestId: string;
}

@Injectable()
export class PrivacyQueueService implements OnModuleInit, OnApplicationShutdown {
  private readonly exportsEnabled: boolean;
  private readonly connection: ConnectionOptions;
  private exportQueue?: Queue<PrivacyJobData>;
  private deletionQueue?: Queue<PrivacyJobData>;
  private exportWorker?: Worker<PrivacyJobData>;
  private deletionWorker?: Worker<PrivacyJobData>;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PrivacyExportService) private readonly exports: PrivacyExportService,
    @Inject(PrivacyExportProcessor) private readonly exportProcessor: PrivacyExportProcessor,
    @Inject(PrivacyDeletionService) private readonly deletions: PrivacyDeletionService,
    @Inject(PrivacyDeletionProcessor) private readonly deletionProcessor: PrivacyDeletionProcessor,
    @Optional() @Inject('PRIVACY_WORKERS_DISABLED') private readonly workersDisabled?: boolean,
  ) {
    this.exportsEnabled = config.getOrThrow<boolean>('PRIVACY_EXPORTS_ENABLED');
    this.connection = redisConnection(config.getOrThrow<string>('REDIS_URL'));
  }

  onModuleInit(): void {
    if (this.exportsEnabled) {
      this.exportQueue = new Queue(PRIVACY_EXPORT_QUEUE, { connection: this.connection });
      if (!this.workersDisabled) {
        this.exportWorker = new Worker(
          PRIVACY_EXPORT_QUEUE,
          (job: Job<PrivacyJobData>) =>
            this.exportProcessor.process(job.data.requestId, job.attemptsMade + 1),
          { connection: this.connection, concurrency: 1 },
        );
      }
    }
    this.deletionQueue = new Queue(PRIVACY_DELETION_QUEUE, { connection: this.connection });
    if (!this.workersDisabled) {
      this.deletionWorker = new Worker(
        PRIVACY_DELETION_QUEUE,
        (job: Job<PrivacyJobData>) =>
          this.deletionProcessor.process(job.data.requestId, job.attemptsMade + 1),
        { connection: this.connection, concurrency: 1 },
      );
    }
  }

  async enqueueExport(userId: string, idempotencyKey: string) {
    const request = await this.exports.prepare(userId, idempotencyKey);
    const queue =
      this.exportQueue ??
      new Queue<PrivacyJobData>(PRIVACY_EXPORT_QUEUE, { connection: this.connection });
    this.exportQueue = queue;
    const queueJobId = `privacy-export-${request.id}`;
    await queue.add(
      'generate-account-export',
      { requestId: request.id },
      { ...queueOptions(), jobId: queueJobId },
    );
    await this.repository.markExportQueued(request.id, queueJobId);
    return {
      id: request.id,
      manifestVersion: request.manifest_version,
      status: request.status,
      createdAt: request.created_at.toISOString(),
    };
  }

  async enqueueDeletion(
    userId: string,
    input: { confirmEmail: string; password: string },
    idempotencyKey: string,
  ) {
    const request = await this.deletions.prepare(userId, input, idempotencyKey);
    const queue =
      this.deletionQueue ??
      new Queue<PrivacyJobData>(PRIVACY_DELETION_QUEUE, { connection: this.connection });
    this.deletionQueue = queue;
    const queueJobId = `privacy-deletion-${request.id}`;
    await queue.add(
      'delete-account-data',
      { requestId: request.id },
      { ...queueOptions(), jobId: queueJobId, removeOnComplete: false },
    );
    await this.repository.markDeletionQueued(request.id, queueJobId);
    return {
      id: request.id,
      status: request.status,
      createdAt: request.created_at.toISOString(),
    };
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all([
      this.exportWorker?.close(),
      this.deletionWorker?.close(),
      this.exportQueue?.close(),
      this.deletionQueue?.close(),
    ]);
  }
}

function queueOptions() {
  return {
    attempts: PRIVACY_JOB_ATTEMPTS,
    backoff: { type: 'exponential' as const, delay: 1_000 },
    removeOnComplete: { age: 86_400, count: 1_000 },
    removeOnFail: false as const,
  };
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
