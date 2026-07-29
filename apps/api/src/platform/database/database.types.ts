import type { ColumnType } from 'kysely';
import type { JsonValue } from '../events/outbox.port';

type DatabaseTimestamp = ColumnType<Date, Date, Date>;

export interface IdempotencyKeysTable {
  scope_id: string;
  operation: string;
  key_hash: string;
  request_hash: string;
  status: 'completed' | 'in_progress';
  response: Readonly<Record<string, JsonValue>> | null;
  created_at: DatabaseTimestamp;
  completed_at: DatabaseTimestamp | null;
}

export interface DatabaseSchema {
  'mymoneymap.idempotency_keys': IdempotencyKeysTable;
}
