# MyMoneyMap API foundation

This NestJS application is a side-by-side replacement foundation. Step 01 intentionally contains no
authentication, users, finance domains, migrations, queues, or provider adapters.

## Runtime contract

- Node `22.13.1` and pnpm `10.30.3` are locked at the workspace root.
- Configuration is validated before NestJS starts.
- Production does not load local environment files.
- Required connection URLs have no source-code fallback.
- `/api/v1/health/live` checks only the process.
- `/api/v1/health/ready` actively verifies PostgreSQL and Redis.
- Swagger UI is at `/api/docs`; its JSON contract is at `/api/docs/openapi.json`.

Configuration keys:

| Key               | Required | Contract                                                                    |
| ----------------- | -------: | --------------------------------------------------------------------------- |
| `NODE_ENV`        |      yes | `development`, `test`, or `production`                                      |
| `API_HOST`        |      yes | bind host                                                                   |
| `API_PORT`        |      yes | integer 1–65535                                                             |
| `APP_BASE_URL`    |      yes | canonical URL; HTTPS and non-loopback in production                         |
| `DATABASE_URL`    |      yes | `postgresql://` connection URL supplied by runtime secrets/config           |
| `REDIS_URL`       |      yes | `redis://` or `rediss://` connection URL supplied by runtime secrets/config |
| `LOG_LEVEL`       |      yes | Pino level                                                                  |
| `TRUST_PROXY`     |      yes | `true`, `false`, or a positive hop count                                    |
| `OPENAPI_ENABLED` |      yes | explicit `true` or `false`                                                  |

Errors list invalid key names only; values are never included.

## Local development

1. Copy `.env.backend.example` to the ignored `.env.backend` and replace the local-only password.
2. Run `pnpm deps:up`.
3. Run `pnpm dev`.
4. Run `pnpm deps:down` when finished.

The Compose stack is development-only. Its named volumes are not production backups.

## Verification

```text
pnpm format:check
pnpm lint
pnpm typecheck
pnpm test
pnpm test:integration
pnpm build
pnpm openapi:check
pnpm security:audit
```

The integration suite is deliberately strict: missing environment variables, PostgreSQL, or Redis
make it fail. It never converts unavailable dependencies into a skipped or successful result.
