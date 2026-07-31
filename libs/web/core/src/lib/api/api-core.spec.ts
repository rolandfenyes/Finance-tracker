import {
  HttpClient,
  HttpContext,
  HttpErrorResponse,
  HttpHeaders,
  provideHttpClient,
  withInterceptors,
} from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import {
  ApiConfiguration,
  provideApiConfiguration,
} from '@mymoneymap/generated-api-client/api-configuration';
import type { CurrentUserResponseDto } from '@mymoneymap/generated-api-client/models/current-user-response-dto';
import { firstValueFrom } from 'rxjs';
import { apiObservabilityInterceptor, apiSessionInterceptor } from './api.interceptors';
import { ApiClientError, parseApiError } from './api-error';
import { IDEMPOTENT_OPERATIONS, idempotencyContext } from './idempotency';
import { API_OBSERVABILITY_SINK, API_ROUTE_TEMPLATE, type ApiObservation } from './observability';
import { SessionState } from '../session/session-state';

describe('API core HTTP boundary', () => {
  let http: HttpClient;
  let controller: HttpTestingController;
  let observations: ApiObservation[];

  beforeEach(() => {
    observations = [];
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([apiSessionInterceptor, apiObservabilityInterceptor])),
        provideHttpClientTesting(),
        {
          provide: API_OBSERVABILITY_SINK,
          useValue: {
            record: (value: ApiObservation): void => {
              observations.push(value);
            },
          },
        },
      ],
    });
    http = TestBed.inject(HttpClient);
    controller = TestBed.inject(HttpTestingController);
  });

  afterEach(() => controller.verify());

  it('adds credentials only to same-origin /api requests and stores no authentication data', async () => {
    const apiPromise = firstValueFrom(http.get('/api/v1/users/me'));
    const api = controller.expectOne('/api/v1/users/me');
    expect(api.request.withCredentials).toBe(true);
    api.flush({ ok: true });
    await apiPromise;

    const assetPromise = firstValueFrom(http.get('/assets/synthetic.svg'));
    const asset = controller.expectOne('/assets/synthetic.svg');
    expect(asset.request.withCredentials).toBe(false);
    asset.flush('ok');
    await assetPromise;

    expect(Object.keys(localStorage)).toEqual([]);
    expect(Object.keys(sessionStorage)).toEqual([]);
  });

  it('attaches caller-provided idempotency only through a declared operation context', async () => {
    const withoutPromise = firstValueFrom(
      http.post('/api/v1/privacy/exports', {}, { headers: { 'Idempotency-Key': 'bypass' } }),
    );
    const without = controller.expectOne('/api/v1/privacy/exports');
    expect(without.request.headers.has('Idempotency-Key')).toBe(false);
    without.flush({ id: 'synthetic' });
    await withoutPromise;

    const context = idempotencyContext(IDEMPOTENT_OPERATIONS.privacyExport, 'stable-synthetic-key');
    const withPromise = firstValueFrom(http.post('/api/v1/privacy/exports', {}, { context }));
    const withKey = controller.expectOne('/api/v1/privacy/exports');
    expect(withKey.request.headers.get('Idempotency-Key')).toBe('stable-synthetic-key');
    withKey.flush({ id: 'synthetic' });
    await withPromise;

    const mismatched = idempotencyContext(
      IDEMPOTENT_OPERATIONS.goalContribution,
      'wrong-operation',
    );
    await expect(
      firstValueFrom(http.post('/api/v1/privacy/exports', {}, { context: mismatched })),
    ).rejects.toThrow('does not match');
  });

  it('maps violations, request ID, conflicts, throttling, and unavailable states safely', () => {
    const parsed = parseApiError(
      new HttpErrorResponse({
        status: 422,
        headers: new HttpHeaders({ 'retry-after': '17' }),
        error: {
          error: {
            code: 'UNPROCESSABLE_ENTITY',
            message: 'server copy is not rendered',
            requestId: 'synthetic-request-id',
            violations: [
              { field: 'amount', code: 'isDecimal', message: 'sensitive detail omitted' },
            ],
          },
        },
      }),
    );
    expect(parsed).toMatchObject({
      kind: 'validation',
      requestId: 'synthetic-request-id',
      retryAfterSeconds: 17,
      violations: [{ field: 'amount', code: 'isDecimal' }],
    });
    expect(JSON.stringify(parsed)).not.toContain('sensitive detail');
    expect(parseApiError(new HttpErrorResponse({ status: 409 })).kind).toBe('conflict');
    expect(parseApiError(new HttpErrorResponse({ status: 401 })).kind).toBe('authentication');
    expect(parseApiError(new HttpErrorResponse({ status: 403 })).kind).toBe('forbidden');
    expect(parseApiError(new HttpErrorResponse({ status: 429 })).kind).toBe('rate-limit');
    expect(parseApiError(new HttpErrorResponse({ status: 503 })).kind).toBe('unavailable');
  });

  it('clears session on expiry and emits only the approved observability fields', async () => {
    const state = TestBed.inject(SessionState);
    state.markAuthenticated(syntheticUser());
    const context = new HttpContext().set(API_ROUTE_TEMPLATE, '/api/v1/users/me');
    const promise = firstValueFrom(http.get('/api/v1/users/me?private=value', { context }));
    const request = controller.expectOne('/api/v1/users/me?private=value');
    request.flush(
      { error: { code: 'UNAUTHORIZED', message: 'private', requestId: 'request-401' } },
      { status: 401, statusText: 'Unauthorized' },
    );
    await expect(promise).rejects.toBeInstanceOf(ApiClientError);
    expect(state.status()).toBe('anonymous');
    expect(state.currentUser()).toBeNull();
    expect(observations).toHaveLength(1);
    expect(observations[0]).toMatchObject({
      method: 'GET',
      requestId: 'request-401',
      routeTemplate: '/api/v1/users/me',
      status: 401,
    });
    expect(JSON.stringify(observations)).not.toContain('private=value');
    expect(JSON.stringify(observations)).not.toContain('private');
  });
});

describe('API configuration', () => {
  it('keeps the generated client on the same-origin root URL', () => {
    TestBed.configureTestingModule({ providers: [provideApiConfiguration('')] });
    expect(TestBed.inject(ApiConfiguration).rootUrl).toBe('');
  });
});

function syntheticUser(): CurrentUserResponseDto {
  return {
    id: '00000000-0000-4000-8000-000000000001',
    email: 'synthetic@example.test',
    fullName: 'Synthetic User',
    dateOfBirth: '1990-01-01',
    desiredLanguage: 'en' as const,
    emailVerified: true,
    role: 'free' as const,
    theme: 'verdant-horizon' as const,
    entitlements: {
      administration: false,
      cashFlowRuleEditing: false,
      personalFinanceAccess: true,
      resources: {
        activeGoals: { allowed: true, limit: 2 },
        activeLoans: { allowed: true, limit: 2 },
        activeScheduledItems: { allowed: true, limit: 2 },
        categories: { allowed: true, limit: 10 },
        currencies: { allowed: true, limit: 1 },
      },
    },
  };
}
