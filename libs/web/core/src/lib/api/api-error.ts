import { HttpErrorResponse } from '@angular/common/http';
import type { HttpHeaders } from '@angular/common/http';

export type ApiErrorKind =
  | 'authentication'
  | 'forbidden'
  | 'conflict'
  | 'validation'
  | 'rate-limit'
  | 'unavailable'
  | 'not-found'
  | 'unexpected';

export interface ApiFieldViolation {
  readonly code: string;
  readonly field: string;
}

export class ApiClientError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    readonly kind: ApiErrorKind,
    readonly requestId: string | null,
    readonly violations: readonly ApiFieldViolation[],
    readonly retryAfterSeconds: number | null,
  ) {
    super(code);
    this.name = 'ApiClientError';
  }
}

interface UnknownRecord {
  readonly [key: string]: unknown;
}

export function parseApiError(error: unknown): ApiClientError {
  if (error instanceof ApiClientError) return error;
  if (!(error instanceof HttpErrorResponse)) {
    return new ApiClientError(0, 'CLIENT_ERROR', 'unexpected', null, [], null);
  }

  const body = record(error.error);
  const apiError = record(body?.['error']);
  const code = stringValue(apiError?.['code']) ?? statusCode(error.status);
  const requestId = stringValue(apiError?.['requestId']) ?? error.headers.get('x-request-id');
  const rawViolations = Array.isArray(apiError?.['violations']) ? apiError['violations'] : [];
  const violations = rawViolations.flatMap((item): ApiFieldViolation[] => {
    const violation = record(item);
    const field = stringValue(violation?.['field']);
    const violationCode = stringValue(violation?.['code']);
    return field && violationCode ? [{ field, code: violationCode }] : [];
  });

  return new ApiClientError(
    error.status,
    code,
    kindFor(error.status, code),
    requestId,
    violations,
    retryAfter(error.headers),
  );
}

function record(value: unknown): UnknownRecord | null {
  return typeof value === 'object' && value !== null ? (value as UnknownRecord) : null;
}

function stringValue(value: unknown): string | null {
  return typeof value === 'string' && value.length > 0 ? value : null;
}

function statusCode(status: number): string {
  return status === 0 ? 'NETWORK_UNAVAILABLE' : `HTTP_${status}`;
}

function kindFor(status: number, code: string): ApiErrorKind {
  if (status === 401) return 'authentication';
  if (status === 403) return 'forbidden';
  if (status === 404) return 'not-found';
  if (status === 409) return 'conflict';
  if (status === 422 || code === 'VALIDATION_FAILED') return 'validation';
  if (status === 429) return 'rate-limit';
  if (status === 0 || status === 502 || status === 503 || status === 504) return 'unavailable';
  return 'unexpected';
}

function retryAfter(headers: HttpHeaders): number | null {
  const value = headers.get('retry-after');
  if (!value || !/^\d+$/.test(value)) return null;
  const seconds = Number(value);
  return Number.isSafeInteger(seconds) ? seconds : null;
}
