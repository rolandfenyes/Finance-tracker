import fc from 'fast-check';
import { FixedClock } from './clock';
import { CalendarDate } from './calendar-date';
import { DateRange } from './date-range';
import { UserTimeZone } from './user-time-zone';
import { UtcInstant } from './utc-instant';

describe('UTC clock and explicit user-time-zone boundary', () => {
  it('keeps storage instants in canonical UTC and allows deterministic clocks', () => {
    const instant = UtcInstant.create('2026-07-29T08:15:00Z');
    const clock = new FixedClock(instant);

    expect(clock.now().toString()).toBe('2026-07-29T08:15:00.000Z');
    clock.set(UtcInstant.create('2026-07-29T08:15:00.123Z'));
    expect(clock.now().toJSON()).toBe('2026-07-29T08:15:00.123Z');
  });

  it('maps the same instant to the correct user-local date at midnight boundaries', () => {
    const utc = UtcInstant.create('2026-03-28T23:30:00Z');

    expect(UserTimeZone.create('UTC').calendarDateAt(utc).toString()).toBe('2026-03-28');
    expect(UserTimeZone.create('Europe/Budapest').calendarDateAt(utc).toString()).toBe(
      '2026-03-29',
    );
  });

  it('keeps Budapest dates stable across the daylight-saving transition', () => {
    const budapest = UserTimeZone.create('Europe/Budapest');

    expect(budapest.calendarDateAt(UtcInstant.create('2026-03-29T00:59:59.999Z')).toString()).toBe(
      '2026-03-29',
    );
    expect(budapest.calendarDateAt(UtcInstant.create('2026-03-29T01:00:00.000Z')).toString()).toBe(
      '2026-03-29',
    );
    expect(budapest.utcBoundsFor(DateRange.create('2026-03-29', '2026-03-29'))).toEqual({
      fromInclusive: UtcInstant.create('2026-03-28T23:00:00.000Z'),
      toExclusive: UtcInstant.create('2026-03-29T22:00:00.000Z'),
    });
  });

  it('round-trips valid UTC milliseconds as canonical timestamps', () => {
    fc.assert(
      fc.property(
        fc.integer({
          min: Date.UTC(2000, 0, 1),
          max: Date.UTC(2099, 11, 31, 23, 59, 59, 999),
        }),
        (epochMilliseconds) => {
          const iso = new Date(epochMilliseconds).toISOString();
          expect(UtcInstant.create(iso).toString()).toBe(iso);
        },
      ),
    );
  });

  it('validates calendar dates and inclusive date ranges', () => {
    const range = DateRange.create('2024-02-29', '2024-03-01');

    expect(range.contains(CalendarDate.create('2024-02-29'))).toBe(true);
    expect(range.contains(CalendarDate.create('2024-03-01'))).toBe(true);
    expect(() => CalendarDate.create('2025-02-29')).toThrow();
    expect(() => DateRange.create('2026-02-01', '2026-01-31')).toThrow();
    expect(() => UserTimeZone.create('UTC+02:00')).toThrow();
    expect(() => UtcInstant.create('2026-07-29T10:00:00+02:00')).toThrow();
  });
});
