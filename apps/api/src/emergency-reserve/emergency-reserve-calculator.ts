import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { expandRecurrence } from '../recurrence/recurrence-rule';
import type { RecurringRule } from '../recurrence/recurrence.types';
import type { EmergencyReserveMovement, ScheduledActivityTotal } from './emergency-reserve.types';

export function deriveEmergencyReserveBalance(
  movements: readonly Pick<
    EmergencyReserveMovement,
    'direction' | 'reserveAmount' | 'reversedByJournalEntryId'
  >[],
): string {
  return movements
    .filter(({ reversedByJournalEntryId }) => reversedByJournalEntryId === null)
    .reduce(
      (total, movement) =>
        movement.direction === 'contribution'
          ? total.add(ExactDecimal.create(movement.reserveAmount))
          : total.subtract(ExactDecimal.create(movement.reserveAmount)),
      ExactDecimal.create('0'),
    )
    .toString();
}

export function rawScheduledActivityTotals(
  rules: readonly RecurringRule[],
  periodFrom: string,
  periodTo: string,
): ScheduledActivityTotal[] {
  const totals = new Map<
    string,
    { income: ExactDecimal; expense: ExactDecimal; transfer: ExactDecimal }
  >();
  for (const rule of rules) {
    const expansion = expandRecurrence(rule.startsOn, rule.rrule, periodFrom, periodTo);
    if (expansion.truncated) throw new Error('Scheduled activity expansion exceeded its limit');
    const amount = ExactDecimal.create(rule.amount).multiply(
      ExactDecimal.create(String(expansion.dates.length)),
    );
    const row = totals.get(rule.currency) ?? {
      income: ExactDecimal.create('0'),
      expense: ExactDecimal.create('0'),
      transfer: ExactDecimal.create('0'),
    };
    row[rule.economicType] = row[rule.economicType].add(amount);
    totals.set(rule.currency, row);
  }
  return [...totals.entries()]
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([currency, total]) => ({
      currency,
      income: total.income.toString(),
      expense: total.expense.toString(),
      transfer: total.transfer.toString(),
    }));
}

export function nextFullCalendarMonth(current: string): { from: string; to: string } {
  const year = Number(current.slice(0, 4));
  const month = Number(current.slice(5, 7));
  const nextYear = month === 12 ? year + 1 : year;
  const nextMonth = month === 12 ? 1 : month + 1;
  const last = new Date(Date.UTC(nextYear, nextMonth, 0)).getUTCDate();
  const prefix = `${nextYear}-${String(nextMonth).padStart(2, '0')}`;
  return { from: `${prefix}-01`, to: `${prefix}-${String(last).padStart(2, '0')}` };
}
