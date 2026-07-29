import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { NestFactory } from '@nestjs/core';
import { format } from 'prettier';
import { setOpenApiGenerationEnvironment } from './openapi-environment';

setOpenApiGenerationEnvironment();

async function generate(): Promise<void> {
  const [{ AppModule }, { configureApiApplication }, openApi] = await Promise.all([
    import('../app.module'),
    import('../bootstrap'),
    import('./openapi'),
  ]);
  const app = await NestFactory.create(AppModule, {
    abortOnError: false,
    bufferLogs: true,
    logger: false,
  });

  try {
    configureApiApplication(app, { installOpenApi: false });
    await app.init();
    const document = openApi.createOpenApiDocument(app);
    const content = await format(JSON.stringify(document), { parser: 'json' });
    await mkdir(dirname(openApi.OPENAPI_DOCUMENT_PATH), { recursive: true });
    await writeFile(openApi.OPENAPI_DOCUMENT_PATH, content, 'utf8');
  } finally {
    await app.close();
  }
}

void generate().catch((error: unknown) => {
  console.error(error);
  process.exitCode = 1;
});
