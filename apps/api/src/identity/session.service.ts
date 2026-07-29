import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import type { AuthenticatedPrincipal } from './identity.types';
import { RedisSecurityService } from './redis-security.service';

@Injectable()
export class SessionService {
  constructor(
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(RedisSecurityService) private readonly redis: RedisSecurityService,
  ) {}

  async establish(
    request: Request,
    principal: AuthenticatedPrincipal,
    remember: boolean,
  ): Promise<void> {
    await regenerate(request);
    const now = new Date();
    const absoluteSeconds = remember
      ? this.config.getOrThrow<number>('REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS')
      : this.config.getOrThrow<number>('SESSION_ABSOLUTE_TTL_SECONDS');
    request.session.principal = principal;
    request.session.authenticatedAt = now.toISOString();
    request.session.absoluteExpiresAt = new Date(
      now.getTime() + absoluteSeconds * 1000,
    ).toISOString();
    request.session.cookie.maxAge = remember ? absoluteSeconds * 1000 : undefined;
    await save(request);
    const client = await this.redis.ready();
    const registry = `mymoneymap:user-sessions:${principal.userId}`;
    await client.sAdd(registry, request.sessionID);
    await client.expire(registry, this.config.getOrThrow('REMEMBER_SESSION_ABSOLUTE_TTL_SECONDS'));
  }

  async revoke(request: Request): Promise<void> {
    await destroy(request);
  }

  async revokeAllForUser(userId: string): Promise<void> {
    const client = await this.redis.ready();
    const registry = `mymoneymap:user-sessions:${userId}`;
    const ids = await client.sMembers(registry);
    if (ids.length > 0) {
      await client.del(ids.map((id) => `mymoneymap:session:${id}`));
    }
    await client.del(registry);
  }
}

function regenerate(request: Request): Promise<void> {
  return new Promise((resolve, reject) =>
    request.session.regenerate((error) => (error ? reject(asError(error)) : resolve())),
  );
}

function save(request: Request): Promise<void> {
  return new Promise((resolve, reject) =>
    request.session.save((error) => (error ? reject(asError(error)) : resolve())),
  );
}

function destroy(request: Request): Promise<void> {
  return new Promise((resolve, reject) =>
    request.session.destroy((error) => (error ? reject(asError(error)) : resolve())),
  );
}

function asError(value: unknown): Error {
  return value instanceof Error ? value : new Error('Session operation failed');
}
