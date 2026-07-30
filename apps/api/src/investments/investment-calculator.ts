import Decimal from 'decimal.js';
import type { RoundingMode } from '../platform/decimal/rounding-policy';

const FinancialDecimal = Decimal.clone({
  precision: 80,
  rounding: Decimal.ROUND_HALF_EVEN,
  toExpNeg: -80,
  toExpPos: 80,
});

export const investmentFrequencies = ['daily', 'weekly', 'monthly', 'annual'] as const;
export type InvestmentFrequency = (typeof investmentFrequencies)[number];

const periods: Readonly<Record<InvestmentFrequency, string>> = {
  daily: '365',
  weekly: '52',
  monthly: '12',
  annual: '1',
};

export function compoundScenario(input: {
  principal: string;
  nominalAnnualRate: string;
  frequency: InvestmentFrequency;
  years: string;
  scale: number;
  roundingMode: RoundingMode;
}): string {
  const principal = decimal(input.principal);
  const rate = decimal(input.nominalAnnualRate);
  const years = decimal(input.years);
  if (principal.isNegative() || rate.isNegative() || years.isNegative()) {
    throw new Error('Compound scenario inputs cannot be negative');
  }
  if (principal.isZero() || years.isZero() || rate.isZero()) {
    return principal.toDecimalPlaces(input.scale, rounding(input.roundingMode)).toFixed();
  }
  const m = decimal(periods[input.frequency]);
  const base = decimal('1').add(rate.div('100').div(m));
  return principal
    .mul(base.pow(years.mul(m)))
    .toDecimalPlaces(input.scale, rounding(input.roundingMode))
    .toFixed();
}

export function projectScenarioWithContributions(input: {
  principal: string;
  nominalAnnualRate: string;
  frequency: InvestmentFrequency;
  from: string;
  to: string;
  contributions: ReadonlyArray<{ occurredOn: string; amount: string }>;
  scale: number;
  roundingMode: RoundingMode;
}): { value: string; contributionTotal: string; scenarioGain: string } {
  const years = yearFraction(input.from, input.to);
  const principalFuture = decimal(
    compoundScenario({
      principal: input.principal,
      nominalAnnualRate: input.nominalAnnualRate,
      frequency: input.frequency,
      years,
      scale: 36,
      roundingMode: 'HALF_EVEN',
    }),
  );
  let future = principalFuture;
  let contributionTotal = decimal('0');
  for (const contribution of input.contributions) {
    contributionTotal = contributionTotal.add(contribution.amount);
    future = future.add(
      compoundScenario({
        principal: contribution.amount,
        nominalAnnualRate: input.nominalAnnualRate,
        frequency: input.frequency,
        years: yearFraction(contribution.occurredOn, input.to),
        scale: 36,
        roundingMode: 'HALF_EVEN',
      }),
    );
  }
  const gain = Decimal.max(future.sub(decimal(input.principal)).sub(contributionTotal), 0);
  const mode = rounding(input.roundingMode);
  return {
    value: future.toDecimalPlaces(input.scale, mode).toFixed(),
    contributionTotal: contributionTotal.toDecimalPlaces(input.scale, mode).toFixed(),
    scenarioGain: gain.toDecimalPlaces(input.scale, mode).toFixed(),
  };
}

export function deriveInvestmentBalance(
  movements: ReadonlyArray<{
    direction: 'deposit' | 'withdrawal';
    investmentAmount: string;
    reversedByJournalEntryId: string | null;
  }>,
): string {
  return movements
    .filter(({ reversedByJournalEntryId }) => reversedByJournalEntryId === null)
    .reduce(
      (total, movement) =>
        movement.direction === 'deposit'
          ? total.add(movement.investmentAmount)
          : total.sub(movement.investmentAmount),
      decimal('0'),
    )
    .toFixed();
}

function yearFraction(from: string, to: string): string {
  const fromMs = Date.parse(`${from}T00:00:00.000Z`);
  const toMs = Date.parse(`${to}T00:00:00.000Z`);
  const days = Math.max(0, Math.round((toMs - fromMs) / 86_400_000));
  return decimal(String(days)).div('365.25').toFixed();
}

function decimal(value: string): Decimal {
  return new FinancialDecimal(value);
}

function rounding(mode: RoundingMode): Decimal.Rounding {
  return {
    DOWN: Decimal.ROUND_DOWN,
    UP: Decimal.ROUND_UP,
    HALF_UP: Decimal.ROUND_HALF_UP,
    HALF_EVEN: Decimal.ROUND_HALF_EVEN,
  }[mode];
}
