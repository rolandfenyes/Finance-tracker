import { forwardRef, Module } from '@nestjs/common';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { BudgetCalculator } from './budget-calculator';
import { BudgetingController } from './budgeting.controller';
import { BudgetingRepository } from './budgeting.repository';
import { BudgetingService } from './budgeting.service';
import { CategoryPolicyService } from './category-policy.service';

@Module({
  imports: [
    IdentityModule,
    UsersModule,
    CurrencyModule,
    forwardRef(() => LedgerModule),
    TimeModule,
  ],
  controllers: [BudgetingController],
  providers: [BudgetingRepository, BudgetCalculator, BudgetingService, CategoryPolicyService],
  exports: [BudgetingRepository, BudgetingService, CategoryPolicyService],
})
export class BudgetingModule {}
