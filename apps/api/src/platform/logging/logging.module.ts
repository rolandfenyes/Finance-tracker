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
              'req.body.email',
              'req.body.recipientEmail',
              'req.body.fullName',
              'req.body.note',
              'req.body.message',
              'req.body.templateData',
              'req.query',
            ],
            censor: '[REDACTED]',
          },
          serializers: {
            req(request: IncomingMessage & { id?: string }): { id?: string; method?: string } {
              return {
                id: request.id,
                method: request.method,
              };
            },
            res(response: ServerResponse): { statusCode: number } {
              return { statusCode: response.statusCode };
            },
            err(error: Error): { type: string } {
              return { type: error.constructor.name };
            },
          },
        },
      }),
    }),
  ],
  exports: [LoggerModule],
})
export class PlatformLoggingModule {}
