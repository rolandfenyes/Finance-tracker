import { forwardRef, Module } from '@nestjs/common';
import { BudgetingModule } from '../budgeting/budgeting.module';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { LedgerController } from './ledger.controller';
import { LedgerPlanningReadService } from './ledger-planning-read.service';
import { LedgerRepository } from './ledger.repository';
import { LedgerService } from './ledger.service';

@Module({
  imports: [
    IdentityModule,
    IdempotencyModule,
    TimeModule,
    UsersModule,
    CurrencyModule,
    forwardRef(() => BudgetingModule),
  ],
  controllers: [LedgerController],
  providers: [LedgerRepository, LedgerService, LedgerPlanningReadService],
  exports: [LedgerRepository, LedgerService, LedgerPlanningReadService],
})
export class LedgerModule {}
