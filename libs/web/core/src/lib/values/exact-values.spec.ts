import { ExactDecimalAdapter } from './exact-decimal.adapter';
import {
  calendarDate,
  currencyCode,
  cursorValue,
  formatCalendarDate,
  formatMoney,
  formatPercent,
  opaqueCursor,
  recurrenceRule,
  resolveLocale,
  utcInstant,
} from './presentation-values';

describe('exact and presentation value boundaries', () => {
  const decimals = new ExactDecimalAdapter();

  it('parses, compares, calculates, and rounds without number coercion', () => {
    const left = decimals.parse('9007199254740993.01');
    const right = decimals.parse('0.09');
    expect(decimals.add(left, right)).toBe('9007199254740993.1');
    expect(decimals.subtract(left, right)).toBe('9007199254740992.92');
    expect(decimals.multiply(decimals.parse('0.1'), decimals.parse('0.2'))).toBe('0.02');
    expect(decimals.divide(decimals.parse('1'), decimals.parse('8'))).toBe('0.125');
    expect(decimals.compare(left, right)).toBe(1);
    expect(decimals.round(decimals.parse('1.005'), 2)).toBe('1.01');
    expect(() => decimals.parse('1e6')).toThrow();
  });

  it('formats money and percentages directly from exact strings', () => {
    const exact = decimals.parse('9007199254740993.01');
    expect(formatMoney(exact, currencyCode('HUF'), 'hu')).toBe(
      '9\u00a0007\u00a0199\u00a0254\u00a0740\u00a0993,01 HUF',
    );
    expect(formatPercent(decimals.parse('12.50'), 'es')).toBe('12,5%');
  });

  it('round-trips calendar dates without timezone shifts and validates UTC instants', () => {
    const date = calendarDate('2026-03-29');
    expect(date).toBe('2026-03-29');
    expect(formatCalendarDate(date, 'en')).toBe('03/29/2026');
    expect(formatCalendarDate(date, 'hu')).toBe('2026.03.29.');
    expect(utcInstant('2026-03-29T00:30:00Z')).toBe('2026-03-29T00:30:00.000Z');
    expect(() => calendarDate('2026-02-30')).toThrow();
  });

  it('keeps cursors opaque, RRULEs unmodified, and unsupported locales on English fallback', () => {
    const cursor = opaqueCursor('opaque+/cursor==');
    expect(cursorValue(cursor)).toBe('opaque+/cursor==');
    expect(recurrenceRule('FREQ=MONTHLY;BYMONTHDAY=1')).toBe('FREQ=MONTHLY;BYMONTHDAY=1');
    expect(resolveLocale('es')).toBe('es');
    expect(resolveLocale('de')).toBe('en');
  });
});
