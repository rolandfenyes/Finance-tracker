import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { configureApiApplication } from '../src/bootstrap';

describe('required platform dependencies', () => {
  let app: INestApplication;

  beforeAll(async () => {
    const module = await Test.createTestingModule({ imports: [AppModule] }).compile();
    app = module.createNestApplication({ bufferLogs: true });
    configureApiApplication(app, { installOpenApi: false });
    await app.listen(0, '127.0.0.1');
  });

  afterAll(async () => {
    await app.close();
  });

  it('fails unless real PostgreSQL and Redis dependencies are available', async () => {
    await request(app.getHttpServer())
      .get('/api/v1/health/ready')
      .expect(200)
      .expect({
        status: 'ready',
        dependencies: { postgresql: 'up', redis: 'up' },
      });
  });
});
