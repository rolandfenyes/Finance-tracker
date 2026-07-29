import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash } from 'node:crypto';
import { RedisSecurityService } from './redis-security.service';

@Injectable()
export class LoginRateLimiter {
  private readonly windowSeconds: number;
  private readonly maxAttempts: number;
  private readonly ipMaxAttempts: number;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(RedisSecurityService) private readonly redis: RedisSecurityService,
  ) {
    this.windowSeconds = config.getOrThrow('LOGIN_RATE_LIMIT_WINDOW_SECONDS');
    this.maxAttempts = config.getOrThrow('LOGIN_RATE_LIMIT_MAX_ATTEMPTS');
    this.ipMaxAttempts = config.getOrThrow('LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS');
  }

  async consume(email: string, ip: string): Promise<boolean> {
    const client = await this.redis.ready();
    const limits = [
      { hash: this.hash(`account:${email}`), maximum: this.maxAttempts },
      { hash: this.hash(`ip:${ip}`), maximum: this.ipMaxAttempts },
    ];
    for (const { hash, maximum } of limits) {
      const key = `mymoneymap:login-rate:${hash}`;
      const count = await client.incr(key);
      if (count === 1) await client.expire(key, this.windowSeconds);
      if (count > maximum) return false;
    }
    return true;
  }

  async clear(email: string): Promise<void> {
    const client = await this.redis.ready();
    await client.del(`mymoneymap:login-rate:${this.hash(`account:${email}`)}`);
  }

  private hash(value: string): string {
    return createHash('sha256').update(value).digest('hex');
  }
}
