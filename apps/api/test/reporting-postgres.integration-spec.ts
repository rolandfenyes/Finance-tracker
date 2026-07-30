import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';
import type { Pool, PoolClient, QueryResult } from 'pg';

jest.setTimeout(120_000);

describe('reporting PostgreSQL migration and query plans', () => {
  it('migrates Step 10 up and rolls only its query-shaped indexes back', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(await indexExists(pool, 'journal_entries_user_period_reporting')).toBe(true);
      expect(await indexExists(pool, 'fx_snapshots_user_entry_index')).toBe(true);
      expect(await indexExists(pool, 'basic_incomes_user_dates_index')).toBe(true);
      expect(await indexExists(pool, 'recurring_rules_user_start_index')).toBe(true);

      await migrateOneDown(database);
      expect(await tableExists(pool, 'goals')).toBe(true);

      await migrateOneDown(database);
      expect(await tableExists(pool, 'goals')).toBe(false);
      expect(await indexExists(pool, 'journal_entries_user_period_reporting')).toBe(true);

      await migrateOneDown(database);
      expect(await indexExists(pool, 'journal_entries_user_period_reporting')).toBe(false);
      expect(await tableExists(pool, 'recurring_rules')).toBe(true);

      await migrateToLatest(database);
      expect(await indexExists(pool, 'journal_entries_user_period_reporting')).toBe(true);
    });
  });

  it('meets the approved realistic-volume posted-report plan budget', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        try {
          await client.query(`
          CREATE TEMP TABLE reporting_seed_users AS
          SELECT gen_random_uuid() AS id, n
            FROM generate_series(1, 50) n;

          INSERT INTO mymoneymap.users
            (id,email,password_hash,full_name,date_of_birth,role,email_verified_at,created_at,updated_at)
          SELECT id,
                 'report-plan-' || n || '@example.test',
                 'synthetic-not-a-real-password-hash',
                 'Synthetic Plan User',
                 '1990-01-01',
                 'premium',
                 now(),
                 now(),
                 now()
            FROM reporting_seed_users;

          CREATE TEMP TABLE reporting_seed_entries AS
          SELECT gen_random_uuid() AS id, u.id AS user_id, g.n
            FROM reporting_seed_users u
           CROSS JOIN generate_series(1, 1000) g(n);

          INSERT INTO mymoneymap.journal_entries
            (id,user_id,economic_type,category_id,note,source_module,source_reference_id,
             idempotency_key_hash,posted_on,effective_at,created_at,actor_user_id,
             reverses_entry_id,replaces_entry_id)
          SELECT id,
                 user_id,
                 CASE WHEN n % 3 = 0 THEN 'external_expense' ELSE 'external_income' END,
                 NULL,
                 'Synthetic performance fixture',
                 'manual',
                 NULL,
                 md5(id::text) || md5(id::text || ':report'),
                 DATE '2026-01-01' + ((n - 1) % 365),
                 timestamptz '2026-01-01 00:00:00+00' + ((n - 1) * interval '1 minute'),
                 now(),
                 user_id,
                 NULL,
                 NULL
            FROM reporting_seed_entries;

          INSERT INTO mymoneymap.journal_legs
            (id,entry_id,user_id,account_id,side,amount,currency,created_at)
          SELECT gen_random_uuid(),
                 e.id,
                 e.user_id,
                 CASE
                   WHEN leg.side = 'debit' AND e.n % 3 <> 0 THEN a.id
                   WHEN leg.side = 'credit' AND e.n % 3 = 0 THEN a.id
                   ELSE NULL
                 END,
                 leg.side,
                 '1.000000000000',
                 'HUF',
                 now()
            FROM reporting_seed_entries e
            JOIN mymoneymap.ledger_accounts a
              ON a.user_id = e.user_id AND a.kind = 'cash'
           CROSS JOIN (VALUES ('debit'), ('credit')) leg(side);

          INSERT INTO mymoneymap.fx_conversion_snapshots
            (id,entry_id,user_id,source_currency,target_currency,source_amount,converted_amount,
             source_rate,target_rate,conversion_rate,source_quote_id,target_quote_id,provider,
             rate_at,fetched_at,status,precision,rounding_mode,created_at)
          SELECT gen_random_uuid(),
                 id,
                 user_id,
                 'HUF',
                 'HUF',
                 '1.000000000000',
                 '1.000000000000',
                 '1',
                 '1',
                 '1',
                 NULL,
                 NULL,
                 'identity',
                 timestamptz '2026-01-01 00:00:00+00',
                 timestamptz '2026-01-01 00:00:00+00',
                 'available',
                 2,
                 'HALF_EVEN',
                 now()
            FROM reporting_seed_entries;
          `);
          await client.query('COMMIT');
        } catch (error) {
          await client.query('ROLLBACK');
          throw error;
        }

        await client.query('ANALYZE mymoneymap.journal_entries');
        await client.query('ANALYZE mymoneymap.journal_legs');
        await client.query('ANALYZE mymoneymap.fx_conversion_snapshots');
        const target = await client.query<{ id: string }>(
          'SELECT id FROM reporting_seed_users ORDER BY n LIMIT 1',
        );
        const userId = target.rows[0]!.id;
        await explain(client, userId);
        const measured = await explain(client, userId);
        const document = measured.rows[0]!['QUERY PLAN'] as ExplainDocument;
        const root = document[0]!;
        const journalNodes = flatten(root.Plan).filter(
          (node) => node['Relation Name'] === 'journal_entries',
        );
        const indexNames = flatten(root.Plan).flatMap((node) =>
          node['Index Name'] ? [node['Index Name']] : [],
        );

        expect(root['Execution Time']).toBeLessThanOrEqual(750);
        expect(root.Plan['Actual Rows']).toBe(1);
        expect(journalNodes.some((node) => node['Node Type'] === 'Seq Scan')).toBe(false);
        const usesReportingIndex = indexNames.some((name) =>
          ['journal_entries_user_period_reporting', 'journal_entries_user_posted_cursor'].includes(
            name,
          ),
        );
        if (!usesReportingIndex) {
          throw new Error(
            `Posted report did not use a user-period index: ${indexNames.join(', ')}`,
          );
        }
        expect(
          (root.Plan['Shared Hit Blocks'] ?? 0) + (root.Plan['Shared Read Blocks'] ?? 0),
        ).toBeLessThanOrEqual(5_000);
      } finally {
        client.release();
      }
    });
  });
});

type ExplainDocument = Array<{
  Plan: ExplainNode;
  'Execution Time': number;
}>;

interface ExplainNode {
  'Node Type': string;
  'Relation Name'?: string;
  'Index Name'?: string;
  'Actual Rows': number;
  'Shared Hit Blocks'?: number;
  'Shared Read Blocks'?: number;
  Plans?: ExplainNode[];
}

async function explain(
  pool: Pick<PoolClient, 'query'>,
  userId: string,
): Promise<QueryResult<Record<'QUERY PLAN', unknown>>> {
  return pool.query(
    `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)
     WITH filtered AS (
       SELECT e.economic_type,
              s.converted_amount,
              sum(CASE WHEN l.side = 'debit' THEN 1 ELSE -1 END)::numeric AS owned_direction
         FROM mymoneymap.journal_entries e
         JOIN mymoneymap.fx_conversion_snapshots s
           ON s.entry_id = e.id AND s.user_id = e.user_id
         JOIN mymoneymap.journal_legs l
           ON l.entry_id = e.id
          AND l.user_id = e.user_id
          AND l.account_id IS NOT NULL
        WHERE e.user_id = $1
          AND e.posted_on >= DATE '2026-07-01'
          AND e.posted_on <= DATE '2026-07-31'
        GROUP BY e.id, e.economic_type, s.converted_amount
     )
     SELECT COALESCE(sum(
              CASE WHEN economic_type IN ('external_income', 'interest', 'dividend')
                   THEN converted_amount * owned_direction ELSE 0 END
            ), 0)::text AS income,
            COALESCE(sum(converted_amount * owned_direction), 0)::text AS net_cash_flow
       FROM filtered`,
    [userId],
  );
}

function flatten(node: ExplainNode): ExplainNode[] {
  return [node, ...(node.Plans ?? []).flatMap(flatten)];
}

async function indexExists(pool: Pool, name: string): Promise<boolean> {
  const result = await pool.query<{ exists: boolean }>(
    `SELECT EXISTS (
       SELECT 1 FROM pg_indexes WHERE schemaname = 'mymoneymap' AND indexname = $1
     ) AS exists`,
    [name],
  );
  return result.rows[0]!.exists;
}

async function tableExists(pool: Pool, name: string): Promise<boolean> {
  const result = await pool.query<{ exists: boolean }>(
    `SELECT to_regclass('mymoneymap.' || $1) IS NOT NULL AS exists`,
    [name],
  );
  return result.rows[0]!.exists;
}
