import { Module } from '@nestjs/common';
import { FeedbackModule } from '../feedback/feedback.module';
import { IdentityModule } from '../identity/identity.module';
import { AesGcmEncryptedSettingAdapter } from '../platform/security/aes-gcm-encrypted-setting.adapter';
import { ENCRYPTED_SETTING_PORT } from '../platform/security/encrypted-setting.port';
import { AdminGuard } from './admin.guard';
import { AdministrationController } from './administration.controller';
import { AdministrationService } from './administration.service';
import { DeferredRecoveryNotifier, RECOVERY_NOTIFIER } from './recovery-notifier';

@Module({
  imports: [IdentityModule, FeedbackModule],
  controllers: [AdministrationController],
  providers: [
    AdministrationService,
    AdminGuard,
    AesGcmEncryptedSettingAdapter,
    { provide: ENCRYPTED_SETTING_PORT, useExisting: AesGcmEncryptedSettingAdapter },
    { provide: RECOVERY_NOTIFIER, useClass: DeferredRecoveryNotifier },
  ],
})
export class AdministrationModule {}
