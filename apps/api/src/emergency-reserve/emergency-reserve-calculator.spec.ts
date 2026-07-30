import {
  deriveEmergencyReserveBalance,
  nextFullCalendarMonth,
  rawScheduledActivityTotals,
} from './emergency-reserve-calculator';
import type { RecurringRule } from '../recurrence/recurrence.types';

describe('emergency reserve calculations', () => {
  it('derives the exact allocation from active movements and excludes reversals', () => {
    expect(
      deriveEmergencyReserveBalance([
        {
          direction: 'contribution',
          reserveAmount: '0.1',
          reversedByJournalEntryId: null,
        },
        {
          direction: 'contribution',
          reserveAmount: '0.2',
          reversedByJournalEntryId: null,
        },
        {
          direction: 'withdrawal',
          reserveAmount: '0.3',
          reversedByJournalEntryId: '00000000-0000-4000-8000-000000000001',
        },
      ]),
    ).toBe('0.3');
  });

  it('returns labeled raw totals by economic type without inventing needs', () => {
    const rule = (economicType: RecurringRule['economicType'], amount: string): RecurringRule => ({
      id: crypto.randomUUID(),
      title: 'Synthetic schedule',
      amount,
      currency: 'HUF',
      economicType,
      startsOn: '2026-08-01',
      rrule: 'FREQ=MONTHLY;BYMONTHDAY=1',
      categoryId: null,
      categoryLabel: null,
      goalId: null,
      createdAt: '2026-07-29T00:00:00.000Z',
      updatedAt: '2026-07-29T00:00:00.000Z',
    });
    expect(
      rawScheduledActivityTotals(
        [rule('income', '1000'), rule('expense', '300'), rule('transfer', '200')],
        '2026-08-01',
        '2026-08-31',
      ),
    ).toEqual([{ currency: 'HUF', income: '1000', expense: '300', transfer: '200' }]);
  });

  it('handles year and leap-month boundaries deterministically', () => {
    expect(nextFullCalendarMonth('2026-12-31')).toEqual({
      from: '2027-01-01',
      to: '2027-01-31',
    });
    expect(nextFullCalendarMonth('2028-01-31')).toEqual({
      from: '2028-02-01',
      to: '2028-02-29',
    });
  });
});
