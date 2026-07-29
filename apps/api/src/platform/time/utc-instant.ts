const UTC_INSTANT_PATTERN =
  /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,3})?Z$/;

export class UtcInstant {
  private constructor(
    private readonly value: string,
    private readonly epochMilliseconds: number,
  ) {}

  static create(value: string): UtcInstant {
    if (!UTC_INSTANT_PATTERN.test(value)) {
      throw new Error('UTC instant must be an ISO 8601 timestamp ending in Z');
    }
    const epochMilliseconds = Date.parse(value);
    if (!Number.isFinite(epochMilliseconds)) {
      throw new Error('UTC instant must be a valid timestamp');
    }

    const canonical = new Date(epochMilliseconds).toISOString();
    const inputWithoutMilliseconds = value.replace(/Z$/, '.000Z');
    const normalizedInput = value.includes('.')
      ? value.replace(/\.(\d)Z$/, '.$100Z').replace(/\.(\d{2})Z$/, '.$10Z')
      : inputWithoutMilliseconds;
    if (canonical !== normalizedInput) {
      throw new Error('UTC instant must be a valid timestamp');
    }

    return new UtcInstant(canonical, epochMilliseconds);
  }

  static fromDate(value: Date): UtcInstant {
    return UtcInstant.create(value.toISOString());
  }

  compare(other: UtcInstant): number {
    return Math.sign(this.epochMilliseconds - other.epochMilliseconds);
  }

  toDate(): Date {
    return new Date(this.epochMilliseconds);
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }
}
