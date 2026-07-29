import { Inject, Injectable, OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createClient, type RedisClientType } from 'redis';

@Injectable()
export class RedisSecurityService implements OnApplicationShutdown {
  private readonly client: RedisClientType;
  private connecting?: Promise<void>;

  constructor(@Inject(ConfigService) config: ConfigService) {
    this.client = createClient({ url: config.getOrThrow<string>('REDIS_URL') });
    this.client.on('error', () => {
      // Command failures propagate to callers; credentials and payloads are never logged here.
    });
  }

  get rawClient(): RedisClientType {
    return this.client;
  }

  async ready(): Promise<RedisClientType> {
    if (!this.client.isOpen) {
      this.connecting ??= this.client.connect().then(() => undefined);
      try {
        await this.connecting;
      } finally {
        this.connecting = undefined;
      }
    }
    return this.client;
  }

  async onApplicationShutdown(): Promise<void> {
    if (this.client.isOpen) await this.client.quit();
  }
}
