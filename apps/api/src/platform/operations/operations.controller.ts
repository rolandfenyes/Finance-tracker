import { Controller, Get, Header, Inject, Res, UseGuards } from '@nestjs/common';
import {
  ApiCookieAuth,
  ApiExcludeEndpoint,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Response } from 'express';
import { AdminGuard } from '../../administration/admin.guard';
import { AuthenticationGuard, VerifiedEmailGuard } from '../../identity/authentication.guard';
import { OperationsMetricsGuard } from './operations-metrics.guard';
import { OperationsMetricsService } from './operations-metrics.service';
import { OperationsService } from './operations.service';
import type { QueueOperationsSnapshot } from './operations.service';

@ApiTags('Administration')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, AdminGuard)
@Controller('admin/operations')
export class AdminOperationsController {
  constructor(@Inject(OperationsService) private readonly operations: OperationsService) {}

  @Get('queues')
  @ApiOperation({
    summary: 'Read PII-safe BullMQ backlog, failure, and provider-circuit diagnostics',
  })
  @ApiOkResponse({
    schema: {
      type: 'object',
      required: ['generatedAt', 'queues', 'providerCircuits'],
      properties: {
        generatedAt: { type: 'string', format: 'date-time', example: '2026-07-30T12:00:00.000Z' },
        queues: {
          type: 'array',
          items: {
            type: 'object',
            required: ['queue', 'counts', 'oldestPendingSeconds', 'alertCodes'],
            properties: {
              queue: { type: 'string', example: 'mymoneymap-email-delivery' },
              counts: {
                type: 'object',
                additionalProperties: { type: 'integer', minimum: 0 },
                example: { wait: 0, active: 1, completed: 50, failed: 0 },
              },
              oldestPendingSeconds: { type: 'integer', minimum: 0, example: 0 },
              alertCodes: {
                type: 'array',
                items: {
                  type: 'string',
                  enum: [
                    'PERMANENT_FAILURE_PRESENT',
                    'QUEUE_BACKLOG_HIGH',
                    'OLDEST_PENDING_OVER_300_SECONDS',
                  ],
                },
                example: [],
              },
            },
          },
        },
        providerCircuits: {
          type: 'object',
          required: ['frankfurter', 'finnhub'],
          properties: {
            frankfurter: { type: 'string', enum: ['open', 'closed'], example: 'closed' },
            finnhub: { type: 'string', enum: ['open', 'closed'], example: 'closed' },
          },
        },
      },
    },
  })
  queues(): Promise<{
    generatedAt: string;
    queues: QueueOperationsSnapshot[];
    providerCircuits: { frankfurter: 'open' | 'closed'; finnhub: 'open' | 'closed' };
  }> {
    return this.operations.queueSnapshot();
  }
}

@Controller('internal')
export class InternalMetricsController {
  constructor(
    @Inject(OperationsService) private readonly operations: OperationsService,
    @Inject(OperationsMetricsService) private readonly metrics: OperationsMetricsService,
  ) {}

  @Get('metrics')
  @UseGuards(OperationsMetricsGuard)
  @ApiExcludeEndpoint()
  @Header('cache-control', 'no-store')
  async scrape(@Res() response: Response): Promise<void> {
    await this.operations.queueSnapshot();
    response.type(this.metrics.contentType()).send(await this.metrics.metrics());
  }
}
