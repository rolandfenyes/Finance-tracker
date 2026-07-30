import './instrument';
import { ConfigService } from '@nestjs/config';
import { NestFactory } from '@nestjs/core';
import { Logger } from 'nestjs-pino';
import type { Server } from 'node:http';
import { AppModule } from './app.module';
import { configureApiApplication } from './bootstrap';
import { applyHttpServerTimeouts } from './platform/http/http-hardening';

async function main(): Promise<void> {
  const app = await NestFactory.create(AppModule, { bufferLogs: true });
  configureApiApplication(app);

  const config = app.get(ConfigService);
  const host = config.getOrThrow<string>('API_HOST');
  const port = config.getOrThrow<number>('API_PORT');
  await app.listen(port, host);
  applyHttpServerTimeouts(app.getHttpServer() as Server, config);

  app.get(Logger).log({ host, port }, 'MyMoneyMap API listening');
}

void main();
