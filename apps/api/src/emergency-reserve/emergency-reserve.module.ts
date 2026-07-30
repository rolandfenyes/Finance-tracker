import { Module } from '@nestjs/common';
import { CurrencyModule } from '../currency/currency.module';
import { IdentityModule } from '../identity/identity.module';
import { LedgerModule } from '../ledger/ledger.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { NotificationsCoreModule } from '../notifications/notifications-core.module';
import { EmergencyReserveController } from './emergency-reserve.controller';
import { EmergencyReserveRepository } from './emergency-reserve.repository';
import { EmergencyReserveService } from './emergency-reserve.service';

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
  controllers: [EmergencyReserveController],
  providers: [EmergencyReserveRepository, EmergencyReserveService],
  exports: [EmergencyReserveRepository, EmergencyReserveService],
})
export class EmergencyReserveModule {}
