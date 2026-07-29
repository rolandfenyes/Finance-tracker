import fc from 'fast-check';
import { CurrencyAmount } from './currency-amount';
import { CurrencyCode } from './currency-code';
import { ExactDecimal } from './exact-decimal';
import { FxRate } from './fx-rate';
import { Percentage } from './percentage';
import { RoundingPolicy } from './rounding-policy';
import { SecurityQuantity } from './security-quantity';

const decimalString = fc
  .record({
    coefficient: fc.bigInt({ min: -(10n ** 30n), max: 10n ** 30n }),
    scale: fc.integer({ min: 0, max: 18 }),
  })
  .map(({ coefficient, scale }) => {
    const sign = coefficient < 0n ? '-' : '';
    const digits = (coefficient < 0n ? -coefficient : coefficient).toString();
    if (scale === 0) {
      return `${sign}${digits}`;
    }
    const padded = digits.padStart(scale + 1, '0');
    return `${sign}${padded.slice(0, -scale)}.${padded.slice(-scale)}`;
  });

describe('exact financial decimal primitives', () => {
  it('round-trips arbitrary exact decimal strings through JSON without number coercion', () => {
    fc.assert(
      fc.property(decimalString, (input) => {
        const decimal = ExactDecimal.create(input);
        const serialized: unknown = JSON.parse(JSON.stringify(decimal));

        expect(typeof serialized).toBe('string');
        expect(ExactDecimal.create(serialized as string).equals(decimal)).toBe(true);
      }),
      { numRuns: 1_000 },
    );
  });

  it('uses explicit and deterministic rounding policies', () => {
    const halfUp = RoundingPolicy.create(2, 'HALF_UP');
    const halfEven = RoundingPolicy.create(2, 'HALF_EVEN');

    expect(ExactDecimal.create('1.225').round(halfUp).toString()).toBe('1.23');
    expect(ExactDecimal.create('1.225').round(halfEven).toString()).toBe('1.22');
    expect(ExactDecimal.create('-1.225').round(halfUp).toString()).toBe('-1.23');
    expect(ExactDecimal.create('-1.225').round(halfEven).toString()).toBe('-1.22');
    expect(ExactDecimal.create('1').divide(ExactDecimal.create('3'), halfEven).toString()).toBe(
      '0.33',
    );

    fc.assert(
      fc.property(fc.bigInt({ min: 0n, max: 1_000_000n }), (whole) => {
        const tie = ExactDecimal.create(`${whole}.005`);
        expect(tie.round(halfUp).toString()).toBe(`${whole}.01`);
        expect(tie.round(halfEven).toString()).toBe(whole.toString());
      }),
    );
  });

  it('does not silently round arithmetic at the decimal library default precision', () => {
    expect(
      ExactDecimal.create('999999999999999999999999999999')
        .multiply(ExactDecimal.create('9'))
        .toString(),
    ).toBe('8999999999999999999999999999991');
  });

  it('keeps amount, rate, percentage, and security quantity as decimal strings', () => {
    const amount = CurrencyAmount.create('9007199254740993.123456789', 'HUF');
    const rate = FxRate.create('0.00274123456789', 'HUF', 'EUR');
    const converted = rate.convert(amount);

    expect(amount.toJSON()).toEqual({
      amount: '9007199254740993.123456789',
      currency: 'HUF',
    });
    expect(rate.toJSON()).toEqual({
      rate: '0.00274123456789',
      sourceCurrency: 'HUF',
      targetCurrency: 'EUR',
    });
    expect(typeof converted.toJSON().amount).toBe('string');
    expect(Percentage.create('120').toJSON()).toBe('120');
    expect(SecurityQuantity.create('0.00000001').toJSON()).toBe('0.00000001');
  });

  it('rejects non-string decimal syntax and invalid financial values', () => {
    for (const invalid of ['NaN', 'Infinity', '1e3', '+1', '01', '', ' 1']) {
      expect(() => ExactDecimal.create(invalid)).toThrow();
    }
    expect(() => CurrencyCode.create('usd')).toThrow();
    expect(() => CurrencyCode.create('EURO')).toThrow();
    expect(() => FxRate.create('0', 'EUR', 'HUF')).toThrow();
    expect(() => FxRate.create('-1', 'EUR', 'HUF')).toThrow();
    expect(() => Percentage.create('-0.01')).toThrow();
    expect(() => SecurityQuantity.create('-0.0001')).toThrow();

    fc.assert(
      fc.property(
        fc.string().filter((value) => !/^[A-Z]{3}$/.test(value)),
        (invalidCurrency) => {
          expect(() => CurrencyCode.create(invalidCurrency)).toThrow();
        },
      ),
    );
    fc.assert(
      fc.property(fc.bigInt({ min: 1n, max: 10n ** 30n }), (magnitude) => {
        expect(() => Percentage.create(`-${magnitude}`)).toThrow();
        expect(() => SecurityQuantity.create(`-${magnitude}`)).toThrow();
      }),
    );
  });

  it('prevents arithmetic across currencies', () => {
    expect(() =>
      CurrencyAmount.create('10', 'EUR').add(CurrencyAmount.create('10', 'HUF')),
    ).toThrow('same currency');
  });
});
