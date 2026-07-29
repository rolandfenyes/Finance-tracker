import { ExactDecimal } from './exact-decimal';

export class SecurityQuantity {
  private constructor(readonly value: ExactDecimal) {}

  static create(value: string): SecurityQuantity {
    const decimal = ExactDecimal.create(value);
    if (decimal.isNegative()) {
      throw new Error('Security quantity must not be negative');
    }
    return new SecurityQuantity(decimal);
  }

  toJSON(): string {
    return this.value.toString();
  }

  toString(): string {
    return this.value.toString();
  }
}
