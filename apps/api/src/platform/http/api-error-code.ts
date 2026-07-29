export const apiErrorCodes = [
  'VALIDATION_FAILED',
  'BAD_REQUEST',
  'UNAUTHORIZED',
  'FORBIDDEN',
  'NOT_FOUND',
  'CONFLICT',
  'UNPROCESSABLE_ENTITY',
  'TOO_MANY_REQUESTS',
  'IDEMPOTENCY_CONFLICT',
  'IDEMPOTENCY_IN_PROGRESS',
  'SERVICE_NOT_READY',
  'SERVICE_UNAVAILABLE',
  'INTERNAL_SERVER_ERROR',
] as const;

export type ApiErrorCode = (typeof apiErrorCodes)[number];

export function isApiErrorCode(value: unknown): value is ApiErrorCode {
  return typeof value === 'string' && apiErrorCodes.some((code) => code === value);
}
