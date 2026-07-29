import type { HttpStatus } from '@nestjs/common';
import type { ApiErrorCode } from './api-error-code';
import type { ApiViolation } from './validation';

export class ApplicationError extends Error {
  constructor(
    readonly status: HttpStatus,
    readonly code: ApiErrorCode,
    message: string,
    readonly violations?: ApiViolation[],
  ) {
    super(message);
    this.name = 'ApplicationError';
  }
}
