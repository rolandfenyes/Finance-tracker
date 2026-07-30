import { sql, type Kysely } from 'kysely';
import { applySecuritiesLedgerGuard } from './20260729130100_securities_account_guard_revision';

export async function up(database: Kysely<unknown>): Promise<void> {
  await applySecuritiesLedgerGuard(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`SELECT 1`.execute(database);
}
