import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { environmentFileFor, validateEnvironment } from './platform/config/environment';
import { DatabaseModule } from './platform/database/database.module';
import { HealthModule } from './platform/health/health.module';
import { IdempotencyModule } from './platform/idempotency/idempotency.module';
import { PlatformLoggingModule } from './platform/logging/logging.module';
import { IdentityModule } from './identity/identity.module';
import { UsersModule } from './users/users.module';
import { LedgerModule } from './ledger/ledger.module';
import { CurrencyModule } from './currency/currency.module';

@Module({
  imports: [
    ConfigModule.forRoot({
      cache: true,
      envFilePath: environmentFileFor(process.env.NODE_ENV),
      ignoreEnvFile: process.env.NODE_ENV === 'production',
      isGlobal: true,
      validate: validateEnvironment,
    }),
    PlatformLoggingModule,
    DatabaseModule,
    IdempotencyModule,
    IdentityModule,
    UsersModule,
    CurrencyModule,
    LedgerModule,
    HealthModule,
  ],
})
export class AppModule {}
