import { buildSupportedRRule, describeSupportedRRule } from './rrule';

describe('supported recurrence presentation', () => {
  it('builds only the documented structured subset', () => {
    expect(
      buildSupportedRRule({ frequency: 'WEEKLY', interval: '2', byDay: ['MO', 'FR'], count: '5' }),
    ).toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR;COUNT=5');
    expect(
      buildSupportedRRule({
        frequency: 'YEARLY',
        interval: '1',
        byMonthDay: '29',
        byMonth: '2',
        until: '2032-02-29',
      }),
    ).toBe('FREQ=YEARLY;INTERVAL=1;BYMONTHDAY=29;BYMONTH=2;UNTIL=2032-02-29');
    expect(buildSupportedRRule({ frequency: 'ONCE', interval: '1' })).toBe('');
  });

  it('presents canonical rules without expanding or calculating dates', () => {
    expect(describeSupportedRRule('FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=31')).toBe(
      'FREQ=MONTHLY · INTERVAL=1 · BYMONTHDAY=31',
    );
    expect(describeSupportedRRule('')).toBe('ONCE');
  });
});
