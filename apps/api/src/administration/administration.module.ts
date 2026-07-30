import { Module } from '@nestjs/common';
import { FeedbackModule } from '../feedback/feedback.module';
import { IdentityModule } from '../identity/identity.module';
import { AesGcmEncryptedSettingAdapter } from '../platform/security/aes-gcm-encrypted-setting.adapter';
import { ENCRYPTED_SETTING_PORT } from '../platform/security/encrypted-setting.port';
import { AdminGuard } from './admin.guard';
import { AdministrationController } from './administration.controller';
import { AdministrationService } from './administration.service';
import { RECOVERY_NOTIFIER } from './recovery-notifier';
import { NotificationsCoreModule } from '../notifications/notifications-core.module';
import { QueuedRecoveryNotifier } from '../notifications/notification-notifier.adapters';

@Module({
  imports: [IdentityModule, FeedbackModule, NotificationsCoreModule],
  controllers: [AdministrationController],
  providers: [
    AdministrationService,
    AdminGuard,
    AesGcmEncryptedSettingAdapter,
    { provide: ENCRYPTED_SETTING_PORT, useExisting: AesGcmEncryptedSettingAdapter },
    QueuedRecoveryNotifier,
    { provide: RECOVERY_NOTIFIER, useExisting: QueuedRecoveryNotifier },
  ],
})
export class AdministrationModule {}
