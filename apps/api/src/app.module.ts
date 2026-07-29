import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { environmentFileFor, validateEnvironment } from './platform/config/environment';
import { DatabaseModule } from './platform/database/database.module';
import { HealthModule } from './platform/health/health.module';
import { PlatformLoggingModule } from './platform/logging/logging.module';

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
    HealthModule,
  ],
})
export class AppModule {}
