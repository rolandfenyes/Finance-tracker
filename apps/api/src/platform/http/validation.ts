import { BadRequestException, ValidationPipe } from '@nestjs/common';
import type { ValidationError } from '@nestjs/common';

export interface ApiViolation {
  field: string;
  message: string;
}

function flattenValidationErrors(errors: ValidationError[], parentPath = ''): ApiViolation[] {
  return errors.flatMap((error) => {
    const field = parentPath ? `${parentPath}.${error.property}` : error.property;
    const ownViolations = Object.values(error.constraints ?? {}).map((message) => ({
      field,
      message,
    }));

    return [...ownViolations, ...flattenValidationErrors(error.children ?? [], field)];
  });
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
