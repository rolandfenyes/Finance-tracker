import { createHash } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import { LEGACY_MAPPING_BY_TABLE, LEGACY_RELATION_MAPPINGS } from './legacy-schema.manifest';
import type {
  LegacyRow,
  LegacySchemaColumn,
  LegacySchemaSnapshot,
  LegacySourceSnapshot,
} from './legacy-migration.types';

const SENSITIVE_SOURCE_COLUMNS = new Set([
  'api_key_encrypted',
  'email_verification_token',
  'password_hash',
  'public_key_pem',
  'selector',
  'stripe_publishable_key',
  'stripe_secret_key',
  'stripe_webhook_secret',
  'token_hash',
]);

interface CatalogColumnRow {
  table_name: string;
  column_name: string;
  data_type: string;
  is_nullable: 'YES' | 'NO';
}

export class LegacySourceExtractor {
  constructor(private readonly pool: Pool) {}

  async extract(): Promise<LegacySourceSnapshot> {
    const client = await this.pool.connect();
    try {
      await client.query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
      const readOnly = await client.query<{ transaction_read_only: string }>(
        'SHOW transaction_read_only',
      );
      if (readOnly.rows[0]?.transaction_read_only !== 'on') {
        throw new Error('LEGACY_SOURCE_NOT_READ_ONLY');
      }

      const schema = await inspectSchema(client);
      if (schema.blockingCodes.length > 0) {
        await client.query('ROLLBACK');
        return {
          schema,
          rows: {},
          rowCount: 0,
          dataFingerprint: emptyFingerprint(),
        };
      }

      const rows: Record<string, readonly LegacyRow[]> = {};
      const dataHasher = createHash('sha256');
      let rowCount = 0;

      for (const mapping of LEGACY_RELATION_MAPPINGS) {
        const result = await client.query<{ row: LegacyRow }>(
          `SELECT to_jsonb(source_row) AS row
             FROM public.${quoteIdentifier(mapping.sourceTable)} source_row`,
        );
        const deterministicRows = [...result.rows.map(({ row }) => row)].sort((left, right) =>
          canonicalJson(redactForFingerprint(left)).localeCompare(
            canonicalJson(redactForFingerprint(right)),
          ),
        );
        rows[mapping.sourceTable] = deterministicRows;
        rowCount += deterministicRows.length;
        for (const row of deterministicRows) {
          dataHasher.update(mapping.sourceTable);
          dataHasher.update('\0');
          dataHasher.update(canonicalJson(redactForFingerprint(row)));
          dataHasher.update('\n');
        }
      }

      await client.query('COMMIT');
      return {
        schema,
        rows,
        rowCount,
        dataFingerprint: dataHasher.digest('hex'),
      };
    } catch (error) {
      await client.query('ROLLBACK').catch(() => undefined);
      throw error;
    } finally {
      client.release();
    }
  }
}

export async function inspectSchema(client: PoolClient): Promise<LegacySchemaSnapshot> {
  const catalog = await client.query<CatalogColumnRow>(
    `SELECT columns.table_name,columns.column_name,columns.data_type,columns.is_nullable
       FROM information_schema.columns columns
       JOIN information_schema.tables tables
         ON tables.table_schema=columns.table_schema
        AND tables.table_name=columns.table_name
        AND tables.table_type='BASE TABLE'
      WHERE columns.table_schema='public'
      ORDER BY columns.table_name,columns.ordinal_position`,
  );
  const columns: LegacySchemaColumn[] = catalog.rows.map((column) => ({
    table: column.table_name,
    column: column.column_name,
    dataType: column.data_type,
    nullable: column.is_nullable === 'YES',
  }));
  const tableColumns = new Map<string, Set<string>>();
  for (const column of columns) {
    const current = tableColumns.get(column.table) ?? new Set<string>();
    current.add(column.column);
    tableColumns.set(column.table, current);
  }

  const blockingCodes = new Set<string>();
  const driftCodes = new Set<string>();
  for (const mapping of LEGACY_RELATION_MAPPINGS) {
    const actual = tableColumns.get(mapping.sourceTable);
    if (!actual) {
      blockingCodes.add(`MISSING_TABLE_${mapping.sourceTable.toUpperCase()}`);
      continue;
    }
    for (const required of mapping.requiredColumns) {
      if (!actual.has(required)) {
        blockingCodes.add(
          `MISSING_COLUMN_${mapping.sourceTable.toUpperCase()}_${required.toUpperCase()}`,
        );
      }
    }
    const known = new Set([...mapping.requiredColumns, ...(mapping.optionalColumns ?? [])]);
    for (const actualColumn of actual) {
      if (!known.has(actualColumn)) {
        blockingCodes.add(
          `UNKNOWN_COLUMN_${mapping.sourceTable.toUpperCase()}_${actualColumn.toUpperCase()}`,
        );
      }
    }
  }
  for (const sourceTable of tableColumns.keys()) {
    if (!LEGACY_MAPPING_BY_TABLE.has(sourceTable)) {
      blockingCodes.add(`UNKNOWN_TABLE_${sourceTable.toUpperCase()}`);
    }
  }

  const appliedMigrations = await readAppliedMigrations(client, tableColumns);
  const hasRecorded035 = appliedMigrations.includes('035_system_configuration.sql');
  const hasRecorded036 = appliedMigrations.includes('036_goal_category.sql');
  const configuredDrift =
    tableColumns.get('goals')?.has('category_id') === true &&
    tableColumns.get('investments')?.has('stock_id') === true &&
    tableColumns.get('investments')?.has('units') === true;
  if (configuredDrift) {
    driftCodes.add('CONFIGURED_GOAL_AND_INVESTMENT_DRIFT');
  }
  if (!hasRecorded035) {
    blockingCodes.add('UNSUPPORTED_MIGRATION_LEDGER_BEFORE_035');
  }

  const version: LegacySchemaSnapshot['version'] = !hasRecorded035
    ? 'unsupported'
    : hasRecorded036
      ? 'recorded-036'
      : configuredDrift
        ? 'configured-drift'
        : 'recorded-035';

  return {
    version,
    appliedMigrations,
    columns,
    fingerprint: createHash('sha256').update(canonicalJson(columns)).digest('hex'),
    driftCodes: [...driftCodes].sort(),
    blockingCodes: [...blockingCodes].sort(),
  };
}

async function readAppliedMigrations(
  client: PoolClient,
  tables: ReadonlyMap<string, ReadonlySet<string>>,
): Promise<readonly string[]> {
  if (!tables.has('schema_migrations')) {
    return [];
  }
  const result = await client.query<{ filename: string }>(
    'SELECT filename FROM public.schema_migrations ORDER BY filename',
  );
  return result.rows.map(({ filename }) => filename);
}

export function sourceKeyHash(table: string, row: LegacyRow): string {
  const stableKey =
    row.id ??
    (table === 'user_currencies' ? `${String(row.user_id)}:${String(row.code)}` : undefined) ??
    (table === 'fx_rates'
      ? `${String(row.rate_date)}:${String(row.base_code)}:${String(row.code)}`
      : undefined) ??
    (table === 'emergency_fund' ? row.user_id : undefined) ??
    (table === 'baby_steps' ? `${String(row.user_id)}:${String(row.step)}` : undefined) ??
    canonicalJson(redactForFingerprint(row));
  return createHash('sha256')
    .update(`${table}\0${canonicalJson(stableKey)}`)
    .digest('hex');
}

export function canonicalJson(value: unknown): string {
  if (Array.isArray(value)) {
    return `[${value.map(canonicalJson).join(',')}]`;
  }
  if (value !== null && typeof value === 'object') {
    const entries = Object.entries(value as Record<string, unknown>).sort(([left], [right]) =>
      left.localeCompare(right),
    );
    return `{${entries
      .map(([key, nested]) => `${JSON.stringify(key)}:${canonicalJson(nested)}`)
      .join(',')}}`;
  }
  return JSON.stringify(value) ?? 'null';
}

function redactForFingerprint(row: LegacyRow): LegacyRow {
  return Object.fromEntries(
    Object.entries(row).map(([column, value]) => [
      column,
      SENSITIVE_SOURCE_COLUMNS.has(column) && value !== null ? '[REDACTED_PRESENT]' : value,
    ]),
  );
}

function quoteIdentifier(identifier: string): string {
  if (!/^[a-z][a-z0-9_]*$/.test(identifier)) {
    throw new Error('UNSAFE_LEGACY_IDENTIFIER');
  }
  return `"${identifier}"`;
}

function emptyFingerprint(): string {
  return createHash('sha256').update('').digest('hex');
}
