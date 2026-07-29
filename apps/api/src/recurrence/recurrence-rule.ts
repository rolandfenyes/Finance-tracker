import { CalendarDate } from '../platform/time/calendar-date';
import {
  recurrenceFrequencies,
  recurrenceWeekdays,
  type ParsedRecurrenceRule,
  type RecurrenceExpansion,
  type RecurrenceFrequency,
  type RecurrenceWeekday,
} from './recurrence.types';

export const RECURRENCE_ITERATION_LIMIT = 2_000;

const weekdayNumber: Readonly<Record<RecurrenceWeekday, number>> = {
  MO: 1,
  TU: 2,
  WE: 3,
  TH: 4,
  FR: 5,
  SA: 6,
  SU: 7,
};

export class InvalidRecurrenceRuleError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'InvalidRecurrenceRuleError';
  }
}

export function parseRecurrenceRule(value: string): ParsedRecurrenceRule {
  const input = value.trim();
  if (input === '') {
    return {
      frequency: null,
      interval: 1,
      byDay: [],
      byMonthDay: null,
      byMonth: null,
      count: null,
      until: null,
      canonical: '',
    };
  }

  const values = new Map<string, string>();
  for (const rawPart of input.split(';')) {
    const part = rawPart.trim();
    const separator = part.indexOf('=');
    if (separator <= 0 || separator === part.length - 1) {
      throw new InvalidRecurrenceRuleError('Every RRULE component must use KEY=VALUE');
    }
    const key = part.slice(0, separator).trim().toUpperCase();
    const component = part
      .slice(separator + 1)
      .trim()
      .toUpperCase();
    if (!['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'COUNT', 'UNTIL'].includes(key)) {
      throw new InvalidRecurrenceRuleError(`Unsupported RRULE component: ${key}`);
    }
    if (values.has(key)) {
      throw new InvalidRecurrenceRuleError(`Duplicate RRULE component: ${key}`);
    }
    values.set(key, component);
  }

  const frequencyText = values.get('FREQ');
  if (!frequencyText || !recurrenceFrequencies.includes(frequencyText as RecurrenceFrequency)) {
    throw new InvalidRecurrenceRuleError('FREQ must be DAILY, WEEKLY, MONTHLY, or YEARLY');
  }
  const frequency = frequencyText as RecurrenceFrequency;
  const interval = positiveInteger(values.get('INTERVAL') ?? '1', 'INTERVAL');
  const byDay = parseByDay(values.get('BYDAY'));
  const byMonthDay = optionalInteger(values.get('BYMONTHDAY'), 'BYMONTHDAY', 1, 31);
  const byMonth = optionalInteger(values.get('BYMONTH'), 'BYMONTH', 1, 12);
  const count = optionalInteger(values.get('COUNT'), 'COUNT', 1, 2_147_483_647);
  const until = parseUntil(values.get('UNTIL'));

  if (byDay.length > 0 && frequency !== 'WEEKLY') {
    throw new InvalidRecurrenceRuleError('BYDAY is supported only with WEEKLY');
  }
  if (byMonthDay !== null && frequency !== 'MONTHLY' && frequency !== 'YEARLY') {
    throw new InvalidRecurrenceRuleError('BYMONTHDAY is supported only with MONTHLY or YEARLY');
  }
  if (byMonth !== null && frequency !== 'YEARLY') {
    throw new InvalidRecurrenceRuleError('BYMONTH is supported only with YEARLY');
  }

  const canonical = [
    `FREQ=${frequency}`,
    ...(interval === 1 ? [] : [`INTERVAL=${interval}`]),
    ...(byDay.length === 0 ? [] : [`BYDAY=${byDay.join(',')}`]),
    ...(byMonthDay === null ? [] : [`BYMONTHDAY=${byMonthDay}`]),
    ...(byMonth === null ? [] : [`BYMONTH=${byMonth}`]),
    ...(count === null ? [] : [`COUNT=${count}`]),
    ...(until === null ? [] : [`UNTIL=${until.replaceAll('-', '')}`]),
  ].join(';');

  return { frequency, interval, byDay, byMonthDay, byMonth, count, until, canonical };
}

export function expandRecurrence(
  startsOn: string,
  rrule: string,
  rangeFrom: string,
  rangeTo: string,
): RecurrenceExpansion {
  CalendarDate.create(startsOn);
  CalendarDate.create(rangeFrom);
  CalendarDate.create(rangeTo);
  if (rangeFrom > rangeTo) {
    throw new InvalidRecurrenceRuleError('Forecast range start must not be after its end');
  }
  const rule = parseRecurrenceRule(rrule);
  if (rule.frequency === null) {
    return {
      dates: startsOn >= rangeFrom && startsOn <= rangeTo ? [startsOn] : [],
      iterations: 1,
      truncated: false,
    };
  }

  const start = dateParts(startsOn);
  const dates: string[] = [];
  let remaining = rule.count;
  let iterations = 0;
  let naturallyComplete = false;

  const emit = (candidate: string): boolean => {
    if (remaining !== null && remaining <= 0) return false;
    if (rule.until !== null && candidate > rule.until) return false;
    if (candidate >= rangeFrom && candidate <= rangeTo) dates.push(candidate);
    if (remaining !== null) remaining -= 1;
    return true;
  };

  if (rule.frequency === 'DAILY') {
    while (iterations < RECURRENCE_ITERATION_LIMIT) {
      const candidate = addDays(startsOn, iterations * rule.interval);
      iterations += 1;
      if (!emit(candidate)) {
        naturallyComplete = true;
        break;
      }
      if (candidate > rangeTo && rule.until === null && remaining === null) {
        naturallyComplete = true;
        break;
      }
    }
  } else if (rule.frequency === 'WEEKLY') {
    const selected =
      rule.byDay.length === 0 ? [recurrenceWeekdays[weekdayIndex(startsOn) - 1]!] : rule.byDay;
    const anchor = addDays(startsOn, -(weekdayIndex(startsOn) - 1));
    while (iterations < RECURRENCE_ITERATION_LIMIT) {
      const weekStart = addDays(anchor, iterations * rule.interval * 7);
      iterations += 1;
      let stop = false;
      for (const weekday of selected) {
        const candidate = addDays(weekStart, weekdayNumber[weekday] - 1);
        if (candidate < startsOn) continue;
        if (!emit(candidate)) {
          stop = true;
          break;
        }
      }
      if (stop) {
        naturallyComplete = true;
        break;
      }
      if (weekStart > rangeTo && rule.until === null && remaining === null) {
        naturallyComplete = true;
        break;
      }
    }
  } else if (rule.frequency === 'MONTHLY') {
    const baseDay = rule.byMonthDay ?? start.day;
    while (iterations < RECURRENCE_ITERATION_LIMIT) {
      const target = addMonths(start.year, start.month, iterations * rule.interval);
      const candidate = dateText(target.year, target.month, Math.min(baseDay, daysInMonth(target)));
      iterations += 1;
      if (candidate >= startsOn && !emit(candidate)) {
        naturallyComplete = true;
        break;
      }
      if (candidate > rangeTo && rule.until === null && remaining === null) {
        naturallyComplete = true;
        break;
      }
    }
  } else {
    const baseMonth = rule.byMonth ?? start.month;
    const baseDay = rule.byMonthDay ?? start.day;
    while (iterations < RECURRENCE_ITERATION_LIMIT) {
      const year = start.year + iterations * rule.interval;
      const candidate = dateText(
        year,
        baseMonth,
        Math.min(baseDay, daysInMonth({ year, month: baseMonth })),
      );
      iterations += 1;
      if (candidate >= startsOn && !emit(candidate)) {
        naturallyComplete = true;
        break;
      }
      if (candidate > rangeTo && rule.until === null && remaining === null) {
        naturallyComplete = true;
        break;
      }
    }
  }

  dates.sort();
  return {
    dates,
    iterations,
    truncated: iterations === RECURRENCE_ITERATION_LIMIT && !naturallyComplete,
  };
}

function parseByDay(value: string | undefined): RecurrenceWeekday[] {
  if (value === undefined) return [];
  const days = value.split(',').map((item) => item.trim());
  if (
    days.length === 0 ||
    days.some((day) => !recurrenceWeekdays.includes(day as RecurrenceWeekday))
  ) {
    throw new InvalidRecurrenceRuleError('BYDAY accepts MO,TU,WE,TH,FR,SA,SU only');
  }
  if (new Set(days).size !== days.length) {
    throw new InvalidRecurrenceRuleError('BYDAY must not contain duplicates');
  }
  return days as RecurrenceWeekday[];
}

function parseUntil(value: string | undefined): string | null {
  if (value === undefined) return null;
  const match = /^(\d{4})(\d{2})(\d{2})(?:T\d{6}Z)?$/.exec(value);
  if (!match) {
    throw new InvalidRecurrenceRuleError('UNTIL must use YYYYMMDD or YYYYMMDDThhmmssZ');
  }
  const date = `${match[1]}-${match[2]}-${match[3]}`;
  try {
    CalendarDate.create(date);
  } catch {
    throw new InvalidRecurrenceRuleError('UNTIL must contain a valid Gregorian date');
  }
  return date;
}

function positiveInteger(value: string, name: string): number {
  if (!/^[1-9]\d*$/.test(value)) {
    throw new InvalidRecurrenceRuleError(`${name} must be a positive integer`);
  }
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed > 2_147_483_647) {
    throw new InvalidRecurrenceRuleError(`${name} is outside the supported integer range`);
  }
  return parsed;
}

function optionalInteger(
  value: string | undefined,
  name: string,
  minimum: number,
  maximum: number,
): number | null {
  if (value === undefined) return null;
  const parsed = positiveInteger(value, name);
  if (parsed < minimum || parsed > maximum) {
    throw new InvalidRecurrenceRuleError(`${name} must be between ${minimum} and ${maximum}`);
  }
  return parsed;
}

function dateParts(value: string): { year: number; month: number; day: number } {
  const [year, month, day] = value.split('-').map(Number);
  return { year: year!, month: month!, day: day! };
}

function dateText(year: number, month: number, day: number): string {
  return `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day
    .toString()
    .padStart(2, '0')}`;
}

function addDays(value: string, days: number): string {
  const { year, month, day } = dateParts(value);
  const date = new Date(Date.UTC(year, month - 1, day));
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

function addMonths(year: number, month: number, offset: number): { year: number; month: number } {
  const index = year * 12 + (month - 1) + offset;
  return { year: Math.floor(index / 12), month: (index % 12) + 1 };
}

function daysInMonth(value: { year: number; month: number }): number {
  return new Date(Date.UTC(value.year, value.month, 0)).getUTCDate();
}

function weekdayIndex(value: string): number {
  const { year, month, day } = dateParts(value);
  const sundayFirst = new Date(Date.UTC(year, month - 1, day)).getUTCDay();
  return sundayFirst === 0 ? 7 : sundayFirst;
}
