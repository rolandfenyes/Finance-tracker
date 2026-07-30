import { Module } from '@nestjs/common';
import { AuthenticationGuard, VerifiedEmailGuard } from './authentication.guard';
import { IdentityController } from './identity.controller';
import { IdentityRepository } from './identity.repository';
import { IdentityService } from './identity.service';
import { LoginRateLimiter } from './login-rate-limiter.service';
import { PasskeyService } from './passkey.service';
import { PasswordService } from './password.service';
import { RedisSecurityService } from './redis-security.service';
import { SessionService } from './session.service';
import { VERIFICATION_NOTIFIER } from './verification-notifier';
import { NotificationsCoreModule } from '../notifications/notifications-core.module';
import { QueuedVerificationNotifier } from '../notifications/notification-notifier.adapters';

@Module({
  imports: [NotificationsCoreModule],
  controllers: [IdentityController],
  providers: [
    IdentityRepository,
    IdentityService,
    PasswordService,
    RedisSecurityService,
    LoginRateLimiter,
    SessionService,
    PasskeyService,
    AuthenticationGuard,
    VerifiedEmailGuard,
    QueuedVerificationNotifier,
    { provide: VERIFICATION_NOTIFIER, useExisting: QueuedVerificationNotifier },
  ],
  exports: [
    AuthenticationGuard,
    VerifiedEmailGuard,
    IdentityRepository,
    RedisSecurityService,
    SessionService,
  ],
})
export class IdentityModule {}
