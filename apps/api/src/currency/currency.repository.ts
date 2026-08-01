import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type { Kysely, Transaction } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type {
  Currency,
  FxConversionResult,
  ObservedFxQuote,
  ProviderFxQuote,
  UserCurrency,
} from './currency.types';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

@Injectable()
export class CurrencyRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async catalogue(executor: Executor = this.database): Promise<Currency[]> {
    const rows = await executor
      .selectFrom('mymoneymap.currencies')
      .select(['code', 'name', 'minor_unit', 'rounding_mode'])
      .where('active', '=', true)
      .orderBy('code')
      .execute();
    return rows.map((row) => ({
      code: row.code,
      name: row.name,
      minorUnit: row.minor_unit,
      roundingMode: row.rounding_mode,
    }));
  }

  async currency(code: string, executor: Executor = this.database): Promise<Currency | null> {
    const row = await executor
      .selectFrom('mymoneymap.currencies')
      .select(['code', 'name', 'minor_unit', 'rounding_mode'])
      .where('code', '=', code)
      .where('active', '=', true)
      .executeTakeFirst();
    return row
      ? {
          code: row.code,
          name: row.name,
          minorUnit: row.minor_unit,
          roundingMode: row.rounding_mode,
        }
      : null;
  }

  async userCurrencies(
    userId: string,
    executor: Executor = this.database,
  ): Promise<UserCurrency[]> {
    const rows = await executor
      .selectFrom('mymoneymap.user_currencies as uc')
      .innerJoin('mymoneymap.currencies as c', 'c.code', 'uc.code')
      .select(['c.code', 'c.name', 'c.minor_unit', 'c.rounding_mode', 'uc.is_main'])
      .where('uc.user_id', '=', userId)
      .where('c.active', '=', true)
      .orderBy('uc.is_main', 'desc')
      .orderBy('c.code')
      .execute();
    return rows.map((row) => ({
      code: row.code,
      name: row.name,
      minorUnit: row.minor_unit,
      roundingMode: row.rounding_mode,
      isMain: row.is_main,
    }));
  }

  async lockFinanceUser(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<{ role: 'free' | 'premium'; onboardStep: number } | null> {
    const row = await transaction
      .selectFrom('mymoneymap.users')
      .select(['role', 'onboard_step'])
      .where('id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    return row?.role === 'free' || row?.role === 'premium'
      ? { role: row.role, onboardStep: row.onboard_step }
      : null;
  }

  async replaceOnboardingCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    code: string,
    createdAt: Date,
  ): Promise<void> {
    await transaction
      .deleteFrom('mymoneymap.user_currencies')
      .where('user_id', '=', userId)
      .execute();
    await transaction
      .insertInto('mymoneymap.user_currencies')
      .values({ user_id: userId, code, is_main: true, created_at: createdAt })
      .execute();
    await this.advanceCurrencyOnboarding(transaction, userId, createdAt);
  }

  async advanceCurrencyOnboarding(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    updatedAt: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.users')
      .set((expression) => ({
        onboard_step: expression.fn('greatest', ['onboard_step', expression.val(4)]),
        updated_at: updatedAt,
      }))
      .where('id', '=', userId)
      .execute();
  }

  async addUserCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    code: string,
    createdAt: Date,
  ): Promise<boolean> {
    const row = await transaction
      .insertInto('mymoneymap.user_currencies')
      .values({ user_id: userId, code, is_main: false, created_at: createdAt })
      .onConflict((conflict) => conflict.columns(['user_id', 'code']).doNothing())
      .returning('code')
      .executeTakeFirst();
    if (row) {
      await this.advanceCurrencyOnboarding(transaction, userId, createdAt);
    }
    return row !== undefined;
  }

  async removeUserCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    code: string,
  ): Promise<boolean> {
    const row = await transaction
      .deleteFrom('mymoneymap.user_currencies')
      .where('user_id', '=', userId)
      .where('code', '=', code)
      .where('is_main', '=', false)
      .returning('code')
      .executeTakeFirst();
    return row !== undefined;
  }

  async setMainCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    code: string,
  ): Promise<boolean> {
    const membership = await transaction
      .selectFrom('mymoneymap.user_currencies')
      .select('code')
      .where('user_id', '=', userId)
      .where('code', '=', code)
      .executeTakeFirst();
    if (!membership) return false;
    await transaction
      .updateTable('mymoneymap.user_currencies')
      .set({ is_main: false })
      .where('user_id', '=', userId)
      .where('is_main', '=', true)
      .execute();
    await transaction
      .updateTable('mymoneymap.user_currencies')
      .set({ is_main: true })
      .where('user_id', '=', userId)
      .where('code', '=', code)
      .execute();
    return true;
  }

  async mainCurrency(userId: string, executor: Executor = this.database): Promise<Currency | null> {
    const row = await executor
      .selectFrom('mymoneymap.user_currencies as uc')
      .innerJoin('mymoneymap.currencies as c', 'c.code', 'uc.code')
      .select(['c.code', 'c.name', 'c.minor_unit', 'c.rounding_mode'])
      .where('uc.user_id', '=', userId)
      .where('uc.is_main', '=', true)
      .executeTakeFirst();
    return row
      ? {
          code: row.code,
          name: row.name,
          minorUnit: row.minor_unit,
          roundingMode: row.rounding_mode,
        }
      : null;
  }

  async countUserCurrencies(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<number> {
    const row = await transaction
      .selectFrom('mymoneymap.user_currencies')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async quoteAsOf(
    currency: string,
    asOf: string,
    provider = 'frankfurter',
    executor: Executor = this.database,
  ): Promise<ObservedFxQuote | null> {
    const row = await executor
      .selectFrom('mymoneymap.fx_quotes')
      .selectAll()
      .where('provider', '=', provider)
      .where('base_code', '=', 'EUR')
      .where('quote_code', '=', currency)
      .where('observed_on', '<=', asOf)
      .where('status', '=', 'available')
      .orderBy('observed_on', 'desc')
      .orderBy('fetched_at', 'desc')
      .executeTakeFirst();
    return row
      ? {
          id: row.id,
          provider: row.provider,
          baseCurrency: 'EUR',
          quoteCurrency: row.quote_code,
          rate: row.rate,
          observedOn: dateText(row.observed_on),
          observedAt: row.observed_at,
          fetchedAt: row.fetched_at,
        }
      : null;
  }

  async storeQuote(quote: ProviderFxQuote): Promise<void> {
    await this.database
      .insertInto('mymoneymap.fx_quotes')
      .values({
        id: randomUUID(),
        provider: quote.provider,
        base_code: 'EUR',
        quote_code: quote.quoteCurrency,
        rate: quote.rate,
        observed_on: quote.observedOn,
        observed_at: quote.observedAt,
        fetched_at: quote.fetchedAt,
        quality: 'provider_observed',
        status: 'available',
      })
      .onConflict((conflict) =>
        conflict.columns(['provider', 'base_code', 'quote_code', 'observed_on']).doUpdateSet({
          rate: quote.rate,
          observed_at: quote.observedAt,
          fetched_at: quote.fetchedAt,
          quality: 'provider_observed',
          status: 'available',
        }),
      )
      .execute();
  }

  async insertSnapshot(
    transaction: Transaction<DatabaseSchema>,
    entryId: string,
    userId: string,
    result: FxConversionResult,
    createdAt: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.fx_conversion_snapshots')
      .values({
        id: randomUUID(),
        entry_id: entryId,
        user_id: userId,
        source_currency: result.sourceCurrency,
        target_currency: result.targetCurrency,
        source_amount: result.sourceAmount,
        converted_amount: result.convertedAmount ?? null,
        source_rate: result.sourceRate ?? null,
        target_rate: result.targetRate ?? null,
        conversion_rate: result.conversionRate ?? null,
        source_quote_id: result.sourceQuoteId ?? null,
        target_quote_id: result.targetQuoteId ?? null,
        provider: result.provider ?? null,
        rate_at: result.rateAt ? new Date(result.rateAt) : null,
        fetched_at: result.fetchedAt ? new Date(result.fetchedAt) : null,
        status: result.status,
        precision: result.precision,
        rounding_mode: result.roundingMode,
        created_at: createdAt,
      })
      .execute();
  }

  async copySnapshot(
    transaction: Transaction<DatabaseSchema>,
    originalEntryId: string,
    newEntryId: string,
    userId: string,
    createdAt: Date,
  ): Promise<void> {
    const source = await transaction
      .selectFrom('mymoneymap.fx_conversion_snapshots')
      .selectAll()
      .where('entry_id', '=', originalEntryId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    await transaction
      .insertInto('mymoneymap.fx_conversion_snapshots')
      .values({
        ...source,
        id: randomUUID(),
        entry_id: newEntryId,
        created_at: createdAt,
      })
      .execute();
  }
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}
