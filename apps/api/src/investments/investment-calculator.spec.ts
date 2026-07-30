import {
  compoundScenario,
  deriveInvestmentBalance,
  projectScenarioWithContributions,
} from './investment-calculator';

describe('generic investment scenario calculator', () => {
  it('matches nominal monthly compound fixtures without changing the principal', () => {
    expect(
      compoundScenario({
        principal: '1000',
        nominalAnnualRate: '12',
        frequency: 'monthly',
        years: '1',
        scale: 2,
        roundingMode: 'HALF_EVEN',
      }),
    ).toBe('1126.83');
  });

  it('supports fractional periods with exact decimal inputs', () => {
    expect(
      compoundScenario({
        principal: '1000',
        nominalAnnualRate: '12',
        frequency: 'monthly',
        years: '0.5',
        scale: 2,
        roundingMode: 'HALF_EVEN',
      }),
    ).toBe('1061.52');
  });

  it('keeps zero-rate scenarios flat and rejects negative scenario inputs', () => {
    expect(
      compoundScenario({
        principal: '123.456',
        nominalAnnualRate: '0',
        frequency: 'daily',
        years: '5',
        scale: 3,
        roundingMode: 'HALF_EVEN',
      }),
    ).toBe('123.456');
    expect(() =>
      compoundScenario({
        principal: '100',
        nominalAnnualRate: '-1',
        frequency: 'annual',
        years: '1',
        scale: 2,
        roundingMode: 'HALF_EVEN',
      }),
    ).toThrow('cannot be negative');
  });

  it('compounds recurring contributions only for the remaining fractional horizon', () => {
    expect(
      projectScenarioWithContributions({
        principal: '1000',
        nominalAnnualRate: '0',
        frequency: 'monthly',
        from: '2026-01-01',
        to: '2027-01-01',
        contributions: [
          { occurredOn: '2026-04-01', amount: '100' },
          { occurredOn: '2026-10-01', amount: '100' },
        ],
        scale: 2,
        roundingMode: 'HALF_EVEN',
      }),
    ).toEqual({ value: '1200', contributionTotal: '200', scenarioGain: '0' });
  });

  it('derives posted balance from unreversed movements only', () => {
    expect(
      deriveInvestmentBalance([
        {
          direction: 'deposit',
          investmentAmount: '100.000000000001',
          reversedByJournalEntryId: null,
        },
        {
          direction: 'withdrawal',
          investmentAmount: '25',
          reversedByJournalEntryId: null,
        },
        {
          direction: 'deposit',
          investmentAmount: '999',
          reversedByJournalEntryId: crypto.randomUUID(),
        },
      ]),
    ).toBe('75.000000000001');
  });
});
