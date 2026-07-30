import { Module } from '@nestjs/common';
import { AdminGuard } from '../administration/admin.guard';
import { IdentityModule } from '../identity/identity.module';
import { NotificationsAdminController } from './notifications-admin.controller';
import { NotificationsController } from './notifications.controller';
import { NotificationsCoreModule } from './notifications-core.module';
import { ReportingModule } from '../reporting/reporting.module';
import { EmergencyReserveModule } from '../emergency-reserve/emergency-reserve.module';
import { NotificationAutomationService } from './notification-automation.service';

@Module({
  imports: [IdentityModule, NotificationsCoreModule, ReportingModule, EmergencyReserveModule],
  controllers: [NotificationsController, NotificationsAdminController],
  providers: [AdminGuard, NotificationAutomationService],
  exports: [NotificationAutomationService],
})
export class NotificationsModule {}
