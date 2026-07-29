import { deriveGoalProgress } from './goal-progress';

describe('goal progress', () => {
  it('derives exact progress from active contribution history without binary floating point', () => {
    expect(
      deriveGoalProgress('1000.000000000001', [
        { goalAmount: '400.000000000001', reversedByJournalEntryId: null },
        { goalAmount: '600', reversedByJournalEntryId: null },
      ]),
    ).toEqual({
      currentAmount: '1000.000000000001',
      remainingAmount: '0',
      progressPercent: '100',
    });
  });

  it('excludes reversed history and uses an explicit four-place presentation rounding policy', () => {
    expect(
      deriveGoalProgress('3', [
        { goalAmount: '1', reversedByJournalEntryId: null },
        {
          goalAmount: '2',
          reversedByJournalEntryId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        },
      ]),
    ).toEqual({
      currentAmount: '1',
      remainingAmount: '2',
      progressPercent: '33.3333',
    });
  });
});
