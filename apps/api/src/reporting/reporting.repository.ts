import { Inject, Injectable } from '@nestjs/common';
import { sql, type Kysely, type RawBuilder } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type {
  BasicIncomeForecastRow,
  PostedAggregateRow,
  RecurringRuleForecastRow,
  ReportActivityItem,
  ReportActivityKind,
  ReportActivityPage,
  ReportFilters,
} from './reporting.types';

interface ActivityCursor {
  postedOn: string;
  effectiveAt: string;
  id: string;
}

interface ActivityRow {
  id: string;
  economic_type: ReportActivityItem['economicType'];
  category_id: string | null;
  note: string | null;
  source_module: ReportActivityItem['source']['module'];
  source_reference_id: string | null;
  posted_on: string | Date;
  effective_at: Date;
  reverses_entry_id: string | null;
  source_amount: string;
  source_currency: string;
  target_currency: string;
  converted_amount: string | null;
  status: ReportActivityItem['conversionStatus'];
  provider: string | null;
  rate_at: Date | null;
  fetched_at: Date | null;
}

export class InvalidReportCursorError extends Error {}

@Injectable()
export class ReportingRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  async postedSummary(
    userId: string,
    first: string,
    last: string,
    filters: ReportFilters,
  ): Promise<PostedAggregateRow> {
    const where = reportWhere(userId, first, last, filters);
    const result = await sql<PostedAggregateRow>`
      WITH filtered AS (
        SELECT
          e.economic_type,
          e.reverses_entry_id,
          s.converted_amount,
          s.status,
          s.provider,
          s.rate_at,
          s.fetched_at,
          sum(CASE WHEN l.side = 'debit' THEN 1 ELSE -1 END)::numeric AS owned_direction
        FROM mymoneymap.journal_entries e
        JOIN mymoneymap.fx_conversion_snapshots s
          ON s.entry_id = e.id AND s.user_id = e.user_id
        JOIN mymoneymap.journal_legs l
          ON l.entry_id = e.id
         AND l.user_id = e.user_id
         AND l.account_id IS NOT NULL
        WHERE ${where}
        GROUP BY
          e.id,
          e.economic_type,
          e.reverses_entry_id,
          s.converted_amount,
          s.status,
          s.provider,
          s.rate_at,
          s.fetched_at
      )
      SELECT
        COALESCE(sum(
          CASE WHEN economic_type IN ('external_income', 'interest', 'dividend')
            THEN converted_amount * owned_direction ELSE 0 END
        ), 0)::text AS income,
        COALESCE(sum(
          CASE WHEN economic_type IN ('external_expense', 'fee')
            THEN -converted_amount * owned_direction ELSE 0 END
        ), 0)::text AS expense,
        COALESCE(sum(
          CASE WHEN economic_type IN ('internal_transfer', 'loan_repayment')
            THEN converted_amount * CASE WHEN reverses_entry_id IS NULL THEN 1 ELSE -1 END
            ELSE 0 END
        ), 0)::text AS transfer,
        COALESCE(sum(
          CASE WHEN economic_type = 'adjustment'
            THEN converted_amount * owned_direction ELSE 0 END
        ), 0)::text AS "adjustmentNet",
        COALESCE(sum(
          CASE WHEN economic_type = 'trade_cash'
            THEN converted_amount * owned_direction ELSE 0 END
        ), 0)::text AS "tradeCashNet",
        COALESCE(sum(converted_amount * owned_direction), 0)::text AS "netCashFlow",
        count(*) FILTER (WHERE converted_amount IS NOT NULL)::integer AS "includedSourceCount",
        count(*) FILTER (WHERE status = 'unavailable')::integer AS "unavailableSourceCount",
        count(*) FILTER (WHERE status = 'stale')::integer AS "staleSourceCount",
        COALESCE(array_agg(DISTINCT provider) FILTER (WHERE provider IS NOT NULL), '{}') AS providers,
        min(rate_at) FILTER (WHERE converted_amount IS NOT NULL) AS "oldestRateAt",
        max(fetched_at) FILTER (WHERE converted_amount IS NOT NULL) AS "newestFetchedAt"
      FROM filtered
    `.execute(this.database);
    return result.rows[0]!;
  }

  async activity(
    userId: string,
    first: string,
    last: string,
    filters: ReportFilters,
    limit: number,
    cursorText?: string,
  ): Promise<ReportActivityPage> {
    const cursor = cursorText ? decodeCursor(cursorText) : undefined;
    let query = this.database
      .selectFrom('mymoneymap.journal_entries as e')
      .innerJoin('mymoneymap.fx_conversion_snapshots as s', (join) =>
        join.onRef('s.entry_id', '=', 'e.id').onRef('s.user_id', '=', 'e.user_id'),
      )
      .select([
        'e.id',
        'e.economic_type',
        'e.category_id',
        'e.note',
        'e.source_module',
        'e.source_reference_id',
        'e.posted_on',
        'e.effective_at',
        'e.reverses_entry_id',
        's.source_amount',
        's.source_currency',
        's.target_currency',
        's.converted_amount',
        's.status',
        's.provider',
        's.rate_at',
        's.fetched_at',
      ])
      .where(sql<boolean>`${reportWhere(userId, first, last, filters)}`);
    if (cursor) {
      query = query.where((expression) =>
        expression.or([
          expression('e.posted_on', '<', cursor.postedOn),
          expression.and([
            expression('e.posted_on', '=', cursor.postedOn),
            expression('e.effective_at', '<', new Date(cursor.effectiveAt)),
          ]),
          expression.and([
            expression('e.posted_on', '=', cursor.postedOn),
            expression('e.effective_at', '=', new Date(cursor.effectiveAt)),
            expression('e.id', '<', cursor.id),
          ]),
        ]),
      );
    }
    const rows = (await query
      .orderBy('e.posted_on', 'desc')
      .orderBy('e.effective_at', 'desc')
      .orderBy('e.id', 'desc')
      .limit(limit + 1)
      .execute()) as ActivityRow[];
    const pageRows = rows.slice(0, limit);
    const lastRow = pageRows.at(-1);
    return {
      items: pageRows.map(mapActivity),
      nextCursor:
        rows.length > limit && lastRow
          ? encodeCursor({
              postedOn: dateText(lastRow.posted_on),
              effectiveAt: lastRow.effective_at.toISOString(),
              id: lastRow.id,
            })
          : null,
    };
  }

  async basicIncomes(
    userId: string,
    first: string,
    last: string,
  ): Promise<BasicIncomeForecastRow[]> {
    const rows = await this.database
      .selectFrom('mymoneymap.basic_incomes')
      .select(['id', 'label', 'amount', 'currency', 'valid_from', 'valid_to', 'category_id'])
      .where('user_id', '=', userId)
      .where('valid_from', '<=', last)
      .where((expression) =>
        expression.or([expression('valid_to', 'is', null), expression('valid_to', '>=', first)]),
      )
      .orderBy('valid_from')
      .orderBy('id')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      label: row.label,
      amount: row.amount,
      currency: row.currency,
      validFrom: dateText(row.valid_from),
      validTo: row.valid_to === null ? null : dateText(row.valid_to),
      categoryId: row.category_id,
    }));
  }

  async recurringRules(userId: string, last: string): Promise<RecurringRuleForecastRow[]> {
    const rows = await this.database
      .selectFrom('mymoneymap.recurring_rules')
      .select([
        'id',
        'title',
        'amount',
        'currency',
        'economic_type',
        'starts_on',
        'rrule',
        'category_id',
      ])
      .where('user_id', '=', userId)
      .where('starts_on', '<=', last)
      .orderBy('starts_on')
      .orderBy('id')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      title: row.title,
      amount: row.amount,
      currency: row.currency,
      economicType: row.economic_type,
      startsOn: dateText(row.starts_on),
      rrule: row.rrule,
      categoryId: row.category_id,
    }));
  }

  async years(userId: string, currentYear: number): Promise<number[]> {
    const result = await sql<{ year: number }>`
      SELECT DISTINCT year
      FROM (
        SELECT extract(year FROM posted_on)::integer AS year
          FROM mymoneymap.journal_entries WHERE user_id = ${userId}
        UNION ALL
        SELECT extract(year FROM valid_from)::integer
          FROM mymoneymap.basic_incomes WHERE user_id = ${userId}
        UNION ALL
        SELECT extract(year FROM starts_on)::integer
          FROM mymoneymap.recurring_rules WHERE user_id = ${userId}
        UNION ALL
        SELECT ${currentYear}::integer
      ) years
      ORDER BY year DESC
    `.execute(this.database);
    return result.rows.map(({ year }) => year);
  }
}

function reportWhere(
  userId: string,
  first: string,
  last: string,
  filters: ReportFilters,
): RawBuilder<boolean> {
  const conditions: RawBuilder<unknown>[] = [
    sql`e.user_id = ${userId}`,
    sql`e.posted_on >= ${first}::date`,
    sql`e.posted_on <= ${last}::date`,
  ];
  const economicTypes = filters.kind ? economicTypesFor(filters.kind) : undefined;
  if (economicTypes) {
    conditions.push(sql`e.economic_type IN (${sql.join(economicTypes)})`);
  }
  if (filters.categoryId) conditions.push(sql`e.category_id = ${filters.categoryId}::uuid`);
  if (filters.currency) conditions.push(sql`s.source_currency = ${filters.currency}`);
  if (filters.query) conditions.push(sql`COALESCE(e.note, '') ILIKE ${`%${filters.query}%`}`);
  if (filters.minAmount) conditions.push(sql`s.source_amount >= ${filters.minAmount}::numeric`);
  if (filters.maxAmount) conditions.push(sql`s.source_amount <= ${filters.maxAmount}::numeric`);
  return sql<boolean>`${sql.join(conditions, sql` AND `)}`;
}

function economicTypesFor(kind: ReportActivityKind): string[] {
  if (kind === 'income') return ['external_income', 'interest', 'dividend'];
  if (kind === 'expense') return ['external_expense', 'fee'];
  if (kind === 'transfer') return ['internal_transfer', 'loan_repayment'];
  return [kind];
}

function mapActivity(row: ActivityRow): ReportActivityItem {
  return {
    sourceEntryId: row.id,
    economicType: row.economic_type,
    kind: reportKind(row.economic_type),
    categoryId: row.category_id,
    note: row.note,
    source: { module: row.source_module, referenceId: row.source_reference_id },
    postedOn: dateText(row.posted_on),
    effectiveAt: row.effective_at.toISOString(),
    amount: row.source_amount,
    currency: row.source_currency,
    ...(row.converted_amount === null ? {} : { convertedAmount: row.converted_amount }),
    reportingCurrency: row.target_currency,
    conversionStatus: row.status,
    provider: row.provider,
    rateAt: row.rate_at?.toISOString() ?? null,
    fetchedAt: row.fetched_at?.toISOString() ?? null,
    reversesEntryId: row.reverses_entry_id,
  };
}

export function reportKind(economicType: ReportActivityItem['economicType']): ReportActivityKind {
  if (['external_income', 'interest', 'dividend'].includes(economicType)) return 'income';
  if (['external_expense', 'fee'].includes(economicType)) return 'expense';
  if (['internal_transfer', 'loan_repayment'].includes(economicType)) return 'transfer';
  return economicType === 'trade_cash' ? 'trade_cash' : 'adjustment';
}

function encodeCursor(value: ActivityCursor): string {
  return Buffer.from(JSON.stringify(value), 'utf8').toString('base64url');
}

function decodeCursor(value: string): ActivityCursor {
  let cursor: Partial<ActivityCursor>;
  try {
    cursor = JSON.parse(
      Buffer.from(value, 'base64url').toString('utf8'),
    ) as Partial<ActivityCursor>;
  } catch {
    throw new InvalidReportCursorError('Invalid report cursor');
  }
  if (
    typeof cursor.postedOn !== 'string' ||
    typeof cursor.effectiveAt !== 'string' ||
    typeof cursor.id !== 'string' ||
    Number.isNaN(Date.parse(cursor.effectiveAt))
  ) {
    throw new InvalidReportCursorError('Invalid report cursor');
  }
  return cursor as ActivityCursor;
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getUTCFullYear().toString().padStart(4, '0'),
    (value.getUTCMonth() + 1).toString().padStart(2, '0'),
    value.getUTCDate().toString().padStart(2, '0'),
  ].join('-');
}
