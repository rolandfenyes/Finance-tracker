import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type {
  EmergencyReserve,
  EmergencyReserveMovement,
} from '../emergency-reserve/emergency-reserve.types';
import type { Goal } from '../goals/goals.types';
import type {
  BudgetOverspendingNotification,
  NotificationTrigger,
  PeriodicReportNotification,
} from './notification-trigger.port';
import { NotificationsQueueService } from './notifications-queue.service';
import { NotificationsRepository } from './notifications.repository';
import { NotificationsService } from './notifications.service';

@Injectable()
export class DurableNotificationTriggerService implements NotificationTrigger {
  constructor(
    @Inject(NotificationsService) private readonly notifications: NotificationsService,
    @Inject(NotificationsQueueService) private readonly queue: NotificationsQueueService,
    @Inject(NotificationsRepository) private readonly repository: NotificationsRepository,
    @Inject(ConfigService) private readonly config: ConfigService,
  ) {}

  goalCompleted(userId: string, goal: Goal): Promise<void> {
    return this.sendToUser(userId, {
      eventKey: `goal.completed:${goal.id}:${goal.updatedAt}`,
      templateCode: 'goal_congratulations',
      data: {
        user_first_name: '{{first_name}}',
        achievement_summary: `${goal.title}: ${goal.currentAmount} ${goal.currency} of ${goal.targetAmount} ${goal.currency}.`,
        cta_url: `${this.appUrl()}/goals`,
      },
      provenance: {
        source: 'GoalsService',
        entityId: goal.id,
        version: goal.updatedAt,
        currency: goal.currency,
      },
    });
  }

  emergencyWithdrawal(
    userId: string,
    reserve: EmergencyReserve,
    movement: EmergencyReserveMovement,
  ): Promise<void> {
    return this.sendToUser(userId, {
      eventKey: `emergency.withdrawal:${movement.id}`,
      templateCode: 'emergency_withdrawal',
      data: {
        user_first_name: '{{first_name}}',
        withdrawal_amount: `${movement.reserveAmount} ${movement.reserveCurrency}`,
        remaining_amount: `${reserve.currentAmount} ${reserve.currency}`,
        cta_url: `${this.appUrl()}/emergency`,
      },
      provenance: {
        source: 'EmergencyReserveService',
        entityId: movement.id,
        version: movement.createdAt,
        currency: reserve.currency,
        occurredOn: movement.occurredOn,
      },
    });
  }

  emergencyMotivation(userId: string, reserve: EmergencyReserve, periodKey: string): Promise<void> {
    if (!reserve.configured || reserve.targetAmount === '0') return Promise.resolve();
    return this.sendToUser(userId, {
      eventKey: `emergency.motivation:${userId}:${periodKey}`,
      templateCode: 'emergency_motivation',
      data: {
        user_first_name: '{{first_name}}',
        ef_current: `${reserve.currentAmount} ${reserve.currency}`,
        ef_target: `${reserve.targetAmount} ${reserve.currency}`,
        cta_url: `${this.appUrl()}/emergency`,
      },
      provenance: {
        source: 'EmergencyReserveService',
        entityId: userId,
        version: reserve.updatedAt ?? periodKey,
        currency: reserve.currency,
      },
    });
  }

  async feedbackCreated(input: {
    feedbackId: string;
    userId: string;
    title: string;
    kind: string;
    severity: string | null;
    createdAt: string;
  }): Promise<void> {
    const recipient = await this.repository.feedbackRecipient();
    if (!recipient) return;
    await this.send({
      eventKey: `feedback.created:${input.feedbackId}`,
      recipientEmail: recipient,
      templateCode: 'feedback_new',
      data: {
        feedback_title: input.title,
        feedback_kind: input.kind,
        feedback_severity: input.severity ?? 'not set',
        feedback_url: `${this.appUrl()}/admin/feedback/${input.feedbackId}`,
      },
      provenance: {
        source: 'FeedbackService',
        entityId: input.feedbackId,
        version: input.createdAt,
      },
    });
  }

  feedbackResolved(input: {
    feedbackId: string;
    userId: string;
    title: string;
    resolvedAt: string;
  }): Promise<void> {
    return this.sendToUser(input.userId, {
      eventKey: `feedback.resolved:${input.feedbackId}:${input.resolvedAt}`,
      templateCode: 'feedback_resolved',
      data: {
        user_first_name: '{{first_name}}',
        feedback_title: input.title,
        feedback_url: `${this.appUrl()}/feedback?highlight=${input.feedbackId}`,
      },
      provenance: {
        source: 'AdministrationService',
        entityId: input.feedbackId,
        version: input.resolvedAt,
      },
    });
  }

  budgetOverspent(
    userId: string,
    sourceEntryId: string,
    snapshot: BudgetOverspendingNotification,
  ): Promise<void> {
    return this.sendToUser(userId, {
      eventKey: `budget.overspent:${sourceEntryId}:${snapshot.ruleId}`,
      templateCode: 'cashflow_overspend',
      data: {
        user_first_name: '{{first_name}}',
        rule_label: snapshot.ruleLabel,
        over_amount: `${snapshot.overspendAmount} ${snapshot.currency}`,
        cta_url: `${this.appUrl()}/cashflow`,
      },
      provenance: {
        source: 'BudgetingService',
        entityId: snapshot.ruleId,
        version: snapshot.calculatedAt,
        currency: snapshot.currency,
        period: snapshot.month,
      },
    });
  }

  periodicReport(userId: string, snapshot: PeriodicReportNotification): Promise<void> {
    return this.sendToUser(userId, {
      eventKey: `report.${snapshot.cadence}:${userId}:${snapshot.period.first}:${snapshot.period.last}`,
      templateCode: `report_${snapshot.cadence}`,
      data: {
        user_first_name: '{{first_name}}',
        report_period: `${snapshot.period.first} – ${snapshot.period.last}`,
        total_spent: `${snapshot.expense} ${snapshot.currency}`,
        total_income: `${snapshot.income} ${snapshot.currency}`,
        net_change: `${snapshot.netCashFlow} ${snapshot.currency}`,
        app_url: `${this.appUrl()}/reports`,
      },
      provenance: {
        source: 'ReportingService',
        version: snapshot.calculatedAt,
        currency: snapshot.currency,
        period: `${snapshot.period.first}/${snapshot.period.last}`,
      },
    });
  }

  educationalTips(userId: string, periodKey: string): Promise<void> {
    return this.sendToUser(userId, {
      eventKey: `tips:${userId}:${periodKey}`,
      templateCode: 'tips_and_tricks',
      data: {
        user_first_name: '{{first_name}}',
        tip_title: 'Review weekly',
        tip_body: 'Review your recorded spending and progress regularly.',
        tip_link: `${this.appUrl()}/reports`,
      },
      provenance: { source: 'NotificationAutomationService', version: periodKey },
    });
  }

  private async sendToUser(
    userId: string,
    input: {
      eventKey: string;
      templateCode: string;
      data: Record<string, string>;
      provenance: Record<string, string>;
    },
  ): Promise<void> {
    const user = await this.repository.userForId(userId);
    if (!user) return;
    const data = Object.fromEntries(
      Object.entries(input.data).map(([key, value]) => [
        key,
        value === '{{first_name}}' ? firstName(user.full_name) : value,
      ]),
    );
    await this.send({
      ...input,
      recipientEmail: user.email,
      locale: user.desired_language,
      data,
    });
  }

  private async send(input: {
    eventKey: string;
    recipientEmail: string;
    templateCode: string;
    data: Record<string, string>;
    provenance: Record<string, string>;
    locale?: string;
  }): Promise<void> {
    const delivery = await this.notifications.prepare(input);
    await this.queue.enqueuePrepared(delivery);
  }

  private appUrl(): string {
    return this.config.getOrThrow<string>('APP_BASE_URL').replace(/\/$/, '');
  }
}

function firstName(fullName: string): string {
  return fullName.trim().split(/\s+/)[0] || 'there';
}
