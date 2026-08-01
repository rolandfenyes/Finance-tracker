import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type {
  RecoveryNotifier,
  RecoveryNotification,
  EmailChangeNotification,
} from '../administration/recovery-notifier';
import type { VerificationNotifier } from '../identity/verification-notifier';
import { NotificationsQueueService } from './notifications-queue.service';
import { NotificationsService } from './notifications.service';

@Injectable()
export class QueuedVerificationNotifier implements VerificationNotifier {
  constructor(
    @Inject(NotificationsService) private readonly notifications: NotificationsService,
    @Inject(NotificationsQueueService) private readonly queue: NotificationsQueueService,
    @Inject(ConfigService) private readonly config: ConfigService,
  ) {}
  async sendVerification(input: { email: string; fullName: string; token: string }): Promise<void> {
    const delivery = await this.notifications.prepare({
      eventKey: `verification:${input.email}:${input.token}`,
      recipientEmail: input.email,
      templateCode: 'registration_validation',
      data: {
        user_first_name: firstName(input.fullName),
        verification_link: frontendTokenLink(
          this.config.getOrThrow<string>('APP_BASE_URL'),
          '/auth/verify-email',
          input.token,
        ),
      },
    });
    await this.queue.enqueuePrepared(delivery);
    const welcome = await this.notifications.prepare({
      eventKey: `welcome:${input.email}`,
      recipientEmail: input.email,
      templateCode: 'welcome',
      data: { user_first_name: firstName(input.fullName) },
    });
    await this.queue.enqueuePrepared(welcome);
  }
}

@Injectable()
export class QueuedRecoveryNotifier implements RecoveryNotifier {
  constructor(
    @Inject(NotificationsService) private readonly notifications: NotificationsService,
    @Inject(NotificationsQueueService) private readonly queue: NotificationsQueueService,
    @Inject(ConfigService) private readonly config: ConfigService,
  ) {}
  sendPasswordReset(input: RecoveryNotification): Promise<void> {
    return this.send('password_reset', input, {
      user_first_name: firstName(input.fullName),
      reset_link: `${this.config.getOrThrow<string>('APP_BASE_URL')}/reset-password?token=${encodeURIComponent(input.token)}`,
    });
  }
  sendEmailVerification(input: RecoveryNotification): Promise<void> {
    return this.send('registration_validation', input, {
      user_first_name: firstName(input.fullName),
      verification_link: frontendTokenLink(
        this.config.getOrThrow<string>('APP_BASE_URL'),
        '/auth/verify-email',
        input.token,
      ),
    });
  }
  sendEmailChange(input: EmailChangeNotification): Promise<void> {
    return this.send('email_change', input, {
      user_first_name: firstName(input.fullName),
      pending_email: input.pendingEmail,
      change_link: `${this.config.getOrThrow<string>('APP_BASE_URL')}/confirm-email-change?token=${encodeURIComponent(input.token)}`,
    });
  }
  private async send(
    code: string,
    input: RecoveryNotification,
    data: Record<string, string>,
  ): Promise<void> {
    const delivery = await this.notifications.prepare({
      eventKey: `${code}:${input.email}:${input.token}`,
      recipientEmail: input.email,
      templateCode: code,
      data,
    });
    await this.queue.enqueuePrepared(delivery);
  }
}
function firstName(fullName: string): string {
  return fullName.trim().split(/\s+/)[0] || 'there';
}

function frontendTokenLink(baseUrl: string, path: string, token: string): string {
  const url = new URL(path, baseUrl);
  url.searchParams.set('token', token);
  return url.toString();
}
