const CALENDAR_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export class CalendarDate {
  private constructor(private readonly value: string) {}

  static create(value: string): CalendarDate {
    const match = CALENDAR_DATE_PATTERN.exec(value);
    if (!match) {
      throw new Error('Calendar date must use YYYY-MM-DD');
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (
      date.getUTCFullYear() !== year ||
      date.getUTCMonth() !== month - 1 ||
      date.getUTCDate() !== day
    ) {
      throw new Error('Calendar date must be a valid Gregorian date');
    }

    return new CalendarDate(value);
  }

  compare(other: CalendarDate): number {
    return this.value.localeCompare(other.value);
  }

  nextDay(): CalendarDate {
    const next = new Date(`${this.value}T00:00:00.000Z`);
    next.setUTCDate(next.getUTCDate() + 1);
    return CalendarDate.create(next.toISOString().slice(0, 10));
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }
}
