import { readFile } from 'node:fs/promises';
import { NestFactory } from '@nestjs/core';
import { format } from 'prettier';
import { setOpenApiGenerationEnvironment } from './openapi-environment';

setOpenApiGenerationEnvironment();

async function check(): Promise<void> {
  const [{ AppModule }, { configureApiApplication }, openApi] = await Promise.all([
    import('../app.module'),
    import('../bootstrap'),
    import('./openapi'),
  ]);
  const app = await NestFactory.create(AppModule, {
    abortOnError: true,
    bufferLogs: true,
    logger: false,
  });

  try {
    configureApiApplication(app, { installOpenApi: false });
    await app.init();
    const actual = await format(JSON.stringify(openApi.createOpenApiDocument(app)), {
      parser: 'json',
    });
    const expected = await readFile(openApi.OPENAPI_DOCUMENT_PATH, 'utf8');

    if (actual !== expected) {
      throw new Error(
        `OpenAPI drift detected. Run "pnpm openapi:generate" and review the contract diff.`,
      );
    }
  } finally {
    await app.close();
  }
}

void check();
