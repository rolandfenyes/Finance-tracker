import {
  RECURRENCE_ITERATION_LIMIT,
  expandRecurrence,
  InvalidRecurrenceRuleError,
  parseRecurrenceRule,
} from './recurrence-rule';

describe('approved RRULE subset', () => {
  it('clamps month-end and leap-day occurrences without date drift', () => {
    expect(
      expandRecurrence('2024-01-31', 'FREQ=MONTHLY', '2024-01-01', '2024-04-30').dates,
    ).toEqual(['2024-01-31', '2024-02-29', '2024-03-31', '2024-04-30']);
    expect(
      expandRecurrence(
        '2024-02-29',
        'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29',
        '2024-01-01',
        '2028-12-31',
      ).dates,
    ).toEqual(['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28', '2028-02-29']);
  });

  it('preserves weekly BYDAY selection and interval anchoring', () => {
    expect(
      expandRecurrence(
        '2026-07-08',
        'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR',
        '2026-07-01',
        '2026-08-10',
      ).dates,
    ).toEqual(['2026-07-10', '2026-07-20', '2026-07-24', '2026-08-03', '2026-08-07']);
  });

  it('applies COUNT and inclusive UNTIL to the full recurrence, not only the query range', () => {
    expect(
      expandRecurrence('2026-07-01', 'FREQ=DAILY;COUNT=3', '2026-07-02', '2026-07-10').dates,
    ).toEqual(['2026-07-02', '2026-07-03']);
    expect(
      expandRecurrence(
        '2026-07-01',
        'FREQ=DAILY;UNTIL=20260703T235959Z',
        '2026-07-01',
        '2026-07-10',
      ).dates,
    ).toEqual(['2026-07-01', '2026-07-02', '2026-07-03']);
  });

  it('rejects unsupported tokens and combinations explicitly', () => {
    expect(() => parseRecurrenceRule('FREQ=MONTHLY;BYSETPOS=1')).toThrow(
      InvalidRecurrenceRuleError,
    );
    expect(() => parseRecurrenceRule('FREQ=MONTHLY;BYDAY=MO')).toThrow(
      'BYDAY is supported only with WEEKLY',
    );
    expect(() => parseRecurrenceRule('FREQ=HOURLY')).toThrow(
      'FREQ must be DAILY, WEEKLY, MONTHLY, or YEARLY',
    );
  });

  it('is process-timezone independent and exposes the 2,000-iteration boundary', () => {
    const originalTimeZone = process.env.TZ;
    process.env.TZ = 'Pacific/Kiritimati';
    const expansion = expandRecurrence('2000-01-01', 'FREQ=DAILY', '2000-01-01', '2100-01-01');
    process.env.TZ = originalTimeZone;

    expect(expansion.iterations).toBe(RECURRENCE_ITERATION_LIMIT);
    expect(expansion.dates).toHaveLength(RECURRENCE_ITERATION_LIMIT);
    expect(expansion.dates[0]).toBe('2000-01-01');
    expect(expansion.dates.at(-1)).toBe('2005-06-22');
    expect(expansion.truncated).toBe(true);
  });
});
