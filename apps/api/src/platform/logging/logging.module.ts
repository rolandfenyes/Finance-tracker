import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { randomUUID } from 'node:crypto';
import { LoggerModule } from 'nestjs-pino';
import type { Params } from 'nestjs-pino';
import type { IncomingMessage, ServerResponse } from 'node:http';

@Module({
  imports: [
    LoggerModule.forRootAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (config: ConfigService): Params => ({
        pinoHttp: {
          level: config.getOrThrow<string>('LOG_LEVEL'),
          genReqId: (_request: IncomingMessage, response: ServerResponse): string => {
            const requestId = randomUUID();
            response.setHeader('x-request-id', requestId);
            return requestId;
          },
          redact: {
            paths: [
              'req.headers.authorization',
              'req.headers.cookie',
              'res.headers.set-cookie',
              'req.body.password',
              'req.body.passwordConfirmation',
              'req.body.currentPassword',
              'req.body.token',
              'req.body.secret',
              'req.body.apiKey',
              'req.body.credential',
            ],
            censor: '[REDACTED]',
          },
        },
      }),
    }),
  ],
  exports: [LoggerModule],
})
export class PlatformLoggingModule {}
