import { UtcInstant } from './utc-instant';

export const CLOCK = Symbol('CLOCK');

export interface Clock {
  now(): UtcInstant;
}

export class SystemClock implements Clock {
  now(): UtcInstant {
    return UtcInstant.fromDate(new Date());
  }
}

export class FixedClock implements Clock {
  constructor(private current: UtcInstant) {}

  now(): UtcInstant {
    return this.current;
  }

  set(instant: UtcInstant): void {
    this.current = instant;
  }
}
