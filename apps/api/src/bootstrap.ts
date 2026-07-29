import type { INestApplication } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { HttpAdapterHost } from '@nestjs/core';
import helmet from 'helmet';
import { Logger } from 'nestjs-pino';
import { ApiExceptionFilter } from './platform/http/api-exception.filter';
import { createGlobalValidationPipe } from './platform/http/validation';
import { installOpenApi } from './openapi/openapi';

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
  app.useLogger(app.get(Logger));
  app.setGlobalPrefix('api/v1');
  app.useGlobalPipes(createGlobalValidationPipe());
  app.useGlobalFilters(new ApiExceptionFilter(httpAdapterHost));
  app.enableShutdownHooks();

  if (options.installOpenApi ?? config.getOrThrow<boolean>('OPENAPI_ENABLED')) {
    installOpenApi(app);
  }
}
