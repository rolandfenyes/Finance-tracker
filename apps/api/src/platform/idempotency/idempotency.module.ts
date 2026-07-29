import { Module } from '@nestjs/common';
import { DatabaseModule } from '../database/database.module';
import { TimeModule } from '../time/time.module';
import { IdempotencyService } from './idempotency.service';

@Module({
  imports: [DatabaseModule, TimeModule],
  providers: [IdempotencyService],
  exports: [IdempotencyService],
})
export class IdempotencyModule {}
