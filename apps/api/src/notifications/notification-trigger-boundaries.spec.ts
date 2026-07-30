/* eslint-disable @typescript-eslint/require-await */
import { DurableNotificationTriggerService } from './durable-notification-trigger.service';
import { NotificationAutomationService } from './notification-automation.service';
import type { EmergencyReserve } from '../emergency-reserve/emergency-reserve.types';
import type { Goal } from '../goals/goals.types';

describe('financial notification calculation boundaries', () => {
  const user = {
    id: 'user-1',
    email: 'synthetic@example.test',
    full_name: 'Synthetic User',
    desired_language: 'es',
  };
  const prepared: Array<Record<string, unknown>> = [];
  const notifications = {
    prepare: jest.fn(async (input: Record<string, unknown>) => {
      prepared.push(input);
      return { id: `delivery-${prepared.length}`, status: 'disabled', shouldQueue: false };
    }),
  };
  const queue = { enqueuePrepared: jest.fn(async () => undefined) };
  const repository = {
    userForId: jest.fn(async () => user),
    feedbackRecipient: jest.fn(async () => 'operations@example.test'),
    verifiedRecipients: jest.fn(async () => [{ id: user.id }]),
  };
  const config = {
    getOrThrow: jest.fn((key: string) =>
      key === 'APP_BASE_URL' ? 'https://app.example.test' : 'redis://127.0.0.1:6379/15',
    ),
  };
  const trigger = new DurableNotificationTriggerService(
    notifications as never,
    queue as never,
    repository as never,
    config as never,
  );

  beforeEach(() => {
    prepared.length = 0;
    jest.clearAllMocks();
  });

  it('uses the exact GoalsService snapshot for goal completion values and provenance', async () => {
    const goal = {
      id: 'goal-1',
      title: 'Reserve',
      targetAmount: '1000.120000000001',
      currentAmount: '1000.120000000001',
      remainingAmount: '0',
      progressPercent: '100',
      currency: 'HUF',
      deadline: null,
      priority: 1,
      status: 'completed',
      categoryId: null,
      categoryLabel: null,
      archivedAt: null,
      recurringRule: null,
      contributions: [],
      createdAt: '2026-07-01T00:00:00.000Z',
      updatedAt: '2026-07-30T10:00:00.000Z',
    } satisfies Goal;

    await trigger.goalCompleted(user.id, goal);

    expect(prepared[0]).toMatchObject({
      eventKey: `goal.completed:${goal.id}:${goal.updatedAt}`,
      templateCode: 'goal_congratulations',
      data: {
        achievement_summary: 'Reserve: 1000.120000000001 HUF of 1000.120000000001 HUF.',
      },
      provenance: {
        source: 'GoalsService',
        version: goal.updatedAt,
        currency: 'HUF',
      },
    });
  });

  it('uses EmergencyReserveService movement and balance snapshots without recomputing them', async () => {
    const reserve = emergencySnapshot();
    const movement = reserve.movements[0]!;

    await trigger.emergencyWithdrawal(user.id, reserve, movement);
    await trigger.emergencyMotivation(user.id, reserve, '2026-07-20:2026-07-26');

    expect(prepared[0]).toMatchObject({
      eventKey: `emergency.withdrawal:${movement.id}`,
      data: {
        withdrawal_amount: '20.000000000001 HUF',
        remaining_amount: '80.000000000009 HUF',
      },
      provenance: { source: 'EmergencyReserveService', entityId: movement.id },
    });
    expect(prepared[1]).toMatchObject({
      data: {
        ef_current: '80.000000000009 HUF',
        ef_target: '500.000000000001 HUF',
      },
      provenance: { source: 'EmergencyReserveService' },
    });
  });

  it('uses BudgetingService overspend values exactly', async () => {
    await trigger.budgetOverspent(user.id, 'entry-1', {
      ruleId: 'rule-1',
      ruleLabel: 'Essentials',
      plannedAmount: '500.000000000001',
      spendingAmount: '575.000000000003',
      overspendAmount: '75.000000000002',
      currency: 'EUR',
      month: '2026-07',
      calculatedAt: '2026-07-30T10:00:00.000Z',
    });

    expect(prepared[0]).toMatchObject({
      eventKey: 'budget.overspent:entry-1:rule-1',
      data: { over_amount: '75.000000000002 EUR' },
      provenance: {
        source: 'BudgetingService',
        period: '2026-07',
        currency: 'EUR',
      },
    });
  });

  it('uses ReportingService posted totals and stable period keys on duplicate scheduler runs', async () => {
    const reporting = {
      notificationPeriod: jest.fn(async () => ({
        period: { first: '2026-07-20', last: '2026-07-26' },
        currency: 'USD',
        expense: '12.340000000001',
        income: '50.000000000002',
        netCashFlow: '37.660000000001',
        calculatedAt: '2026-07-27T00:00:00.000Z',
      })),
    };
    const automation = new NotificationAutomationService(
      repository as never,
      reporting as never,
      { reserve: jest.fn(async () => emergencySnapshot()) } as never,
      trigger,
    );

    await automation.run('weekly', '2026-07-27');
    await automation.run('weekly', '2026-07-27');

    expect(reporting.notificationPeriod).toHaveBeenCalledTimes(2);
    expect(prepared).toHaveLength(2);
    expect(prepared[0]).toMatchObject({
      eventKey: 'report.weekly:user-1:2026-07-20:2026-07-26',
      data: {
        total_spent: '12.340000000001 USD',
        total_income: '50.000000000002 USD',
        net_change: '37.660000000001 USD',
      },
      provenance: { source: 'ReportingService' },
    });
    expect(prepared[1]?.eventKey).toBe(prepared[0]?.eventKey);
  });

  it('uses EmergencyReserveService for scheduled motivation and stable keys for tips', async () => {
    const emergency = { reserve: jest.fn(async () => emergencySnapshot()) };
    const automation = new NotificationAutomationService(
      repository as never,
      { notificationPeriod: jest.fn() } as never,
      emergency as never,
      trigger,
    );

    await automation.run('ef-motivation', '2026-07-27');
    await automation.run('tips', '2026-07-27');
    await automation.run('tips', '2026-07-27');

    expect(emergency.reserve).toHaveBeenCalledWith(user.id);
    expect(prepared[0]).toMatchObject({
      eventKey: 'emergency.motivation:user-1:2026-07-20:2026-07-26',
      provenance: { source: 'EmergencyReserveService' },
    });
    expect(prepared[1]?.eventKey).toBe('tips:user-1:2026-07-20:2026-07-26');
    expect(prepared[2]?.eventKey).toBe(prepared[1]?.eventKey);
  });
});

function emergencySnapshot(): EmergencyReserve {
  return {
    configured: true,
    targetAmount: '500.000000000001',
    currentAmount: '80.000000000009',
    currency: 'HUF',
    reserveAccountId: 'account-1',
    linkedInvestmentAccountId: null,
    targetMethodology: {
      code: 'manual_user_defined',
      label: 'User-defined reserve target',
      educationalOnly: true,
    },
    scheduledActivity: {
      classification: 'raw_unclassified_scheduled_activity',
      label: 'Raw scheduled activity totals',
      periodFrom: '2026-08-01',
      periodTo: '2026-08-31',
      totals: [],
    },
    movements: [
      {
        id: 'movement-1',
        journalEntryId: 'entry-1',
        holdingAccountId: 'account-1',
        direction: 'withdrawal',
        amount: '20.000000000001',
        currency: 'HUF',
        reserveAmount: '20.000000000001',
        reserveCurrency: 'HUF',
        occurredOn: '2026-07-30',
        note: null,
        reversedByJournalEntryId: null,
        createdAt: '2026-07-30T10:00:00.000Z',
      },
    ],
    createdAt: '2026-07-01T00:00:00.000Z',
    updatedAt: '2026-07-30T10:00:00.000Z',
  };
}
