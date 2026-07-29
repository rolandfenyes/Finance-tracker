import { Module } from '@nestjs/common';
import { BudgetingModule } from '../budgeting/budgeting.module';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { ReportCalculator } from './report-calculator';
import { ReportingController } from './reporting.controller';
import { ReportingRepository } from './reporting.repository';
import { ReportingService } from './reporting.service';

@Module({
  imports: [IdentityModule, UsersModule, CurrencyModule, BudgetingModule, TimeModule],
  controllers: [ReportingController],
  providers: [ReportingRepository, ReportCalculator, ReportingService],
  exports: [ReportingService],
})
export class ReportingModule {}
