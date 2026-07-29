import { CalendarDate } from './calendar-date';
import type { DateRange } from './date-range';
import { UtcInstant } from './utc-instant';

export interface UtcDateBounds {
  fromInclusive: UtcInstant;
  toExclusive: UtcInstant;
}

export class UserTimeZone {
  private constructor(private readonly value: string) {}

  static create(value: string): UserTimeZone {
    try {
      const canonical = new Intl.DateTimeFormat('en-US', { timeZone: value }).resolvedOptions()
        .timeZone;
      return new UserTimeZone(canonical);
    } catch {
      throw new Error('User time zone must be a valid IANA time zone');
    }
  }

  calendarDateAt(instant: UtcInstant): CalendarDate {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: this.value,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(instant.toDate());
    const part = (type: Intl.DateTimeFormatPartTypes): string => {
      const value = parts.find((item) => item.type === type)?.value;
      if (!value) {
        throw new Error(`Time-zone formatter omitted ${type}`);
      }
      return value;
    };

    return CalendarDate.create(`${part('year')}-${part('month')}-${part('day')}`);
  }

  utcBoundsFor(range: DateRange): UtcDateBounds {
    return {
      fromInclusive: this.firstInstantOf(range.from),
      toExclusive: this.firstInstantOf(range.to.nextDay()),
    };
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }

  private firstInstantOf(date: CalendarDate): UtcInstant {
    const utcMidnight = Date.parse(`${date.toString()}T00:00:00.000Z`);
    let lower = utcMidnight - 36 * 60 * 60 * 1_000;
    let upper = utcMidnight + 36 * 60 * 60 * 1_000;

    while (lower < upper) {
      const midpoint = lower + Math.floor((upper - lower) / 2);
      const candidateDate = this.calendarDateAt(UtcInstant.fromDate(new Date(midpoint)));
      if (candidateDate.compare(date) < 0) {
        lower = midpoint + 1;
      } else {
        upper = midpoint;
      }
    }

    return UtcInstant.fromDate(new Date(lower));
  }
}
