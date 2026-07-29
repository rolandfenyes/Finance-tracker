import { Inject, Injectable, Logger, OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Pool } from 'pg';
import { createClient, RedisClientType } from 'redis';
import { DependencyName, DependencyProbe, DependencyProbeResult } from './dependency-probe';

@Injectable()
export class PostgresRedisProbeService implements DependencyProbe, OnApplicationShutdown {
  private readonly logger = new Logger(PostgresRedisProbeService.name);
  private readonly postgres: Pool;
  private readonly redis: RedisClientType;

  constructor(@Inject(ConfigService) config: ConfigService) {
    this.postgres = new Pool({
      connectionString: config.getOrThrow<string>('DATABASE_URL'),
      max: 1,
      connectionTimeoutMillis: 2_000,
      idleTimeoutMillis: 10_000,
    });
    this.redis = createClient({
      url: config.getOrThrow<string>('REDIS_URL'),
      socket: {
        connectTimeout: 2_000,
        reconnectStrategy: false,
      },
    });
    this.redis.on('error', (error: Error) => {
      this.logDependencyFailure('redis', error);
    });
  }

  async check(): Promise<DependencyProbeResult> {
    const [postgresql, redis] = await Promise.all([this.checkPostgresql(), this.checkRedis()]);

    return { postgresql, redis };
  }

  async onApplicationShutdown(): Promise<void> {
    await Promise.allSettled([
      this.postgres.end(),
      this.redis.isOpen ? this.redis.quit() : Promise.resolve(),
    ]);
  }

  private async checkPostgresql(): Promise<'up' | 'down'> {
    try {
      await this.postgres.query('SELECT 1');
      return 'up';
    } catch (error) {
      this.logDependencyFailure('postgresql', error);
      return 'down';
    }
  }

  private async checkRedis(): Promise<'up' | 'down'> {
    try {
      if (!this.redis.isOpen) {
        await this.redis.connect();
      }
      await this.redis.ping();
      return 'up';
    } catch (error) {
      this.logDependencyFailure('redis', error);
      return 'down';
    }
  }

  private logDependencyFailure(dependency: DependencyName, error: unknown): void {
    this.logger.warn({
      dependency,
      errorType: error instanceof Error ? error.constructor.name : 'UnknownError',
    });
  }
}
