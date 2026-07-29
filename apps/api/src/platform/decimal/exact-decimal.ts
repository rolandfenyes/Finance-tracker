import Decimal from 'decimal.js';
import type { RoundingPolicy } from './rounding-policy';

const DECIMAL_PATTERN = /^-?(?:0|[1-9]\d*)(?:\.\d+)?$/;
const MAX_INPUT_DIGITS = 1_000;
const FinancialDecimal = Decimal.clone({
  precision: 4_096,
  rounding: Decimal.ROUND_HALF_EVEN,
  toExpNeg: -4_096,
  toExpPos: 4_096,
});

export class ExactDecimal {
  private constructor(private readonly value: Decimal) {
    if (value.precision() > MAX_INPUT_DIGITS) {
      throw new Error(`Decimal result must not exceed ${MAX_INPUT_DIGITS} significant digits`);
    }
  }

  static create(value: string): ExactDecimal {
    if (!DECIMAL_PATTERN.test(value)) {
      throw new Error('Decimal value must be a finite base-10 string');
    }
    const digitCount = value.replace(/[-.]/g, '').length;
    if (digitCount > MAX_INPUT_DIGITS) {
      throw new Error(`Decimal value must not exceed ${MAX_INPUT_DIGITS} digits`);
    }
    return new ExactDecimal(new FinancialDecimal(value));
  }

  add(other: ExactDecimal): ExactDecimal {
    return new ExactDecimal(this.value.add(other.value));
  }

  subtract(other: ExactDecimal): ExactDecimal {
    return new ExactDecimal(this.value.sub(other.value));
  }

  multiply(other: ExactDecimal): ExactDecimal {
    return new ExactDecimal(this.value.mul(other.value));
  }

  divide(other: ExactDecimal, policy: RoundingPolicy): ExactDecimal {
    if (other.isZero()) {
      throw new Error('Cannot divide by zero');
    }
    return new ExactDecimal(
      this.value.div(other.value).toDecimalPlaces(policy.scale, policy.decimalMode),
    );
  }

  round(policy: RoundingPolicy): ExactDecimal {
    return new ExactDecimal(this.value.toDecimalPlaces(policy.scale, policy.decimalMode));
  }

  compare(other: ExactDecimal): number {
    return this.value.comparedTo(other.value);
  }

  isNegative(): boolean {
    return this.value.isNegative() && !this.value.isZero();
  }

  isPositive(): boolean {
    return this.value.isPositive() && !this.value.isZero();
  }

  isZero(): boolean {
    return this.value.isZero();
  }

  equals(other: ExactDecimal): boolean {
    return this.value.equals(other.value);
  }

  toJSON(): string {
    return this.toString();
  }

  toString(): string {
    return this.value.isZero() ? '0' : this.value.toFixed();
  }
}
