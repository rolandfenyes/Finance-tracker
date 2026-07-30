/* eslint-disable @typescript-eslint/explicit-function-return-type,@typescript-eslint/unbound-method */
import { BudgetingService } from './budgeting.service';

describe('BudgetingService notification boundary', () => {
  it('derives the exact overspend snapshot from the tested rules read model', async () => {
    const service = new BudgetingService(
      {} as never,
      {} as never,
      {} as never,
      {} as never,
      {} as never,
      {} as never,
      { now: () => ({ toDate: () => new Date('2026-07-30T10:00:00.000Z') }) } as never,
    );
    jest.spyOn(service, 'rules').mockResolvedValue({
      items: [
        {
          id: 'rule-1',
          label: 'Essentials',
          percent: '50',
          targetHint: null,
          assignedCategoryIds: ['category-1'],
          plan: {
            status: 'available',
            currency: 'EUR',
            plannedAmount: '500.000000000001',
            assignedCategorySpending: '575.000000000003',
            signedVariance: '-75.000000000002',
          },
          createdAt: '2026-07-01T00:00:00.000Z',
          updatedAt: '2026-07-01T00:00:00.000Z',
        },
      ],
      allocation: {
        totalPercent: '50',
        status: 'within_allocation',
        overAllocatedBy: '0',
      },
      period: {
        month: '2026-07',
        currency: 'EUR',
        forecastIncomeStatus: 'available',
        forecastIncome: '1000.000000000002',
      },
    });

    await expect(service.overspending('user-1', '2026-07')).resolves.toEqual([
      {
        ruleId: 'rule-1',
        ruleLabel: 'Essentials',
        plannedAmount: '500.000000000001',
        spendingAmount: '575.000000000003',
        overspendAmount: '75.000000000002',
        currency: 'EUR',
        month: '2026-07',
        calculatedAt: '2026-07-30T10:00:00.000Z',
      },
    ]);
    expect(service.rules).toHaveBeenCalledWith('user-1', '2026-07');
  });
});
