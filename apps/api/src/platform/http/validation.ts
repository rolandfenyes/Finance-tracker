import { BadRequestException, ValidationPipe } from '@nestjs/common';
import type { ValidationError } from '@nestjs/common';

export interface ApiViolation {
  field: string;
  code: string;
  message: string;
}

function flattenValidationErrors(errors: ValidationError[], parentPath = ''): ApiViolation[] {
  return errors
    .flatMap((error) => {
      const field = parentPath ? `${parentPath}.${error.property}` : error.property;
      const ownViolations = Object.entries(error.constraints ?? {}).map(([code, message]) => ({
        field,
        code,
        message,
      }));

      return [...ownViolations, ...flattenValidationErrors(error.children ?? [], field)];
    })
    .sort((left, right) =>
      `${left.field}:${left.code}`.localeCompare(`${right.field}:${right.code}`),
    );
}

export function createGlobalValidationPipe(): ValidationPipe {
  return new ValidationPipe({
    transform: true,
    whitelist: true,
    forbidNonWhitelisted: true,
    forbidUnknownValues: true,
    stopAtFirstError: false,
    exceptionFactory: (errors) =>
      new BadRequestException({
        code: 'VALIDATION_FAILED',
        message: 'Request validation failed',
        violations: flattenValidationErrors(errors),
      }),
  });
}
