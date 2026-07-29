import { Inject, Injectable } from '@nestjs/common';
import type { IsolationLevel, Kysely, Transaction } from 'kysely';
import { DATABASE } from './database.constants';
import type { DatabaseSchema } from './database.types';

export type DatabaseTransaction = Transaction<DatabaseSchema>;

@Injectable()
export class DatabaseTransactionService {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  execute<T>(
    work: (transaction: DatabaseTransaction) => Promise<T>,
    isolationLevel: IsolationLevel = 'read committed',
  ): Promise<T> {
    return this.database.transaction().setIsolationLevel(isolationLevel).execute(work);
  }
}
