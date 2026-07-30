import { Module } from '@nestjs/common';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { LoansController } from './loans.controller';
import { LoansRepository } from './loans.repository';
import { LoansService } from './loans.service';

@Module({
  imports: [
    IdentityModule,
    UsersModule,
    TimeModule,
    IdempotencyModule,
    CurrencyModule,
    LedgerModule,
  ],
  controllers: [LoansController],
  providers: [LoansRepository, LoansService],
  exports: [LoansRepository, LoansService],
})
export class LoansModule {}
