import { CurrencyCode } from './currency-code';
import { CurrencyAmount } from './currency-amount';
import { ExactDecimal } from './exact-decimal';

export interface FxRateJson {
  rate: string;
  sourceCurrency: string;
  targetCurrency: string;
}

export class FxRate {
  private constructor(
    readonly rate: ExactDecimal,
    readonly sourceCurrency: CurrencyCode,
    readonly targetCurrency: CurrencyCode,
  ) {}

  static create(rate: string, sourceCurrency: string, targetCurrency: string): FxRate {
    const exactRate = ExactDecimal.create(rate);
    if (!exactRate.isPositive()) {
      throw new Error('FX rate must be greater than zero');
    }

    return new FxRate(
      exactRate,
      CurrencyCode.create(sourceCurrency),
      CurrencyCode.create(targetCurrency),
    );
  }

  convert(amount: CurrencyAmount): CurrencyAmount {
    if (!amount.currency.equals(this.sourceCurrency)) {
      throw new Error('FX source currency does not match the amount currency');
    }

    return CurrencyAmount.create(
      amount.amount.multiply(this.rate).toString(),
      this.targetCurrency.toString(),
    );
  }

  toJSON(): FxRateJson {
    return {
      rate: this.rate.toString(),
      sourceCurrency: this.sourceCurrency.toString(),
      targetCurrency: this.targetCurrency.toString(),
    };
  }
}
