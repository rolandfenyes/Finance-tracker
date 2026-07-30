import {
  derivedOutstanding,
  LOAN_ESTIMATE_VERSION,
  projectLoanSchedule,
  standardMonthlyAnnuity,
} from './loan-calculator';

describe('loan estimate calculator', () => {
  it('matches the approved nominal monthly annuity fixture without binary floats', () => {
    expect(LOAN_ESTIMATE_VERSION).toBe('standard_nominal_monthly_annuity_v1');
    expect(standardMonthlyAnnuity('120000', '12', 12, 2, 'HALF_EVEN')).toBe('10661.85');
  });

  it('handles zero rate and currency rounding', () => {
    expect(standardMonthlyAnnuity('1200', '0', 12, 2, 'HALF_EVEN')).toBe('100');
    expect(standardMonthlyAnnuity('1000', '0', 3, 0, 'HALF_EVEN')).toBe('333');
  });

  it('keeps extra payment and insurance explicitly projected', () => {
    const schedule = projectLoanSchedule({
      principal: '1200',
      nominalAnnualRate: '0',
      termMonths: 12,
      startsOn: '2026-01-31',
      paymentDay: 31,
      extraPaymentScenario: '10',
      insuranceMonthly: '5',
      currencyScale: 2,
      roundingMode: 'HALF_EVEN',
    });
    expect(schedule[0]).toEqual({
      sequence: 1,
      dueOn: '2026-02-28',
      principalComponent: '110',
      interestComponent: '0',
      feeComponent: '5',
      totalAmount: '115',
      remainingPrincipal: '1090',
      status: 'projected',
    });
    expect(schedule.every(({ status }) => status === 'projected')).toBe(true);
  });

  it('derives outstanding principal only from non-reversed posted components', () => {
    expect(
      derivedOutstanding('1000', [
        { loanPrincipalComponent: '250.000000000001', reversedByJournalEntryId: null },
        { loanPrincipalComponent: '100', reversedByJournalEntryId: 'reversal' },
      ]),
    ).toBe('749.999999999999');
  });
});
