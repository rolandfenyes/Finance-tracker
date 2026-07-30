import { Module } from '@nestjs/common';
import { TimeModule } from '../platform/time/time.module';
import { EMAIL_PROVIDER_PORT, SafeEmailProvider } from './email-provider';
import { NotificationsProcessor, NotificationsQueueService } from './notifications-queue.service';
import { NotificationsRepository } from './notifications.repository';
import { NotificationsService } from './notifications.service';
import { DurableNotificationTriggerService } from './durable-notification-trigger.service';
import { NOTIFICATION_TRIGGER } from './notification-trigger.port';

@Module({
  imports: [TimeModule],
  providers: [
    NotificationsRepository,
    NotificationsService,
    NotificationsProcessor,
    NotificationsQueueService,
    DurableNotificationTriggerService,
    SafeEmailProvider,
    { provide: EMAIL_PROVIDER_PORT, useExisting: SafeEmailProvider },
    { provide: NOTIFICATION_TRIGGER, useExisting: DurableNotificationTriggerService },
  ],
  exports: [
    NotificationsRepository,
    NotificationsService,
    NotificationsQueueService,
    NOTIFICATION_TRIGGER,
  ],
})
export class NotificationsCoreModule {}
