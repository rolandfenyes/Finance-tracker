import type { INestApplication } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { HttpAdapterHost } from '@nestjs/core';
import { RedisStore } from 'connect-redis';
import type { NextFunction, Request, Response } from 'express';
import helmet from 'helmet';
import { Logger } from 'nestjs-pino';
import { ApiExceptionFilter } from './platform/http/api-exception.filter';
import { createGlobalValidationPipe } from './platform/http/validation';
import { installOpenApi } from './openapi/openapi';
import session from 'express-session';
import { RedisSecurityService } from './identity/redis-security.service';

export interface BootstrapOptions {
  installOpenApi?: boolean;
}

export function configureApiApplication(
  app: INestApplication,
  options: BootstrapOptions = {},
): void {
  const config = app.get(ConfigService);
  const httpAdapterHost = app.get(HttpAdapterHost);
  const express = app.getHttpAdapter().getInstance() as {
    set(name: string, value: boolean | number): void;
  };

  express.set('trust proxy', config.getOrThrow('TRUST_PROXY'));
  app.use(helmet());
  const redis = app.get(RedisSecurityService);
  app.use((request: Request, _response: Response, next: NextFunction) => {
    if (
      !request.path.startsWith('/api/v1/auth') &&
      !request.path.startsWith('/api/v1/users/me') &&
      !request.path.startsWith('/api/v1/journal') &&
      !request.path.startsWith('/api/v1/privacy')
    ) {
      next();
      return;
    }
    void redis.ready().then(() => next(), next);
  });
  app.use(
    session({
      name: config.getOrThrow('SESSION_COOKIE_NAME'),
      secret: config.getOrThrow('SESSION_SECRET'),
      store: new RedisStore({
        client: redis.rawClient,
        prefix: 'mymoneymap:session:',
        ttl: (data): number => {
          const idle = config.getOrThrow<number>('SESSION_IDLE_TTL_SECONDS');
          if (!data.absoluteExpiresAt) return idle;
          const remaining = Math.ceil((Date.parse(data.absoluteExpiresAt) - Date.now()) / 1000);
          return Math.max(1, Math.min(idle, remaining));
        },
      }),
      resave: false,
      saveUninitialized: false,
      rolling: true,
      cookie: {
        httpOnly: true,
        secure: config.getOrThrow('NODE_ENV') === 'production',
        sameSite: 'lax',
        path: '/',
        maxAge: config.getOrThrow<number>('SESSION_IDLE_TTL_SECONDS') * 1000,
      },
    }),
  );
  app.useLogger(app.get(Logger));
  app.setGlobalPrefix('api/v1');
  app.useGlobalPipes(createGlobalValidationPipe());
  app.useGlobalFilters(new ApiExceptionFilter(httpAdapterHost));
  app.enableShutdownHooks();

  if (options.installOpenApi ?? config.getOrThrow<boolean>('OPENAPI_ENABLED')) {
    installOpenApi(app);
  }
}
