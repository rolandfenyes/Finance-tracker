import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Insertable, type Kysely, type Transaction } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type { JournalLegsTable } from '../platform/database/database.types';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { CalendarDate } from '../platform/time/calendar-date';
import type {
  JournalEntry,
  JournalLeg,
  JournalListPage,
  JournalListQuery,
  LedgerAccountKind,
  PostJournalCommand,
} from './ledger.types';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

interface CursorValue {
  postedOn: string;
  effectiveAt: string;
  id: string;
}

export class InvalidJournalCursorError extends Error {}

@Injectable()
export class LedgerRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  async createModuleAccount(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    kind: Exclude<LedgerAccountKind, 'cash'>,
    moduleReferenceId: string,
    createdAt: Date,
  ): Promise<string> {
    const id = randomUUID();
    const row = await transaction
      .insertInto('mymoneymap.ledger_accounts')
      .values({
        id,
        user_id: userId,
        kind,
        module_reference_id: moduleReferenceId,
        created_at: createdAt,
      })
      .onConflict((conflict) =>
        conflict
          .columns(['user_id', 'kind', 'module_reference_id'])
          .where('module_reference_id', 'is not', null)
          .doNothing(),
      )
      .returning('id')
      .executeTakeFirst();
    if (row) return row.id;
    return (
      await transaction
        .selectFrom('mymoneymap.ledger_accounts')
        .select('id')
        .where('user_id', '=', userId)
        .where('kind', '=', kind)
        .where('module_reference_id', '=', moduleReferenceId)
        .executeTakeFirstOrThrow()
    ).id;
  }

  async post(
    transaction: Transaction<DatabaseSchema>,
    command: PostJournalCommand,
  ): Promise<JournalEntry> {
    assertPostCommand(command);
    const accountIds = await this.resolveOwnedAccounts(transaction, command);
    const entryId = randomUUID();
    const createdAt = command.createdAt;
    await transaction
      .insertInto('mymoneymap.journal_entries')
      .values({
        id: entryId,
        user_id: command.userId,
        economic_type: command.economicType,
        category_id: command.categoryId ?? null,
        note: command.note?.trim() ?? null,
        source_module: command.sourceModule,
        source_reference_id: command.sourceReferenceId ?? null,
        idempotency_key_hash: command.idempotencyKeyHash,
        posted_on: command.postedOn,
        effective_at: command.effectiveAt,
        created_at: createdAt,
        actor_user_id: command.actorUserId,
        reverses_entry_id: command.reversesEntryId ?? null,
        replaces_entry_id: command.replacesEntryId ?? null,
      })
      .execute();

    const legs = buildLegs(entryId, command, accountIds, createdAt);
    await transaction.insertInto('mymoneymap.journal_legs').values(legs).execute();
    return this.findOwnedEntry(transaction, command.userId, entryId);
  }

  async reverse(
    transaction: Transaction<DatabaseSchema>,
    original: JournalEntry,
    command: {
      userId: string;
      actorUserId: string;
      postedOn: string;
      effectiveAt: Date;
      createdAt: Date;
      note?: string;
      idempotencyKeyHash: string;
    },
  ): Promise<JournalEntry> {
    const entryId = randomUUID();
    await transaction
      .insertInto('mymoneymap.journal_entries')
      .values({
        id: entryId,
        user_id: command.userId,
        economic_type: original.economicType,
        category_id: original.categoryId,
        note: command.note?.trim() ?? `Reversal of ${original.id}`,
        source_module: 'manual',
        source_reference_id: null,
        idempotency_key_hash: command.idempotencyKeyHash,
        posted_on: command.postedOn,
        effective_at: command.effectiveAt,
        created_at: command.createdAt,
        actor_user_id: command.actorUserId,
        reverses_entry_id: original.id,
        replaces_entry_id: null,
      })
      .execute();
    await transaction
      .insertInto('mymoneymap.journal_legs')
      .values(
        original.legs.map((leg) => ({
          id: randomUUID(),
          entry_id: entryId,
          user_id: command.userId,
          account_id: leg.accountId,
          side: leg.side === 'debit' ? ('credit' as const) : ('debit' as const),
          amount: leg.amount,
          currency: leg.currency,
          created_at: command.createdAt,
        })),
      )
      .execute();
    return this.findOwnedEntry(transaction, command.userId, entryId);
  }

  findOwnedEntry(executor: Executor, userId: string, entryId: string): Promise<JournalEntry> {
    return this.loadEntry(executor, userId, entryId);
  }

  async list(userId: string, query: JournalListQuery): Promise<JournalListPage> {
    const cursor = query.cursor ? decodeCursor(query.cursor) : undefined;
    let selection = this.database
      .selectFrom('mymoneymap.journal_entries')
      .selectAll()
      .where('user_id', '=', userId);
    if (query.dateFrom) selection = selection.where('posted_on', '>=', query.dateFrom);
    if (query.dateTo) selection = selection.where('posted_on', '<=', query.dateTo);
    if (cursor) {
      selection = selection.where((expression) =>
        expression.or([
          expression('posted_on', '<', cursor.postedOn),
          expression.and([
            expression('posted_on', '=', cursor.postedOn),
            expression('effective_at', '<', new Date(cursor.effectiveAt)),
          ]),
          expression.and([
            expression('posted_on', '=', cursor.postedOn),
            expression('effective_at', '=', new Date(cursor.effectiveAt)),
            expression('id', '<', cursor.id),
          ]),
        ]),
      );
    }
    const rows = await selection
      .orderBy('posted_on', 'desc')
      .orderBy('effective_at', 'desc')
      .orderBy('id', 'desc')
      .limit(query.limit + 1)
      .execute();
    const pageRows = rows.slice(0, query.limit);
    const items = await Promise.all(
      pageRows.map((row) => this.loadEntry(this.database, userId, row.id)),
    );
    const last = pageRows.at(-1);
    return {
      items,
      nextCursor:
        rows.length > query.limit && last
          ? encodeCursor({
              postedOn: databaseDate(last.posted_on),
              effectiveAt: last.effective_at.toISOString(),
              id: last.id,
            })
          : null,
    };
  }

  async accountBalance(userId: string, accountId: string, currency: string): Promise<string> {
    const row = await this.database
      .selectFrom('mymoneymap.journal_legs')
      .select(
        sql<string>`COALESCE(sum(CASE WHEN side = 'debit' THEN amount ELSE -amount END), 0)::text`.as(
          'balance',
        ),
      )
      .where('user_id', '=', userId)
      .where('account_id', '=', accountId)
      .where('currency', '=', currency)
      .executeTakeFirstOrThrow();
    return row.balance;
  }

  private async resolveOwnedAccounts(
    transaction: Transaction<DatabaseSchema>,
    command: PostJournalCommand,
  ): Promise<{ owned?: string; source?: string; destination?: string }> {
    if (command.economicType === 'internal_transfer' || command.economicType === 'loan_repayment') {
      const source = command.sourceAccountId;
      const destination = command.destinationAccountId;
      if (!source || !destination || source === destination) {
        throw new Error('A transfer requires two distinct accounts');
      }
      await this.assertOwnedAccounts(transaction, command.userId, [source, destination]);
      return { source, destination };
    }
    const owned = command.accountId ?? (await this.defaultCashAccount(transaction, command.userId));
    await this.assertOwnedAccounts(transaction, command.userId, [owned]);
    return { owned };
  }

  private async defaultCashAccount(executor: Executor, userId: string): Promise<string> {
    return (
      await executor
        .selectFrom('mymoneymap.ledger_accounts')
        .select('id')
        .where('user_id', '=', userId)
        .where('kind', '=', 'cash')
        .executeTakeFirstOrThrow()
    ).id;
  }

  private async assertOwnedAccounts(
    executor: Executor,
    userId: string,
    accountIds: string[],
  ): Promise<void> {
    const rows = await executor
      .selectFrom('mymoneymap.ledger_accounts')
      .select('id')
      .where('user_id', '=', userId)
      .where('id', 'in', accountIds)
      .execute();
    if (rows.length !== new Set(accountIds).size) throw new Error('Owned account was not found');
  }

  private async loadEntry(
    executor: Executor,
    userId: string,
    entryId: string,
  ): Promise<JournalEntry> {
    const entry = await executor
      .selectFrom('mymoneymap.journal_entries')
      .selectAll()
      .where('user_id', '=', userId)
      .where('id', '=', entryId)
      .executeTakeFirstOrThrow();
    const legs = await executor
      .selectFrom('mymoneymap.journal_legs')
      .selectAll()
      .where('user_id', '=', userId)
      .where('entry_id', '=', entryId)
      .orderBy('side')
      .orderBy('id')
      .execute();
    return {
      id: entry.id,
      economicType: entry.economic_type,
      categoryId: entry.category_id,
      note: entry.note,
      source: { module: entry.source_module, referenceId: entry.source_reference_id },
      postedOn: databaseDate(entry.posted_on),
      effectiveAt: entry.effective_at.toISOString(),
      createdAt: entry.created_at.toISOString(),
      actorUserId: entry.actor_user_id,
      reversesEntryId: entry.reverses_entry_id,
      replacesEntryId: entry.replaces_entry_id,
      legs: legs.map((leg): JournalLeg => ({
        id: leg.id,
        accountId: leg.account_id,
        side: leg.side,
        amount: leg.amount,
        currency: leg.currency,
      })),
    };
  }
}

export function buildLegs(
  entryId: string,
  command: PostJournalCommand,
  accounts: { owned?: string; source?: string; destination?: string },
  createdAt: Date,
): Insertable<JournalLegsTable>[] {
  let debitAccount: string | null;
  let creditAccount: string | null;
  if (command.economicType === 'internal_transfer' || command.economicType === 'loan_repayment') {
    debitAccount = accounts.destination!;
    creditAccount = accounts.source!;
  } else {
    const increase =
      command.economicType === 'external_income' ||
      command.economicType === 'interest' ||
      command.economicType === 'dividend' ||
      ((command.economicType === 'adjustment' || command.economicType === 'trade_cash') &&
        command.adjustmentDirection === 'increase');
    debitAccount = increase ? accounts.owned! : null;
    creditAccount = increase ? null : accounts.owned!;
  }
  return [
    {
      id: randomUUID(),
      entry_id: entryId,
      user_id: command.userId,
      account_id: debitAccount,
      side: 'debit' as const,
      amount: command.amount,
      currency: command.currency,
      created_at: createdAt,
    },
    {
      id: randomUUID(),
      entry_id: entryId,
      user_id: command.userId,
      account_id: creditAccount,
      side: 'credit' as const,
      amount: command.amount,
      currency: command.currency,
      created_at: createdAt,
    },
  ];
}

function encodeCursor(cursor: CursorValue): string {
  return Buffer.from(JSON.stringify(cursor), 'utf8').toString('base64url');
}

function decodeCursor(value: string): CursorValue {
  let decoded: Partial<CursorValue>;
  try {
    decoded = JSON.parse(Buffer.from(value, 'base64url').toString('utf8')) as Partial<CursorValue>;
  } catch {
    throw new InvalidJournalCursorError('Invalid journal cursor');
  }
  if (
    typeof decoded.postedOn !== 'string' ||
    typeof decoded.effectiveAt !== 'string' ||
    typeof decoded.id !== 'string' ||
    Number.isNaN(Date.parse(decoded.effectiveAt))
  ) {
    throw new InvalidJournalCursorError('Invalid journal cursor');
  }
  return decoded as CursorValue;
}

function databaseDate(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}

function assertPostCommand(command: PostJournalCommand): void {
  if (!ExactDecimal.create(command.amount).isPositive()) {
    throw new Error('Journal amount must be greater than zero');
  }
  CurrencyCode.create(command.currency);
  CalendarDate.create(command.postedOn);
  if (
    !Number.isFinite(command.effectiveAt.getTime()) ||
    !Number.isFinite(command.createdAt.getTime())
  ) {
    throw new Error('Journal timestamps must be valid');
  }
  if (command.actorUserId !== command.userId) {
    throw new Error('Journal actor must match the owning user');
  }
  const requiresDirection =
    command.economicType === 'adjustment' || command.economicType === 'trade_cash';
  if (requiresDirection !== (command.adjustmentDirection !== undefined)) {
    throw new Error('Adjustment and trade cash entries require an explicit direction');
  }
  if (
    command.categoryId &&
    !['external_income', 'external_expense', 'fee'].includes(command.economicType)
  ) {
    throw new Error('Category is not appropriate for this economic type');
  }
  if (command.sourceModule !== 'manual' && !command.sourceReferenceId) {
    throw new Error('Module journal entries require a source reference');
  }
}
