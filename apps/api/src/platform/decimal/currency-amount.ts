import { CurrencyCode } from './currency-code';
import { ExactDecimal } from './exact-decimal';
import type { RoundingPolicy } from './rounding-policy';

export interface CurrencyAmountJson {
  amount: string;
  currency: string;
}

export class CurrencyAmount {
  private constructor(
    readonly amount: ExactDecimal,
    readonly currency: CurrencyCode,
  ) {}

  static create(amount: string, currency: string): CurrencyAmount {
    return new CurrencyAmount(ExactDecimal.create(amount), CurrencyCode.create(currency));
  }

  add(other: CurrencyAmount): CurrencyAmount {
    this.assertSameCurrency(other);
    return new CurrencyAmount(this.amount.add(other.amount), this.currency);
  }

  subtract(other: CurrencyAmount): CurrencyAmount {
    this.assertSameCurrency(other);
    return new CurrencyAmount(this.amount.subtract(other.amount), this.currency);
  }

  round(policy: RoundingPolicy): CurrencyAmount {
    return new CurrencyAmount(this.amount.round(policy), this.currency);
  }

  toJSON(): CurrencyAmountJson {
    return {
      amount: this.amount.toString(),
      currency: this.currency.toString(),
    };
  }

  private assertSameCurrency(other: CurrencyAmount): void {
    if (!this.currency.equals(other.currency)) {
      throw new Error('Currency amounts must use the same currency');
    }
  }
}
