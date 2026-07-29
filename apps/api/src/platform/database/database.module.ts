import { Global, Inject, Injectable, Module, OnModuleDestroy } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Kysely, PostgresDialect } from 'kysely';
import { Pool } from 'pg';
import { DATABASE, POSTGRES_POOL } from './database.constants';
import type { DatabaseSchema } from './database.types';
import { createPostgresPoolConfig } from './postgres-config';
import { DatabaseTransactionService } from './database-transaction.service';

@Injectable()
class DatabaseLifecycle implements OnModuleDestroy {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async onModuleDestroy(): Promise<void> {
    await this.pool.end();
  }
}

@Global()
@Module({
  providers: [
    {
      provide: POSTGRES_POOL,
      inject: [ConfigService],
      useFactory: (config: ConfigService): Pool =>
        new Pool(
          createPostgresPoolConfig({
            connectionString: config.getOrThrow<string>('DATABASE_URL'),
            tlsMode: config.getOrThrow('DATABASE_TLS_MODE'),
            tlsCa: config.get<string>('DATABASE_TLS_CA'),
            poolMax: config.getOrThrow<number>('DATABASE_POOL_MAX'),
            connectionTimeoutMs: config.getOrThrow<number>('DATABASE_CONNECTION_TIMEOUT_MS'),
            idleTimeoutMs: config.getOrThrow<number>('DATABASE_IDLE_TIMEOUT_MS'),
            maxLifetimeSeconds: config.getOrThrow<number>('DATABASE_MAX_LIFETIME_SECONDS'),
          }),
        ),
    },
    {
      provide: DATABASE,
      inject: [POSTGRES_POOL],
      useFactory: (pool: Pool): Kysely<DatabaseSchema> =>
        new Kysely<DatabaseSchema>({
          dialect: new PostgresDialect({ pool }),
        }),
    },
    DatabaseLifecycle,
    DatabaseTransactionService,
  ],
  exports: [DATABASE, POSTGRES_POOL, DatabaseTransactionService],
})
export class DatabaseModule {
  constructor(private readonly lifecycle: DatabaseLifecycle) {
    void this.lifecycle;
  }
}
