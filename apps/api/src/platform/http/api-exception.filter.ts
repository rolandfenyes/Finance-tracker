import { ArgumentsHost, Catch, ExceptionFilter, HttpException, HttpStatus } from '@nestjs/common';
import { HttpAdapterHost } from '@nestjs/core';
import type { Request } from 'express';
import { randomUUID } from 'node:crypto';
import { isApiErrorCode, type ApiErrorCode } from './api-error-code';
import { ApplicationError } from './application-error';
import type { ApiViolation } from './validation';

interface ExceptionBody {
  code?: unknown;
  message?: unknown;
  violations?: unknown;
}

const statusCodeNames: Partial<Record<number, ApiErrorCode>> = {
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

function safeViolations(value: unknown): ApiViolation[] | undefined {
  if (!Array.isArray(value)) {
    return undefined;
  }
  const violations = value.filter(
    (violation): violation is ApiViolation =>
      typeof violation === 'object' &&
      violation !== null &&
      typeof (violation as Partial<ApiViolation>).field === 'string' &&
      typeof (violation as Partial<ApiViolation>).code === 'string' &&
      typeof (violation as Partial<ApiViolation>).message === 'string',
  );
  return violations.length === value.length ? violations : undefined;
}

@Catch()
export class ApiExceptionFilter implements ExceptionFilter {
  constructor(private readonly adapterHost: HttpAdapterHost) {}

  catch(exception: unknown, host: ArgumentsHost): void {
    const http = host.switchToHttp();
    const request = http.getRequest<Request>();
    const response = http.getResponse<unknown>();
    const requestId = typeof request.id === 'string' ? request.id : randomUUID();
    const isApplicationError = exception instanceof ApplicationError;
    const isHttpException = exception instanceof HttpException;
    const status: number = isApplicationError
      ? exception.status
      : isHttpException
        ? exception.getStatus()
        : HttpStatus.INTERNAL_SERVER_ERROR;
    const exceptionBody = isHttpException ? safeExceptionBody(exception) : {};
    const code = isApplicationError
      ? exception.code
      : isApiErrorCode(exceptionBody.code)
        ? exceptionBody.code
        : (statusCodeNames[status] ?? 'INTERNAL_SERVER_ERROR');
    const message =
      status >= 500
        ? 'An unexpected error occurred'
        : isApplicationError
          ? exception.message
          : typeof exceptionBody.message === 'string'
            ? exceptionBody.message
            : isHttpException
              ? exception.message
              : 'An unexpected error occurred';
    const violations = isApplicationError
      ? exception.violations
      : safeViolations(exceptionBody.violations);

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
          ...(violations ? { violations } : {}),
        },
      },
      status,
    );
  }
}
