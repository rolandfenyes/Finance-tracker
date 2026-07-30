import { Module } from '@nestjs/common';
import { TimeModule } from '../platform/time/time.module';
import { EMAIL_PROVIDER_PORT, SafeEmailProvider } from './email-provider';
import { NotificationsProcessor, NotificationsQueueService } from './notifications-queue.service';
import { NotificationsRepository } from './notifications.repository';
import { NotificationsService } from './notifications.service';

@Module({
  imports: [TimeModule],
  providers: [
    NotificationsRepository,
    NotificationsService,
    NotificationsProcessor,
    NotificationsQueueService,
    SafeEmailProvider,
    { provide: EMAIL_PROVIDER_PORT, useExisting: SafeEmailProvider },
  ],
  exports: [NotificationsService, NotificationsQueueService],
})
export class NotificationsCoreModule {}
