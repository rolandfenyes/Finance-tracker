import { Inject, Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import nodemailer from 'nodemailer';

export type EmailProviderKind = 'disabled' | 'log' | 'postmark' | 'smtp';

export interface EmailProviderMessage {
  to: string;
  subject: string;
  textBody: string;
  correlationId: string;
}

export interface EmailProvider {
  send(message: EmailProviderMessage): Promise<{ messageId: string }>;
}

export const EMAIL_PROVIDER_PORT = Symbol('EMAIL_PROVIDER_PORT');

@Injectable()
export class SafeEmailProvider implements EmailProvider {
  private readonly logger = new Logger(SafeEmailProvider.name);

  constructor(@Inject(ConfigService) private readonly config: ConfigService) {}

  async send(message: EmailProviderMessage): Promise<{ messageId: string }> {
    const enabled = this.config.getOrThrow<boolean>('EMAIL_DELIVERY_ENABLED');
    const provider = this.config.getOrThrow<EmailProviderKind>('EMAIL_PROVIDER');
    if (!enabled || provider === 'disabled') throw new Error('email_delivery_disabled');
    if (provider === 'log') {
      this.logger.log(
        { correlationId: message.correlationId },
        'Email accepted by safe log provider',
      );
      return { messageId: `log-${message.correlationId}` };
    }
    if (provider === 'smtp') return this.sendSmtp(message);
    return this.sendPostmark(message);
  }

  private async sendPostmark(message: EmailProviderMessage): Promise<{ messageId: string }> {
    const token = this.config.get<string>('POSTMARK_SERVER_TOKEN');
    const from = this.config.get<string>('EMAIL_FROM_ADDRESS');
    if (!token || !from) throw new Error('email_provider_not_configured');
    const response = await fetch(`${this.config.getOrThrow<string>('POSTMARK_BASE_URL')}/email`, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-postmark-server-token': token,
      },
      body: JSON.stringify({
        From: from,
        To: message.to,
        Subject: message.subject,
        TextBody: message.textBody,
        MessageStream: 'outbound',
        Metadata: { correlationId: message.correlationId },
      }),
      signal: AbortSignal.timeout(5_000),
    });
    if (!response.ok) throw new Error(`email_provider_failure_${response.status}`);
    const result = (await response.json()) as { MessageID?: string };
    if (!result.MessageID) throw new Error('email_provider_invalid_response');
    return { messageId: result.MessageID };
  }

  private async sendSmtp(message: EmailProviderMessage): Promise<{ messageId: string }> {
    const host = this.config.get<string>('SMTP_HOST');
    const username = this.config.get<string>('SMTP_USERNAME');
    const password = this.config.get<string>('SMTP_PASSWORD');
    const from = this.config.get<string>('EMAIL_FROM_ADDRESS');
    if (!host || !username || !password || !from) {
      throw new Error('email_provider_not_configured');
    }
    const security = this.config.getOrThrow<'none' | 'starttls' | 'tls'>('SMTP_SECURITY');
    const timeout = this.config.getOrThrow<number>('SMTP_CONNECTION_TIMEOUT_MS');
    const transport = nodemailer.createTransport({
      host,
      port: this.config.getOrThrow<number>('SMTP_PORT'),
      secure: security === 'tls',
      requireTLS: security === 'starttls',
      auth: { user: username, pass: password },
      connectionTimeout: timeout,
      greetingTimeout: timeout,
      socketTimeout: timeout,
      tls: { minVersion: 'TLSv1.2' },
    });
    const result = await transport.sendMail({
      from: {
        address: from,
        name: this.config.getOrThrow<string>('EMAIL_FROM_NAME'),
      },
      replyTo: this.config.get<string>('EMAIL_REPLY_TO_ADDRESS') || undefined,
      to: message.to,
      subject: message.subject,
      text: message.textBody,
      headers: { 'X-MyMoneyMap-Correlation-Id': message.correlationId },
    });
    if (!result.messageId) throw new Error('email_provider_invalid_response');
    return { messageId: result.messageId };
  }
}
