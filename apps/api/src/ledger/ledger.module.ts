import { Module } from '@nestjs/common';
import { IdentityModule } from '../identity/identity.module';
import { IdempotencyModule } from '../platform/idempotency/idempotency.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { LedgerController } from './ledger.controller';
import { LedgerRepository } from './ledger.repository';
import { LedgerService } from './ledger.service';

@Module({
  imports: [IdentityModule, IdempotencyModule, TimeModule, UsersModule],
  controllers: [LedgerController],
  providers: [LedgerRepository, LedgerService],
  exports: [LedgerRepository, LedgerService],
})
export class LedgerModule {}
