import { ExactDecimal } from './exact-decimal';

export class Percentage {
  private constructor(readonly value: ExactDecimal) {}

  static create(value: string): Percentage {
    const decimal = ExactDecimal.create(value);
    if (decimal.isNegative()) {
      throw new Error('Percentage must not be negative');
    }
    return new Percentage(decimal);
  }

  toJSON(): string {
    return this.value.toString();
  }

  toString(): string {
    return this.value.toString();
  }
}
