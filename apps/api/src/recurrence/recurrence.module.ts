import { Module } from '@nestjs/common';
import { IdentityModule } from '../identity/identity.module';
import { TimeModule } from '../platform/time/time.module';
import { UsersModule } from '../users/users.module';
import { RecurrenceController } from './recurrence.controller';
import { RecurrenceProcessor, RecurrenceQueueService } from './recurrence-queue.service';
import { RecurrenceRepository } from './recurrence.repository';
import { RecurrenceService } from './recurrence.service';

@Module({
  imports: [IdentityModule, UsersModule, TimeModule],
  controllers: [RecurrenceController],
  providers: [RecurrenceRepository, RecurrenceService, RecurrenceProcessor, RecurrenceQueueService],
  exports: [RecurrenceRepository, RecurrenceService, RecurrenceQueueService],
})
export class RecurrenceModule {}
