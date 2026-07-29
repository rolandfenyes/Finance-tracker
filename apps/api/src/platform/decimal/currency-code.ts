const CURRENCY_CODE_PATTERN = /^[A-Z]{3}$/;

export class CurrencyCode {
  private constructor(private readonly value: string) {}

  static create(value: string): CurrencyCode {
    if (!CURRENCY_CODE_PATTERN.test(value)) {
      throw new Error('Currency code must contain exactly three uppercase ASCII letters');
    }
    return new CurrencyCode(value);
  }

  equals(other: CurrencyCode): boolean {
    return this.value === other.value;
  }

  toJSON(): string {
    return this.value;
  }

  toString(): string {
    return this.value;
  }
}
