import type { DatabaseTransaction } from '../database/database-transaction.service';
import type { EntityId } from '../identifiers/entity-id';
import type { UtcInstant } from '../time/utc-instant';

export type JsonPrimitive = boolean | null | string;
export type JsonValue = JsonPrimitive | JsonValue[] | { [key: string]: JsonValue };

export interface DomainEvent {
  id: EntityId<'domain-event'>;
  type: string;
  occurredAt: UtcInstant;
  payload: Readonly<Record<string, JsonValue>>;
}

export interface TransactionalOutboxPort {
  append(transaction: DatabaseTransaction, event: DomainEvent): Promise<void>;
}
