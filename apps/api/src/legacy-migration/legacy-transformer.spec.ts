import { LEGACY_RELATION_MAPPINGS } from './legacy-schema.manifest';
import type { LegacyRow, LegacySourceSnapshot } from './legacy-migration.types';
import { LegacyTransformer, migrationPlanFingerprint } from './legacy-transformer';

const fixedInstant = '2026-01-15T10:00:00.000Z';

describe('LegacyTransformer', () => {
  it('maps evidenced finance events and reclassifies goal and reserve movements as transfers', () => {
    const source = snapshot({
      users: [user({ id: 1 })],
      currencies: [{ code: 'HUF', name: 'Hungarian Forint' }],
      user_currencies: [{ user_id: 1, code: 'HUF', is_main: true }],
      transactions: [
        {
          id: 100,
          user_id: 1,
          kind: 'income',
          category_id: null,
          amount: '1000.00',
          currency: 'HUF',
          occurred_on: '2026-01-10',
          note: null,
          created_at: fixedInstant,
          updated_at: null,
        },
        {
          id: 101,
          user_id: 1,
          kind: 'spending',
          category_id: null,
          amount: '75.00',
          currency: 'HUF',
          occurred_on: '2026-01-12',
          note: null,
          created_at: fixedInstant,
          updated_at: null,
          source: 'ef',
          source_ref_id: 300,
          locked: true,
          ef_tx_id: 300,
        },
      ],
      goals: [
        {
          id: 10,
          user_id: 1,
          title: 'Synthetic goal',
          target_amount: '500.00',
          current_amount: '125.00',
          currency: 'HUF',
          deadline: '2026-12-01',
          priority: 2,
          status: 'active',
        },
      ],
      goal_contributions: [
        {
          id: 200,
          user_id: 1,
          goal_id: 10,
          amount: '125.00',
          currency: 'HUF',
          occurred_on: '2026-01-11',
          note: null,
          created_at: fixedInstant,
        },
      ],
      goal_transactions: [
        {
          id: 201,
          user_id: 1,
          goal_id: 10,
          amount: '125.00',
          currency: 'HUF',
          occurred_on: '2026-01-11',
          note: null,
        },
      ],
      emergency_fund: [
        {
          user_id: 1,
          target_amount: '900.00',
          currency: 'HUF',
          total: '75.00',
          investment_id: null,
        },
      ],
      emergency_fund_tx: [
        {
          id: 300,
          user_id: 1,
          occurred_on: '2026-01-12',
          kind: 'add',
          amount_native: '75.00',
          currency_native: 'HUF',
          amount_main: '75.00',
          main_currency: 'HUF',
          rate_used: '1.00000000',
          note: null,
        },
      ],
    });

    const plan = new LegacyTransformer().transform(source);

    expect(plan.blockingCodes).toEqual([]);
    expect(
      plan.planned
        .filter(({ targetTable }) => targetTable === 'journal_entries')
        .map(({ values }) => values.economic_type)
        .sort(),
    ).toEqual(['external_income', 'internal_transfer', 'internal_transfer']);
    expect(plan.skipped.map(({ reasonCode }) => reasonCode).sort()).toEqual([
      'MATCHED_DUPLICATE_MOVEMENT',
      'MATCHED_EMERGENCY_TRANSFER_LEG',
    ]);
    expect(plan.quarantined).toEqual([]);
    expect(plan.reconciliation.every(({ status }) => status !== 'blocked')).toBe(true);

    const repeated = new LegacyTransformer().transform(source);
    expect(migrationPlanFingerprint(repeated)).toBe(migrationPlanFingerprint(plan));
    expect(repeated).toEqual(plan);
  });

  it('never migrates an administrator implicitly and quarantines orphan-owned rows', () => {
    const source = snapshot({
      users: [user({ id: 1, role: 'admin' }), user({ id: 2 })],
      transactions: [
        {
          id: 1,
          user_id: 1,
          kind: 'income',
          category_id: null,
          amount: '10.00',
          currency: 'HUF',
          occurred_on: '2026-01-10',
          note: null,
          created_at: fixedInstant,
          updated_at: null,
        },
        {
          id: 2,
          user_id: 999,
          kind: 'income',
          category_id: null,
          amount: '10.00',
          currency: 'HUF',
          occurred_on: '2026-01-10',
          note: null,
          created_at: fixedInstant,
          updated_at: null,
        },
      ],
    });

    const plan = new LegacyTransformer().transform(source);

    expect(plan.planned.filter(({ targetTable }) => targetTable === 'users')).toHaveLength(1);
    expect(plan.quarantined.map(({ reasonCode }) => reasonCode).sort()).toEqual([
      'DEFAULT_OR_UNAPPROVED_ADMIN',
      'ORPHAN_OWNER',
      'ORPHAN_OWNER',
    ]);
    expect(JSON.stringify(plan.quarantined)).not.toContain('admin@example.test');
    expect(JSON.stringify(plan.quarantined)).not.toContain('synthetic-password-hash');
  });

  it('maps the supported investment core while quarantining configured-schema securities drift', () => {
    const source = snapshot(
      {
        users: [user({ id: 1 })],
        investments: [
          {
            id: 40,
            user_id: 1,
            type: 'etf',
            name: 'Synthetic ETF scenario',
            provider: null,
            identifier: null,
            interest_rate: '5.250',
            notes: null,
            created_at: fixedInstant,
            updated_at: fixedInstant,
            balance: '0.00',
            currency: 'EUR',
            interest_frequency: 'monthly',
            stock_id: 7,
            units: '3.500000',
          },
        ],
        investment_transactions: [],
      },
      'configured-drift',
    );

    const plan = new LegacyTransformer().transform(source);
    const drift = plan.quarantined.find(
      ({ reasonCode }) => reasonCode === 'UNTRACKED_INVESTMENT_SECURITIES_LINK',
    );

    expect(plan.planned.some(({ targetTable }) => targetTable === 'investments')).toBe(true);
    expect(drift?.detailCodes).toEqual(['CONFIGURED_SCHEMA_STOCK_ID', 'CONFIGURED_SCHEMA_UNITS']);
  });

  it('covers every required Step 20 domain with an explicit source disposition', () => {
    const domains = new Set(LEGACY_RELATION_MAPPINGS.map(({ domain }) => domain));
    const sourceTables = LEGACY_RELATION_MAPPINGS.map(({ sourceTable }) => sourceTable);
    expect(domains).toEqual(
      new Set([
        'identity',
        'currency',
        'ledger',
        'budgeting',
        'recurrence',
        'goals',
        'emergency_reserve',
        'loans',
        'investments',
        'securities',
        'feedback',
        'billing',
        'notifications',
        'administration',
        'removed',
      ]),
    );
    expect(new Set(sourceTables).size).toBe(sourceTables.length);
    expect(
      LEGACY_RELATION_MAPPINGS.every(
        ({ disposition, rationaleCode, targetTables }) =>
          rationaleCode.length > 0 && (disposition !== 'map' || targetTables.length > 0),
      ),
    ).toBe(true);
  });
});

function snapshot(
  rows: Record<string, readonly LegacyRow[]>,
  version: LegacySourceSnapshot['schema']['version'] = 'recorded-035',
): LegacySourceSnapshot {
  return {
    schema: {
      version,
      appliedMigrations: ['035_system_configuration.sql'],
      columns: [],
      fingerprint: 'a'.repeat(64),
      driftCodes: version === 'configured-drift' ? ['CONFIGURED_GOAL_AND_INVESTMENT_DRIFT'] : [],
      blockingCodes: [],
    },
    rows,
    rowCount: Object.values(rows).reduce((sum, tableRows) => sum + tableRows.length, 0),
    dataFingerprint: 'b'.repeat(64),
  };
}

function user(overrides: Record<string, unknown>): LegacyRow {
  return {
    id: 1,
    email: 'user@example.test',
    password_hash: 'synthetic-password-hash',
    full_name: 'Synthetic User',
    date_of_birth: '1990-01-01',
    role: 'free',
    status: 'active',
    email_verified_at: fixedInstant,
    created_at: fixedInstant,
    theme: 'verdant-horizon',
    desired_language: 'en',
    onboard_step: 6,
    needs_tutorial: false,
    ...overrides,
  };
}
