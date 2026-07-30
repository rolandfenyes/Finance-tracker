import { Module } from '@nestjs/common';
import { AdminOperationsController, InternalMetricsController } from './operations.controller';
import { OperationsMetricsGuard } from './operations-metrics.guard';
import { OperationsMetricsService } from './operations-metrics.service';
import { OperationsService } from './operations.service';
import { IdentityModule } from '../../identity/identity.module';

@Module({
  imports: [IdentityModule],
  controllers: [AdminOperationsController, InternalMetricsController],
  providers: [OperationsMetricsService, OperationsMetricsGuard, OperationsService],
  exports: [OperationsMetricsService],
})
export class OperationsModule {}
