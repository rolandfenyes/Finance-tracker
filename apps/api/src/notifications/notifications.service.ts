/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash, randomUUID } from 'node:crypto';
import { CLOCK, type Clock } from '../platform/time/clock';
import { ApplicationError } from '../platform/http/application-error';
import { catalogTemplate, type EmailLocale } from './email-template.catalog';
import { NotificationsRepository } from './notifications.repository';

@Injectable()
export class NotificationsService {
  private readonly logger = new Logger(NotificationsService.name);
  constructor(
    @Inject(NotificationsRepository) private readonly repository: NotificationsRepository,
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  preference(userId: string) {
    return this.repository.preference(userId);
  }

  setPreference(userId: string, educationalEnabled: boolean) {
    return this.repository.setPreference(userId, educationalEnabled, this.clock.now().toDate());
  }

  async prepare(input: {
    eventKey: string;
    recipientEmail: string;
    templateCode: string;
    data: Record<string, string>;
    locale?: string;
    provenance?: Record<string, string>;
  }) {
    const template = catalogTemplate(input.templateCode);
    if (!template) throw new ApplicationError(400, 'BAD_REQUEST', 'Unknown email template');
    const missing = template.contract.filter((field) => !nonEmpty(input.data[field]));
    const unknown = Object.keys(input.data).filter((field) => !template.contract.includes(field));
    if (missing.length || unknown.length) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        `Email template data does not match its contract (missing: ${missing.join(', ') || 'none'}; unknown: ${unknown.join(', ') || 'none'})`,
      );
    }
    const user = await this.repository.userForEmail(input.recipientEmail);
    const locale = normalizeLocale(input.locale ?? user?.desired_language);
    let status = this.config.getOrThrow<boolean>('EMAIL_DELIVERY_ENABLED') ? 'queued' : 'disabled';
    if (template.classification === 'educational' && user) {
      const preference = await this.repository.preference(user.id);
      if (!preference.educationalEnabled) status = 'preference_blocked';
    }
    if (await this.repository.isSuppressed(input.recipientEmail)) status = 'suppressed';
    const correlationId = randomUUID();
    const delivery = await this.repository.createDelivery({
      eventKey: createHash('sha256').update(input.eventKey).digest('hex'),
      correlationId,
      userId: user?.id ?? null,
      recipientEmail: input.recipientEmail.trim().toLowerCase(),
      templateCode: input.templateCode,
      locale,
      classification: template.classification,
      data: input.data,
      provenance: input.provenance ?? {},
      status,
      now: this.clock.now().toDate(),
    });
    this.logger.log(
      {
        deliveryId: delivery.id,
        correlationId,
        templateCode: input.templateCode,
        status: delivery.status,
      },
      'Email delivery state recorded',
    );
    return {
      ...delivery,
      correlationId,
      shouldQueue:
        status === 'queued' && delivery.status === 'queued' && delivery.queue_job_id === null,
    };
  }

  render(code: string, locale: string, data: Record<string, string>) {
    const template = catalogTemplate(code);
    if (!template) throw new ApplicationError(404, 'NOT_FOUND', 'Email template not found');
    const resolvedLocale = normalizeLocale(locale);
    const missing = template.contract.filter((field) => !nonEmpty(data[field]));
    const unknown = Object.keys(data).filter((field) => !template.contract.includes(field));
    if (missing.length || unknown.length) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        `Email template data does not match its contract (missing: ${missing.join(', ') || 'none'}; unknown: ${unknown.join(', ') || 'none'})`,
      );
    }
    return {
      code,
      version: 1,
      locale: resolvedLocale,
      subject: interpolate(template.subjects[resolvedLocale], data),
      textBody: interpolate(template.bodies[resolvedLocale], data),
    };
  }

  templates() {
    return {
      items: EMAIL_PUBLIC_TEMPLATES(),
      localeFallback: ['requested locale', 'en'],
    };
  }

  preview(code: string, locale: string, data: Record<string, string>) {
    return this.render(code, locale, data);
  }

  channel() {
    return this.repository.channel();
  }

  updateChannel(
    actorId: string,
    input: {
      enabled: boolean;
      provider: 'disabled' | 'log' | 'postmark';
      fromAddress?: string;
      replyToAddress?: string;
    },
  ) {
    if (input.enabled && input.provider === 'disabled') {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        'An enabled email channel requires a provider',
      );
    }
    if (input.enabled && !this.config.getOrThrow<boolean>('EMAIL_DELIVERY_PRODUCTION_APPROVED')) {
      throw new ApplicationError(
        409,
        'CONFLICT',
        'Email production delivery is gated until Step 21 approval',
      );
    }
    return this.repository.updateChannel({ ...input, actorId, now: this.clock.now().toDate() });
  }
}

import { EMAIL_TEMPLATE_CATALOG } from './email-template.catalog';
function EMAIL_PUBLIC_TEMPLATES() {
  return EMAIL_TEMPLATE_CATALOG.flatMap((template) =>
    (['en', 'es', 'hu'] as const).map((locale) => ({
      code: template.code,
      version: 1,
      locale,
      classification: template.classification,
      subject: template.subjects[locale],
      dataContract: template.contract,
    })),
  );
}
function normalizeLocale(value?: string): EmailLocale {
  const locale = value?.trim().toLowerCase().split(/[-_]/)[0];
  return locale === 'es' || locale === 'hu' ? locale : 'en';
}
function nonEmpty(value: unknown): value is string {
  return typeof value === 'string' && value.trim().length > 0;
}
function interpolate(source: string, data: Record<string, string>): string {
  return source.replace(/\{\{([a-z0-9_]+)\}\}/g, (_all, key: string) => data[key] ?? '');
}
