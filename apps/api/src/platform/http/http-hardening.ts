import type { INestApplication } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHmac, randomUUID } from 'node:crypto';
import type { NextFunction, Request, Response } from 'express';
import { json, urlencoded } from 'express';
import helmet from 'helmet';
import { RedisSecurityService } from '../../identity/redis-security.service';
import { OperationsMetricsService } from '../operations/operations-metrics.service';

export function installHttpHardening(app: INestApplication): void {
  const config = app.get(ConfigService);
  const redis = app.get(RedisSecurityService);
  const metrics = app.get(OperationsMetricsService);
  const bodyLimit = config.getOrThrow<number>('HTTP_JSON_BODY_LIMIT_BYTES');

  app.use(json({ limit: bodyLimit, strict: true }));
  app.use(urlencoded({ extended: false, limit: bodyLimit, parameterLimit: 100 }));
  app.use(
    helmet({
      contentSecurityPolicy: {
        directives: {
          defaultSrc: ["'none'"],
          baseUri: ["'none'"],
          formAction: ["'none'"],
          frameAncestors: ["'none'"],
        },
      },
      crossOriginResourcePolicy: { policy: 'same-site' },
      frameguard: { action: 'deny' },
      referrerPolicy: { policy: 'no-referrer' },
      strictTransportSecurity:
        config.getOrThrow<string>('NODE_ENV') === 'production'
          ? { maxAge: 31_536_000, includeSubDomains: true, preload: false }
          : false,
    }),
  );
  app.use((_request: Request, response: Response, next: NextFunction) => {
    response.setHeader(
      'permissions-policy',
      'camera=(), geolocation=(), microphone=(), payment=(), usb=()',
    );
    next();
  });
  app.use(async (request: Request, response: Response, next: NextFunction) => {
    if (!request.path.startsWith('/api/v1') || request.path.startsWith('/api/v1/health/')) {
      next();
      return;
    }
    try {
      const scope = request.path.startsWith('/api/v1/admin') ? 'admin' : 'api';
      const maximum = config.getOrThrow<number>(
        scope === 'admin' ? 'HTTP_ADMIN_RATE_LIMIT_MAX_REQUESTS' : 'HTTP_RATE_LIMIT_MAX_REQUESTS',
      );
      const windowSeconds = config.getOrThrow<number>('HTTP_RATE_LIMIT_WINDOW_SECONDS');
      const client = await redis.ready();
      const result = await consumeFixedWindowRateLimit(client, {
        scope,
        subject: request.ip || request.socket.remoteAddress || 'unknown',
        secret: config.getOrThrow<string>('SESSION_SECRET'),
        maximum,
        windowSeconds,
        nowMs: Date.now(),
      });
      response.setHeader('ratelimit-limit', maximum);
      response.setHeader('ratelimit-remaining', result.remaining);
      if (result.rejected) {
        metrics.observeRateLimit(scope);
        response.setHeader('retry-after', windowSeconds);
        const requestId = requestIdFor(request, response);
        response.status(429).json({
          error: {
            code: 'TOO_MANY_REQUESTS',
            message: 'Too many requests',
            requestId,
          },
        });
        return;
      }
      next();
    } catch (error) {
      next(error);
    }
  });
  app.use((request: Request, response: Response, next: NextFunction) => {
    const started = process.hrtime.bigint();
    response.once('finish', () => {
      const duration = Number(process.hrtime.bigint() - started) / 1_000_000_000;
      metrics.observeRequest(request.method, boundedRoute(request), response.statusCode, duration);
    });
    next();
  });
  app.use((error: unknown, request: Request, response: Response, next: NextFunction): void => {
    if (isPayloadTooLarge(error)) {
      const requestId = requestIdFor(request, response);
      response.status(413).json({
        error: {
          code: 'PAYLOAD_TOO_LARGE',
          message: 'Request payload exceeds the configured limit',
          requestId,
        },
      });
      return;
    }
    next(error);
  });
}

export async function consumeFixedWindowRateLimit(
  redis: {
    incr(key: string): Promise<number>;
    expire(key: string, seconds: number): Promise<unknown>;
  },
  input: {
    scope: 'admin' | 'api';
    subject: string;
    secret: string;
    maximum: number;
    windowSeconds: number;
    nowMs: number;
  },
): Promise<{ remaining: number; rejected: boolean }> {
  const subjectHash = createHmac('sha256', input.secret).update(input.subject).digest('hex');
  const bucket = Math.floor(input.nowMs / (input.windowSeconds * 1_000));
  const key = `mymoneymap:http-rate:${input.scope}:${subjectHash}:${bucket}`;
  const count = await redis.incr(key);
  if (count === 1) await redis.expire(key, input.windowSeconds + 1);
  return {
    remaining: Math.max(0, input.maximum - count),
    rejected: count > input.maximum,
  };
}

export function applyHttpServerTimeouts(
  server: {
    requestTimeout: number;
    headersTimeout: number;
    keepAliveTimeout: number;
  },
  config: ConfigService,
): void {
  server.requestTimeout = config.getOrThrow<number>('HTTP_REQUEST_TIMEOUT_MS');
  server.headersTimeout = config.getOrThrow<number>('HTTP_HEADERS_TIMEOUT_MS');
  server.keepAliveTimeout = config.getOrThrow<number>('HTTP_KEEP_ALIVE_TIMEOUT_MS');
}

function requestIdFor(request: Request, response: Response): string {
  const requestId = typeof request.id === 'string' ? request.id : randomUUID();
  response.setHeader('x-request-id', requestId);
  return requestId;
}

function boundedRoute(request: Request): string {
  const route = request.route as { path?: unknown } | undefined;
  return typeof route?.path === 'string'
    ? `${request.baseUrl}${route.path}` || '/'
    : request.path.startsWith('/api/v1')
      ? '/api/v1/_unmatched'
      : '/_non_api';
}

function isPayloadTooLarge(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    (error as { status?: unknown }).status === 413
  );
}
