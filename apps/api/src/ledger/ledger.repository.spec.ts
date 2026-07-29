import { buildLegs } from './ledger.repository';
import type { PostJournalCommand } from './ledger.types';

describe('ledger posting leg semantics', () => {
  const base: PostJournalCommand = {
    userId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    actorUserId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    economicType: 'external_income',
    amount: '1000.000000000001',
    currency: 'HUF',
    postedOn: '2026-07-29',
    effectiveAt: new Date('2026-07-29T10:00:00.000Z'),
    createdAt: new Date('2026-07-29T10:00:01.000Z'),
    sourceModule: 'manual',
    idempotencyKeyHash: 'a'.repeat(64),
  };

  it.each([
    ['external_income', 'debit', 'credit'],
    ['interest', 'debit', 'credit'],
    ['dividend', 'debit', 'credit'],
    ['external_expense', 'credit', 'debit'],
    ['fee', 'credit', 'debit'],
  ] as const)(
    'maps %s to explicit owned/external sides',
    (economicType, ownedSide, externalSide) => {
      const legs = buildLegs(
        'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        { ...base, economicType },
        { owned: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc' },
        base.createdAt,
      );
      expect(legs.find((leg) => leg.account_id)?.side).toBe(ownedSide);
      expect(legs.find((leg) => leg.account_id === null)?.side).toBe(externalSide);
      expect(legs.every((leg) => leg.amount === '1000.000000000001')).toBe(true);
      expect(legs.every((leg) => typeof leg.amount === 'string')).toBe(true);
    },
  );

  it('maps internal transfer source to credit and destination to debit', () => {
    const legs = buildLegs(
      'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      { ...base, economicType: 'internal_transfer' },
      {
        source: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        destination: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
      },
      base.createdAt,
    );
    expect(legs).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          account_id: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
          side: 'credit',
        }),
        expect.objectContaining({
          account_id: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
          side: 'debit',
        }),
      ]),
    );
  });

  it('requires explicit adjustment direction to choose the owned side', () => {
    const increased = buildLegs(
      'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      { ...base, economicType: 'adjustment', adjustmentDirection: 'increase' },
      { owned: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc' },
      base.createdAt,
    );
    const decreased = buildLegs(
      'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      { ...base, economicType: 'adjustment', adjustmentDirection: 'decrease' },
      { owned: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc' },
      base.createdAt,
    );
    expect(increased.find((leg) => leg.account_id)?.side).toBe('debit');
    expect(decreased.find((leg) => leg.account_id)?.side).toBe('credit');
  });
});
