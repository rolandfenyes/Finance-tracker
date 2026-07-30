import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';

export const LOAN_ESTIMATE_VERSION = 'standard_nominal_monthly_annuity_v1' as const;
const INTERNAL = RoundingPolicy.create(36, 'HALF_EVEN');

export interface LoanProjectionInput {
  principal: string;
  nominalAnnualRate: string;
  termMonths: number;
  startsOn: string;
  paymentDay: number | null;
  extraPaymentScenario: string;
  insuranceMonthly: string;
  currencyScale: number;
  roundingMode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN';
}

export interface ProjectedLoanPayment {
  sequence: number;
  dueOn: string;
  principalComponent: string;
  interestComponent: string;
  feeComponent: string;
  totalAmount: string;
  remainingPrincipal: string;
  status: 'projected';
}

export function standardMonthlyAnnuity(
  principalText: string,
  nominalAnnualRateText: string,
  termMonths: number,
  scale: number,
  roundingMode: LoanProjectionInput['roundingMode'],
): string {
  const principal = ExactDecimal.create(principalText);
  const annualRate = ExactDecimal.create(nominalAnnualRateText);
  if (!principal.isPositive()) throw new Error('Principal must be positive');
  if (annualRate.isNegative()) throw new Error('Nominal annual rate cannot be negative');
  if (!Number.isSafeInteger(termMonths) || termMonths <= 0) {
    throw new Error('Term months must be a positive integer');
  }
  const output = RoundingPolicy.create(scale, roundingMode);
  if (annualRate.isZero()) {
    return principal
      .divide(ExactDecimal.create(String(termMonths)), INTERNAL)
      .round(output)
      .toString();
  }
  const monthlyRate = annualRate
    .divide(ExactDecimal.create('100'), INTERNAL)
    .divide(ExactDecimal.create('12'), INTERNAL);
  const factor = integerPower(ExactDecimal.create('1').add(monthlyRate), termMonths);
  return principal
    .multiply(monthlyRate)
    .multiply(factor)
    .divide(factor.subtract(ExactDecimal.create('1')), INTERNAL)
    .round(output)
    .toString();
}

export function projectLoanSchedule(input: LoanProjectionInput): ProjectedLoanPayment[] {
  const rounding = RoundingPolicy.create(input.currencyScale, input.roundingMode);
  const basePayment = ExactDecimal.create(
    standardMonthlyAnnuity(
      input.principal,
      input.nominalAnnualRate,
      input.termMonths,
      input.currencyScale,
      input.roundingMode,
    ),
  );
  const monthlyRate = ExactDecimal.create(input.nominalAnnualRate)
    .divide(ExactDecimal.create('100'), INTERNAL)
    .divide(ExactDecimal.create('12'), INTERNAL);
  const extra = ExactDecimal.create(input.extraPaymentScenario);
  const fee = ExactDecimal.create(input.insuranceMonthly).round(rounding);
  let remaining = ExactDecimal.create(input.principal);
  const projected: ProjectedLoanPayment[] = [];
  for (let sequence = 1; sequence <= input.termMonths && remaining.isPositive(); sequence += 1) {
    const interest = remaining.multiply(monthlyRate).round(rounding);
    const proposedPrincipal = basePayment.subtract(interest).add(extra);
    const principal =
      proposedPrincipal.compare(remaining) > 0 ? remaining : proposedPrincipal.round(rounding);
    if (!principal.isPositive()) break;
    remaining = remaining.subtract(principal);
    projected.push({
      sequence,
      dueOn: dueDate(input.startsOn, input.paymentDay, sequence),
      principalComponent: principal.toString(),
      interestComponent: interest.toString(),
      feeComponent: fee.toString(),
      totalAmount: principal.add(interest).add(fee).toString(),
      remainingPrincipal: remaining.toString(),
      status: 'projected',
    });
  }
  return projected;
}

export function derivedOutstanding(
  principal: string,
  payments: readonly { loanPrincipalComponent: string; reversedByJournalEntryId: string | null }[],
): string {
  return payments
    .filter(({ reversedByJournalEntryId }) => reversedByJournalEntryId === null)
    .reduce(
      (balance, payment) => balance.subtract(ExactDecimal.create(payment.loanPrincipalComponent)),
      ExactDecimal.create(principal),
    )
    .toString();
}

function integerPower(base: ExactDecimal, exponent: number): ExactDecimal {
  let result = ExactDecimal.create('1');
  for (let index = 0; index < exponent; index += 1) result = result.multiply(base);
  return result;
}

function dueDate(startsOn: string, requestedDay: number | null, sequence: number): string {
  const year = Number(startsOn.slice(0, 4));
  const month = Number(startsOn.slice(5, 7));
  const startDay = Number(startsOn.slice(8, 10));
  const day = requestedDay ?? startDay;
  const target = new Date(Date.UTC(year, month - 1 + sequence, 1));
  const last = new Date(
    Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0),
  ).getUTCDate();
  return [
    String(target.getUTCFullYear()).padStart(4, '0'),
    String(target.getUTCMonth() + 1).padStart(2, '0'),
    String(Math.min(day, last)).padStart(2, '0'),
  ].join('-');
}
