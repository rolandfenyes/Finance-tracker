import { Inject, Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

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
    const provider = this.config.getOrThrow<'disabled' | 'log' | 'postmark'>('EMAIL_PROVIDER');
    if (!enabled || provider === 'disabled') throw new Error('email_delivery_disabled');
    if (provider === 'log') {
      this.logger.log(
        { correlationId: message.correlationId },
        'Email accepted by safe log provider',
      );
      return { messageId: `log-${message.correlationId}` };
    }
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
}
