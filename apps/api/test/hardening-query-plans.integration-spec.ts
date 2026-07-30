import { migrateToLatest } from '../src/platform/database/migration-runner';
import { withIsolatedPostgresDatabase } from './postgres-test-database';
import type { PoolClient } from 'pg';

jest.setTimeout(120_000);

describe('Step 21 cross-domain query-plan contract', () => {
  it('has index-backed plans for activity, reports, schedules, portfolio, admin, and exports', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const client = await pool.connect();
      try {
        await client.query('SET enable_seqscan = off');
        const userId = '00000000-0000-4000-8000-000000000021';
        const contracts = [
          {
            name: 'activity',
            index: 'journal_entries_user_posted_cursor',
            query: `SELECT id FROM mymoneymap.journal_entries
                    WHERE user_id = $1
                    ORDER BY posted_on DESC, effective_at DESC, id DESC LIMIT 100`,
          },
          {
            name: 'reports',
            index: 'journal_entries_user_period_reporting',
            query: `SELECT id FROM mymoneymap.journal_entries
                    WHERE user_id = $1 AND posted_on BETWEEN DATE '2026-01-01' AND DATE '2026-12-31'
                    ORDER BY posted_on, id`,
          },
          {
            name: 'schedules',
            index: 'recurring_occurrences_user_due_index',
            query: `SELECT id FROM mymoneymap.recurring_occurrences
                    WHERE user_id = $1 AND due_on >= DATE '2026-01-01'
                    ORDER BY due_on, rule_id LIMIT 100`,
          },
          {
            name: 'portfolio',
            index: 'securities_trades_history_index',
            query: `SELECT id FROM mymoneymap.securities_trades
                    WHERE user_id = $1
                    ORDER BY executed_at DESC, id DESC LIMIT 100`,
          },
          {
            name: 'admin',
            index: 'feedback_admin_page_index',
            query: `SELECT id FROM mymoneymap.feedback
                    WHERE created_at < now()
                    ORDER BY created_at DESC, id DESC LIMIT 100`,
            parameters: [],
          },
          {
            name: 'export',
            index: 'privacy_export_requests_owner_index',
            query: `SELECT id FROM mymoneymap.privacy_export_requests
                    WHERE user_id = $1
                    ORDER BY created_at DESC, id DESC LIMIT 100`,
          },
        ];

        for (const contract of contracts) {
          const indexes = await explainIndexes(
            client,
            contract.query,
            contract.parameters ?? [userId],
          );
          if (!indexes.includes(contract.index)) {
            throw new Error(
              `${contract.name} plan did not use ${contract.index}; used: ${indexes.join(', ')}`,
            );
          }
        }
      } finally {
        client.release();
      }
    });
  });
});

async function explainIndexes(
  client: PoolClient,
  query: string,
  parameters: unknown[],
): Promise<string[]> {
  const result = await client.query<Record<'QUERY PLAN', ExplainDocument>>(
    `EXPLAIN (COSTS OFF, FORMAT JSON) ${query}`,
    parameters,
  );
  const row = result.rows[0];
  if (!row) throw new Error('EXPLAIN returned no plan document');
  return flatten(row['QUERY PLAN'][0].Plan).flatMap((node) =>
    node['Index Name'] ? [node['Index Name']] : [],
  );
}

type ExplainDocument = [{ Plan: ExplainNode }];
interface ExplainNode {
  'Index Name'?: string;
  Plans?: ExplainNode[];
}

function flatten(node: ExplainNode): ExplainNode[] {
  return [node, ...(node.Plans ?? []).flatMap(flatten)];
}
