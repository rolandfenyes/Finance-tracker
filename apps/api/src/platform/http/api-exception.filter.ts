import { ArgumentsHost, Catch, ExceptionFilter, HttpException, HttpStatus } from '@nestjs/common';
import { HttpAdapterHost } from '@nestjs/core';
import type { Request } from 'express';
import { randomUUID } from 'node:crypto';

interface ExceptionBody {
  code?: unknown;
  message?: unknown;
  violations?: unknown;
}

const statusCodeNames: Partial<Record<number, string>> = {
  [HttpStatus.BAD_REQUEST]: 'BAD_REQUEST',
  [HttpStatus.UNAUTHORIZED]: 'UNAUTHORIZED',
  [HttpStatus.FORBIDDEN]: 'FORBIDDEN',
  [HttpStatus.NOT_FOUND]: 'NOT_FOUND',
  [HttpStatus.CONFLICT]: 'CONFLICT',
  [HttpStatus.UNPROCESSABLE_ENTITY]: 'UNPROCESSABLE_ENTITY',
  [HttpStatus.TOO_MANY_REQUESTS]: 'TOO_MANY_REQUESTS',
  [HttpStatus.SERVICE_UNAVAILABLE]: 'SERVICE_UNAVAILABLE',
};

function safeExceptionBody(exception: HttpException): ExceptionBody {
  const response = exception.getResponse();
  return typeof response === 'object' && response !== null ? response : {};
}

@Catch()
export class ApiExceptionFilter implements ExceptionFilter {
  constructor(private readonly adapterHost: HttpAdapterHost) {}

  catch(exception: unknown, host: ArgumentsHost): void {
    const http = host.switchToHttp();
    const request = http.getRequest<Request>();
    const response = http.getResponse<unknown>();
    const requestId = typeof request.id === 'string' ? request.id : randomUUID();
    const isHttpException = exception instanceof HttpException;
    const status: number = isHttpException
      ? exception.getStatus()
      : HttpStatus.INTERNAL_SERVER_ERROR;
    const exceptionBody = isHttpException ? safeExceptionBody(exception) : {};
    const code =
      typeof exceptionBody.code === 'string'
        ? exceptionBody.code
        : (statusCodeNames[status] ?? 'INTERNAL_SERVER_ERROR');
    const message =
      status >= 500
        ? 'An unexpected error occurred'
        : typeof exceptionBody.message === 'string'
          ? exceptionBody.message
          : isHttpException
            ? exception.message
            : 'An unexpected error occurred';

    if (status >= 500) {
      request.log?.error(
        {
          code,
          requestId,
          exceptionType: exception instanceof Error ? exception.constructor.name : 'UnknownError',
        },
        'Request failed',
      );
    }

    this.adapterHost.httpAdapter.reply(
      response,
      {
        error: {
          code,
          message,
          requestId,
          ...(Array.isArray(exceptionBody.violations)
            ? { violations: exceptionBody.violations }
            : {}),
        },
      },
      status,
    );
  }
}
