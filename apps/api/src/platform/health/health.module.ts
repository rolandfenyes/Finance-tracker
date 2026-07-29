import { Module } from '@nestjs/common';
import { HealthController } from './health.controller';
import { DEPENDENCY_PROBE } from './dependency-probe';
import { PostgresRedisProbeService } from './postgres-redis-probe.service';

@Module({
  controllers: [HealthController],
  providers: [
    PostgresRedisProbeService,
    {
      provide: DEPENDENCY_PROBE,
      useExisting: PostgresRedisProbeService,
    },
  ],
  exports: [DEPENDENCY_PROBE],
})
export class HealthModule {}
