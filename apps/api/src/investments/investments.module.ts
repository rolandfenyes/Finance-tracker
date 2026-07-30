import { Module } from '@nestjs/common';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { InvestmentsController } from './investments.controller';
import { InvestmentsRepository } from './investments.repository';
import { InvestmentsService } from './investments.service';

@Module({
  imports: [
    IdentityModule,
    IdempotencyModule,
    CurrencyModule,
    LedgerModule,
    UsersModule,
    TimeModule,
  ],
  controllers: [InvestmentsController],
  providers: [InvestmentsRepository, InvestmentsService],
  exports: [InvestmentsService],
})
export class InvestmentsModule {}
