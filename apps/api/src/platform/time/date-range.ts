import { CalendarDate } from './calendar-date';

export interface DateRangeJson {
  from: string;
  to: string;
}

export class DateRange {
  private constructor(
    readonly from: CalendarDate,
    readonly to: CalendarDate,
  ) {}

  static create(from: string, to: string): DateRange {
    const start = CalendarDate.create(from);
    const end = CalendarDate.create(to);
    if (start.compare(end) > 0) {
      throw new Error('Date range start must not be after its end');
    }
    return new DateRange(start, end);
  }

  contains(date: CalendarDate): boolean {
    return this.from.compare(date) <= 0 && this.to.compare(date) >= 0;
  }

  toJSON(): DateRangeJson {
    return { from: this.from.toString(), to: this.to.toString() };
  }
}
