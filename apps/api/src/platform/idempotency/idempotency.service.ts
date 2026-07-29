import { HttpStatus, Inject, Injectable } from '@nestjs/common';
import type { Insertable } from 'kysely';
import type { DatabaseTransaction } from '../database/database-transaction.service';
import { DatabaseTransactionService } from '../database/database-transaction.service';
import type { IdempotencyKeysTable } from '../database/database.types';
import type { JsonValue } from '../events/outbox.port';
import { ApplicationError } from '../http/application-error';
import { CLOCK, type Clock } from '../time/clock';
import type { IdempotencyExecution } from './idempotency';

export interface IdempotencyResult<T> {
  value: T;
  replayed: boolean;
}

type IdempotentResponse = Readonly<Record<string, JsonValue>>;

@Injectable()
export class IdempotencyService {
  constructor(
    @Inject(DatabaseTransactionService)
    private readonly transactions: DatabaseTransactionService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  execute<T extends IdempotentResponse>(
    execution: IdempotencyExecution,
    work: (transaction: DatabaseTransaction) => Promise<T>,
  ): Promise<IdempotencyResult<T>> {
    return this.transactions.execute(async (transaction) => {
      const identity = {
        scope_id: execution.scopeId.toString(),
        operation: execution.operation.toString(),
        key_hash: execution.key.toHash(),
      };
      const createdAt = this.clock.now().toDate();
      const row: Insertable<IdempotencyKeysTable> = {
        ...identity,
        request_hash: execution.requestFingerprint.toHash(),
        status: 'in_progress',
        response: null,
        created_at: createdAt,
        completed_at: null,
      };

      const inserted = await transaction
        .insertInto('mymoneymap.idempotency_keys')
        .values(row)
        .onConflict((conflict) =>
          conflict.columns(['scope_id', 'operation', 'key_hash']).doNothing(),
        )
        .returning('key_hash')
        .executeTakeFirst();

      if (!inserted) {
        const existing = await transaction
          .selectFrom('mymoneymap.idempotency_keys')
          .select(['request_hash', 'status', 'response'])
          .where('scope_id', '=', identity.scope_id)
          .where('operation', '=', identity.operation)
          .where('key_hash', '=', identity.key_hash)
          .executeTakeFirstOrThrow();

        if (existing.request_hash !== execution.requestFingerprint.toHash()) {
          throw new ApplicationError(
            HttpStatus.CONFLICT,
            'IDEMPOTENCY_CONFLICT',
            'The idempotency key was already used for a different request',
          );
        }
        if (existing.status !== 'completed' || existing.response === null) {
          throw new ApplicationError(
            HttpStatus.CONFLICT,
            'IDEMPOTENCY_IN_PROGRESS',
            'The idempotent operation is still in progress',
          );
        }

        return { value: existing.response as T, replayed: true };
      }

      const value = await work(transaction);
      await transaction
        .updateTable('mymoneymap.idempotency_keys')
        .set({
          status: 'completed',
          response: value,
          completed_at: this.clock.now().toDate(),
        })
        .where('scope_id', '=', identity.scope_id)
        .where('operation', '=', identity.operation)
        .where('key_hash', '=', identity.key_hash)
        .executeTakeFirstOrThrow();

      return { value, replayed: false };
    });
  }
}
