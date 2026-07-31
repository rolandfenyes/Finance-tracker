import type { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, tap, throwError } from 'rxjs';
import { parseApiError } from './api-error';
import { IDEMPOTENCY_REQUEST, isDeclaredIdempotentRequest } from './idempotency';
import { API_OBSERVABILITY_SINK, API_ROUTE_TEMPLATE } from './observability';
import { SessionState } from '../session/session-state';

export const apiSessionInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isApiRequest(request.url)) return next(request);

  const session = inject(SessionState);
  const idempotency = request.context.get(IDEMPOTENCY_REQUEST);
  let apiRequest = request.clone({
    withCredentials: true,
    headers: request.headers.delete('Idempotency-Key'),
  });
  if (idempotency) {
    if (!isDeclaredIdempotentRequest(idempotency, request.method, request.url)) {
      return throwError(() => new Error('Idempotency declaration does not match this request'));
    }
    apiRequest = apiRequest.clone({ setHeaders: { 'Idempotency-Key': idempotency.key } });
  }

  return next(apiRequest).pipe(
    catchError((error: unknown) => {
      const parsed = parseApiError(error);
      if (parsed.status === 401) session.markAnonymous();
      return throwError(() => parsed);
    }),
  );
};

export const apiObservabilityInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isApiRequest(request.url)) return next(request);
  const sink = inject(API_OBSERVABILITY_SINK);
  const startedAt = performance.now();
  const routeTemplate = request.context.get(API_ROUTE_TEMPLATE);

  return next(request).pipe(
    tap({
      next: (event) => {
        if ('status' in event) {
          sink.record({
            durationMs: Math.max(0, performance.now() - startedAt),
            method: request.method,
            requestId: event.headers.get('x-request-id'),
            routeTemplate,
            status: event.status,
          });
        }
      },
      error: (error: unknown) => {
        const parsed = parseApiError(error);
        sink.record({
          durationMs: Math.max(0, performance.now() - startedAt),
          method: request.method,
          requestId: parsed.requestId,
          routeTemplate,
          status: parsed.status,
        });
      },
    }),
  );
};

function isApiRequest(url: string): boolean {
  return url === '/api' || url.startsWith('/api/');
}
