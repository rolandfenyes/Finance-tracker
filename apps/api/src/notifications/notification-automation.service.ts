import { Inject, Injectable } from '@nestjs/common';
import { EmergencyReserveService } from '../emergency-reserve/emergency-reserve.service';
import { ReportingService } from '../reporting/reporting.service';
import { NOTIFICATION_TRIGGER, type NotificationTrigger } from './notification-trigger.port';
import { NotificationsRepository } from './notifications.repository';

export type NotificationAutomationKind = 'tips' | 'weekly' | 'monthly' | 'yearly' | 'ef-motivation';

export const NOTIFICATION_AUTOMATION_KINDS: readonly NotificationAutomationKind[] = [
  'tips',
  'weekly',
  'monthly',
  'yearly',
  'ef-motivation',
];

@Injectable()
export class NotificationAutomationService {
  constructor(
    @Inject(NotificationsRepository) private readonly repository: NotificationsRepository,
    @Inject(ReportingService) private readonly reporting: ReportingService,
    @Inject(EmergencyReserveService) private readonly emergency: EmergencyReserveService,
    @Inject(NOTIFICATION_TRIGGER) private readonly trigger: NotificationTrigger,
  ) {}

  async run(kind: NotificationAutomationKind, referenceDate: string): Promise<void> {
    const recipients = await this.repository.verifiedRecipients();
    const period = periodFor(kind, referenceDate);
    for (const recipient of recipients) {
      if (kind === 'tips') {
        await this.trigger.educationalTips(recipient.id, period.key);
      } else if (kind === 'ef-motivation') {
        await this.trigger.emergencyMotivation(
          recipient.id,
          await this.emergency.reserve(recipient.id),
          period.key,
        );
      } else {
        const report = await this.reporting.notificationPeriod(
          recipient.id,
          period.first,
          period.last,
        );
        await this.trigger.periodicReport(recipient.id, {
          cadence: kind,
          ...report,
        });
      }
    }
  }
}

function periodFor(
  kind: NotificationAutomationKind,
  referenceDate: string,
): { first: string; last: string; key: string } {
  const reference = parseDate(referenceDate);
  if (kind === 'monthly') {
    const last = new Date(Date.UTC(reference.getUTCFullYear(), reference.getUTCMonth(), 0));
    const first = new Date(Date.UTC(last.getUTCFullYear(), last.getUTCMonth(), 1));
    return range(first, last);
  }
  if (kind === 'yearly') {
    const year = reference.getUTCFullYear() - 1;
    return range(new Date(Date.UTC(year, 0, 1)), new Date(Date.UTC(year, 11, 31)));
  }
  const day = reference.getUTCDay();
  const previousSunday = addDays(reference, -(day === 0 ? 7 : day));
  const previousMonday = addDays(previousSunday, -6);
  return range(previousMonday, previousSunday);
}

function range(first: Date, last: Date): { first: string; last: string; key: string } {
  const firstText = dateOnly(first);
  const lastText = dateOnly(last);
  return { first: firstText, last: lastText, key: `${firstText}:${lastText}` };
}

function parseDate(value: string): Date {
  const parsed = new Date(`${value}T00:00:00.000Z`);
  if (!Number.isFinite(parsed.getTime()) || dateOnly(parsed) !== value) {
    throw new Error('notification_automation_invalid_reference_date');
  }
  return parsed;
}

function addDays(value: Date, days: number): Date {
  return new Date(value.getTime() + days * 86_400_000);
}

function dateOnly(value: Date): string {
  return value.toISOString().slice(0, 10);
}
