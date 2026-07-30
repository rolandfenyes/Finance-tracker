import { Module } from '@nestjs/common';
import { AdminGuard } from '../administration/admin.guard';
import { IdentityModule } from '../identity/identity.module';
import { NotificationsAdminController } from './notifications-admin.controller';
import { NotificationsController } from './notifications.controller';
import { NotificationsCoreModule } from './notifications-core.module';

@Module({
  imports: [IdentityModule, NotificationsCoreModule],
  controllers: [NotificationsController, NotificationsAdminController],
  providers: [AdminGuard],
})
export class NotificationsModule {}
