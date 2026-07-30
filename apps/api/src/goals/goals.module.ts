import { Module } from '@nestjs/common';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { NotificationsCoreModule } from '../notifications/notifications-core.module';
import { GoalsController } from './goals.controller';
import { GoalsRepository } from './goals.repository';
import { GoalsService } from './goals.service';

@Module({
  imports: [
    IdentityModule,
    UsersModule,
    TimeModule,
    IdempotencyModule,
    CurrencyModule,
    LedgerModule,
    NotificationsCoreModule,
  ],
  controllers: [GoalsController],
  providers: [GoalsRepository, GoalsService],
  exports: [GoalsRepository, GoalsService],
})
export class GoalsModule {}
