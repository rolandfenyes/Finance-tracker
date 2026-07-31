import nodemailer from 'nodemailer';
import { SafeEmailProvider } from './email-provider';

jest.mock('nodemailer', () => ({
  __esModule: true,
  default: { createTransport: jest.fn() },
}));

const message = {
  to: 'recipient@example.test',
  subject: 'Synthetic subject',
  textBody: 'Synthetic body',
  correlationId: '00000000-0000-4000-8000-000000000001',
};

describe('SafeEmailProvider SMTP delivery', () => {
  const sendMail = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    jest.mocked(nodemailer.createTransport).mockReturnValue({ sendMail } as never);
  });

  it('uses authenticated STARTTLS without exposing credentials in the message', async () => {
    sendMail.mockResolvedValue({ messageId: '<synthetic-message@example.test>' });
    const provider = new SafeEmailProvider(
      config({
        EMAIL_DELIVERY_ENABLED: true,
        EMAIL_PROVIDER: 'smtp',
        EMAIL_FROM_ADDRESS: 'sender@example.test',
        EMAIL_FROM_NAME: 'MyMoneyMap',
        EMAIL_REPLY_TO_ADDRESS: 'support@example.test',
        SMTP_HOST: 'mail.example.test',
        SMTP_PORT: 587,
        SMTP_USERNAME: 'synthetic-user',
        SMTP_PASSWORD: 'synthetic-password',
        SMTP_SECURITY: 'starttls',
        SMTP_CONNECTION_TIMEOUT_MS: 15_000,
      }) as never,
    );

    await expect(provider.send(message)).resolves.toEqual({
      messageId: '<synthetic-message@example.test>',
    });
    expect(nodemailer.createTransport).toHaveBeenCalledWith({
      host: 'mail.example.test',
      port: 587,
      secure: false,
      requireTLS: true,
      auth: { user: 'synthetic-user', pass: 'synthetic-password' },
      connectionTimeout: 15_000,
      greetingTimeout: 15_000,
      socketTimeout: 15_000,
      tls: { minVersion: 'TLSv1.2' },
    });
    expect(sendMail).toHaveBeenCalledWith({
      from: { address: 'sender@example.test', name: 'MyMoneyMap' },
      replyTo: 'support@example.test',
      to: message.to,
      subject: message.subject,
      text: message.textBody,
      headers: { 'X-MyMoneyMap-Correlation-Id': message.correlationId },
    });
    expect(JSON.stringify(sendMail.mock.calls)).not.toContain('synthetic-password');
  });

  it('uses implicit TLS when configured and rejects incomplete SMTP credentials', async () => {
    sendMail.mockResolvedValue({ messageId: '<synthetic-message@example.test>' });
    const implicitTls = new SafeEmailProvider(
      config({
        EMAIL_DELIVERY_ENABLED: true,
        EMAIL_PROVIDER: 'smtp',
        EMAIL_FROM_ADDRESS: 'sender@example.test',
        EMAIL_FROM_NAME: 'MyMoneyMap',
        SMTP_HOST: 'mail.example.test',
        SMTP_PORT: 465,
        SMTP_USERNAME: 'synthetic-user',
        SMTP_PASSWORD: 'synthetic-password',
        SMTP_SECURITY: 'tls',
        SMTP_CONNECTION_TIMEOUT_MS: 15_000,
      }) as never,
    );
    await implicitTls.send(message);
    expect(nodemailer.createTransport).toHaveBeenCalledWith(
      expect.objectContaining({ secure: true, requireTLS: false }),
    );

    const incomplete = new SafeEmailProvider(
      config({
        EMAIL_DELIVERY_ENABLED: true,
        EMAIL_PROVIDER: 'smtp',
        EMAIL_FROM_ADDRESS: 'sender@example.test',
        EMAIL_FROM_NAME: 'MyMoneyMap',
        SMTP_HOST: 'mail.example.test',
        SMTP_PORT: 587,
        SMTP_USERNAME: 'synthetic-user',
        SMTP_SECURITY: 'starttls',
        SMTP_CONNECTION_TIMEOUT_MS: 15_000,
      }) as never,
    );
    await expect(incomplete.send(message)).rejects.toThrow('email_provider_not_configured');
  });

  it('does not initialize SMTP while delivery is disabled', async () => {
    const provider = new SafeEmailProvider(
      config({ EMAIL_DELIVERY_ENABLED: false, EMAIL_PROVIDER: 'smtp' }) as never,
    );

    await expect(provider.send(message)).rejects.toThrow('email_delivery_disabled');
    expect(nodemailer.createTransport).not.toHaveBeenCalled();
  });
});

function config(values: Record<string, unknown>): {
  get<T>(key: string): T | undefined;
  getOrThrow<T>(key: string): T;
} {
  return {
    get: <T>(key: string): T | undefined => values[key] as T | undefined,
    getOrThrow: <T>(key: string): T => {
      if (values[key] === undefined) throw new Error(`Missing synthetic config: ${key}`);
      return values[key] as T;
    },
  };
}
