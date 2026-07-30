import { Inject, Injectable, OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Queue, type ConnectionOptions } from 'bullmq';
import { createHash } from 'node:crypto';
import { RedisSecurityService } from '../identity/redis-security.service';
import { NOTIFICATIONS_QUEUE } from '../notifications/notifications-queue.service';
import { SECURITIES_REFRESH_QUEUE } from '../securities/securities-refresh-queue.service';
import { PRIVATE_OBJECT_STORAGE, type PrivateObjectStorage } from './private-object-storage';
import { PrivacyRepository } from './privacy.repository';
import { PRIVACY_EXPORT_QUEUE } from './privacy-queue.constants';

@Injectable()
export class PrivacyCleanupService implements OnApplicationShutdown {
  private readonly queues: Queue[];

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(RedisSecurityService) private readonly redis: RedisSecurityService,
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PRIVATE_OBJECT_STORAGE) private readonly storage: PrivateObjectStorage,
  ) {
    const connection = redisConnection(config.getOrThrow<string>('REDIS_URL'));
    this.queues = [
      new Queue(PRIVACY_EXPORT_QUEUE, { connection }),
      new Queue(NOTIFICATIONS_QUEUE, { connection }),
      new Queue(SECURITIES_REFRESH_QUEUE, { connection }),
    ];
  }

  async cleanup(userId: string): Promise<void> {
    const inventory = await this.repository.cleanupInventory(userId);
    await Promise.all(inventory.objectKeys.map((key) => this.storage.delete(key)));
    await Promise.all([
      this.removeJobs(this.queues[0]!, inventory.exportQueueJobIds),
      this.removeJobs(this.queues[1]!, inventory.emailQueueJobIds),
      this.removeJobs(this.queues[2]!, inventory.securitiesQueueJobIds),
      this.clearRedis(userId, inventory.email),
    ]);
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.all(this.queues.map((queue) => queue.close()));
  }

  private async removeJobs(queue: Queue, ids: string[]): Promise<void> {
    for (const id of ids) {
      const job = await queue.getJob(id);
      if (job) await job.remove();
    }
  }

  private async clearRedis(userId: string, email: string): Promise<void> {
    const client = await this.redis.ready();
    const registry = `mymoneymap:user-sessions:${userId}`;
    const sessionIds = await client.sMembers(registry);
    const keys = sessionIds.map((id) => `mymoneymap:session:${id}`);
    keys.push(registry);
    keys.push(
      `mymoneymap:login-rate:${createHash('sha256')
        .update(`account:${email.trim().toLowerCase()}`)
        .digest('hex')}`,
    );
    await client.del(keys);
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
    maxRetriesPerRequest: null,
  };
}
