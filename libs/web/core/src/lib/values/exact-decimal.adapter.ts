import { Injectable } from '@angular/core';
import Decimal from 'decimal.js';

export type ExactDecimal = string & { readonly __exactDecimal: unique symbol };

@Injectable({ providedIn: 'root' })
export class ExactDecimalAdapter {
  parse(value: string): ExactDecimal {
    const normalized = value.trim();
    if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(normalized)) {
      throw new Error('Exact decimal must use plain decimal notation');
    }
    return new Decimal(normalized).toFixed() as ExactDecimal;
  }

  compare(left: ExactDecimal, right: ExactDecimal): -1 | 0 | 1 {
    const comparison = new Decimal(left).comparedTo(new Decimal(right));
    return comparison < 0 ? -1 : comparison > 0 ? 1 : 0;
  }

  add(left: ExactDecimal, right: ExactDecimal): ExactDecimal {
    return new Decimal(left).plus(right).toFixed() as ExactDecimal;
  }

  subtract(left: ExactDecimal, right: ExactDecimal): ExactDecimal {
    return new Decimal(left).minus(right).toFixed() as ExactDecimal;
  }

  multiply(left: ExactDecimal, right: ExactDecimal): ExactDecimal {
    return new Decimal(left).times(right).toFixed() as ExactDecimal;
  }

  divide(left: ExactDecimal, right: ExactDecimal): ExactDecimal {
    return new Decimal(left).dividedBy(right).toFixed() as ExactDecimal;
  }

  round(value: ExactDecimal, decimalPlaces: number): ExactDecimal {
    if (!Number.isSafeInteger(decimalPlaces) || decimalPlaces < 0) {
      throw new Error('Decimal places must be a non-negative safe integer');
    }
    return new Decimal(value).toDecimalPlaces(decimalPlaces).toFixed() as ExactDecimal;
  }

  toString(value: ExactDecimal): string {
    return value;
  }
}
