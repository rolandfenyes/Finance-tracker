import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';
import { DEPENDENCY_PROBE } from '../src/platform/health/dependency-probe';
import type { DependencyProbeResult } from '../src/platform/health/dependency-probe';

describe('platform HTTP contract', () => {
  let app: INestApplication;
  let dependencyStatus: DependencyProbeResult;
  const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  beforeAll(async () => {
    dependencyStatus = { postgresql: 'up', redis: 'up' };
    const module = await Test.createTestingModule({ imports: [AppModule] })
      .overrideProvider(DEPENDENCY_PROBE)
      .useValue({
        check: (): Promise<DependencyProbeResult> => Promise.resolve(dependencyStatus),
      })
      .compile();

    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app);
    await app.init();
  });

  afterAll(async () => {
    await app.close();
  });

  it('serves liveness with a generated request ID and security headers', async () => {
    const response = await request(app.getHttpServer()).get('/api/v1/health/live').expect(200);

    expect(response.body).toEqual({ status: 'ok' });
    expect(response.headers['x-request-id']).toMatch(uuidPattern);
    expect(response.headers['x-content-type-options']).toBe('nosniff');
    expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
  });

  it('serves readiness only when both required dependencies are available', async () => {
    await request(app.getHttpServer())
      .get('/api/v1/health/ready')
      .expect(200)
      .expect({
        status: 'ready',
        dependencies: { postgresql: 'up', redis: 'up' },
      });

    dependencyStatus = { postgresql: 'down', redis: 'up' };
    const response = await request(app.getHttpServer()).get('/api/v1/health/ready').expect(503);

    expect(response.body).toEqual({
      error: {
        code: 'SERVICE_NOT_READY',
        message: 'An unexpected error occurred',
        requestId: response.headers['x-request-id'],
      },
    });
  });

  it('maps unknown routes to the stable safe error contract', async () => {
    const response = await request(app.getHttpServer()).get('/api/v1/finance').expect(404);

    expect(response.body).toEqual({
      error: {
        code: 'NOT_FOUND',
        message: 'Cannot GET /api/v1/finance',
        requestId: response.headers['x-request-id'],
      },
    });
  });

  it('publishes only the health API operations in OpenAPI', async () => {
    const response = await request(app.getHttpServer()).get('/api/docs/openapi.json').expect(200);

    expect(Object.keys(response.body.paths).sort()).toEqual([
      '/api/v1/health/live',
      '/api/v1/health/ready',
    ]);
  });
});
