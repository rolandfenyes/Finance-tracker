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
import { DeferredVerificationNotifier, VERIFICATION_NOTIFIER } from './verification-notifier';

@Module({
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
    { provide: VERIFICATION_NOTIFIER, useClass: DeferredVerificationNotifier },
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
