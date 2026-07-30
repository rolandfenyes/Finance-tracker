/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { type Kysely, type Selectable, type Transaction } from 'kysely';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import type { JsonValue } from '../platform/events/outbox.port';
import type { FifoProjection, FifoTrade } from './securities-calculator';
import type {
  ImportPreviewRow,
  ProviderDailyPrice,
  ProviderInstrumentMetadata,
  ProviderQuote,
  SecuritiesInstrument,
  SecuritiesQuote,
  SecuritiesTrade,
  TradeSide,
} from './securities.types';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface TradeWrite {
  id: string;
  userId: string;
  positionId: string;
  instrumentId: string;
  side: TradeSide;
  quantity: string;
  unitPrice: string;
  fee: string;
  currency: string;
  notional: string;
  notionalBase: string;
  feeBase: string;
  baseCurrency: string;
  conversionStatus: 'available' | 'stale';
  conversionRate: string;
  conversionProvider: string;
  rateAt: Date;
  fetchedAt: Date;
  executedAt: Date;
  tradedOn: string;
  note: string | null;
  cashJournalEntryId: string;
  feeJournalEntryId: string | null;
  createdAt: Date;
}

@Injectable()
export class SecuritiesRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(
    work: (transaction: Transaction<DatabaseSchema>) => Promise<T>,
    isolation: 'read committed' | 'serializable' = 'read committed',
  ): Promise<T> {
    return this.database.transaction().setIsolationLevel(isolation).execute(work);
  }

  async user(
    userId: string,
    executor: Executor,
    lock = false,
  ): Promise<{ role: 'free' | 'premium' | 'admin' } | null> {
    let query = executor.selectFrom('mymoneymap.users').select('role').where('id', '=', userId);
    if (lock) query = query.forUpdate();
    return (await query.executeTakeFirst()) ?? null;
  }

  currencyOwned(userId: string, currency: string, executor: Executor): Promise<boolean> {
    return executor
      .selectFrom('mymoneymap.user_currencies')
      .select('code')
      .where('user_id', '=', userId)
      .where('code', '=', currency)
      .executeTakeFirst()
      .then(Boolean);
  }

  mainCurrency(
    userId: string,
    executor: Executor,
  ): Promise<{
    code: string;
    minorUnit: number;
    roundingMode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN';
  }> {
    return executor
      .selectFrom('mymoneymap.user_currencies as uc')
      .innerJoin('mymoneymap.currencies as c', 'c.code', 'uc.code')
      .select(['uc.code', 'c.minor_unit as minorUnit', 'c.rounding_mode as roundingMode'])
      .where('uc.user_id', '=', userId)
      .where('uc.is_main', '=', true)
      .executeTakeFirstOrThrow();
  }

  defaultCashAccount(transaction: Transaction<DatabaseSchema>, userId: string): Promise<string> {
    return transaction
      .selectFrom('mymoneymap.ledger_accounts')
      .select('id')
      .where('user_id', '=', userId)
      .where('kind', '=', 'cash')
      .executeTakeFirstOrThrow()
      .then(({ id }) => id);
  }

  portfolio(
    userId: string,
    executor: Executor = this.database,
  ): Promise<{ id: string; cashAccountId: string } | null> {
    return executor
      .selectFrom('mymoneymap.securities_portfolios')
      .select(['id', 'cash_account_id as cashAccountId'])
      .where('user_id', '=', userId)
      .executeTakeFirst()
      .then((row) => row ?? null);
  }

  async createPortfolio(
    transaction: Transaction<DatabaseSchema>,
    id: string,
    userId: string,
    cashAccountId: string,
    now: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.securities_portfolios')
      .values({ id, user_id: userId, cash_account_id: cashAccountId, created_at: now })
      .execute();
  }

  instrumentByIdentity(
    symbol: string,
    market: string,
    executor: Executor,
  ): Promise<SecuritiesInstrument | null> {
    return executor
      .selectFrom('mymoneymap.securities_instruments')
      .selectAll()
      .where('symbol', '=', symbol)
      .where('market', '=', market)
      .executeTakeFirst()
      .then((row) => (row ? instrument(row) : null));
  }

  instrumentById(
    instrumentId: string,
    executor: Executor = this.database,
  ): Promise<SecuritiesInstrument | null> {
    return executor
      .selectFrom('mymoneymap.securities_instruments')
      .selectAll()
      .where('id', '=', instrumentId)
      .executeTakeFirst()
      .then((row) => (row ? instrument(row) : null));
  }

  async createInstrument(
    transaction: Transaction<DatabaseSchema>,
    values: {
      id: string;
      symbol: string;
      market: string;
      exchange: string | null;
      name: string | null;
      currency: string;
      now: Date;
    },
  ): Promise<SecuritiesInstrument> {
    const row = await transaction
      .insertInto('mymoneymap.securities_instruments')
      .values({
        id: values.id,
        symbol: values.symbol,
        market: values.market,
        exchange: values.exchange,
        name: values.name,
        currency: values.currency,
        sector: null,
        industry: null,
        beta: null,
        metadata_provider: null,
        metadata_observed_at: null,
        active: true,
        created_at: values.now,
        updated_at: values.now,
      })
      .returningAll()
      .executeTakeFirstOrThrow();
    return instrument(row);
  }

  position(
    userId: string,
    instrumentId: string,
    executor: Executor = this.database,
    lock = false,
  ): Promise<{
    id: string;
    holdingAccountId: string;
    quantity: string;
    localCurrency: string;
    baseCurrency: string;
  } | null> {
    let query = executor
      .selectFrom('mymoneymap.securities_positions')
      .select([
        'id',
        'holding_account_id as holdingAccountId',
        'quantity',
        'local_currency as localCurrency',
        'base_currency as baseCurrency',
      ])
      .where('user_id', '=', userId)
      .where('instrument_id', '=', instrumentId);
    if (lock) query = query.forUpdate();
    return query.executeTakeFirst().then((row) => row ?? null);
  }

  async createPosition(
    transaction: Transaction<DatabaseSchema>,
    values: {
      id: string;
      userId: string;
      instrumentId: string;
      holdingAccountId: string;
      localCurrency: string;
      baseCurrency: string;
      now: Date;
    },
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.securities_positions')
      .values({
        id: values.id,
        user_id: values.userId,
        instrument_id: values.instrumentId,
        holding_account_id: values.holdingAccountId,
        quantity: '0',
        remaining_cost_local: '0',
        remaining_cost_base: '0',
        local_currency: values.localCurrency,
        base_currency: values.baseCurrency,
        created_at: values.now,
        updated_at: values.now,
      })
      .execute();
  }

  async insertTrade(transaction: Transaction<DatabaseSchema>, value: TradeWrite): Promise<void> {
    await transaction
      .insertInto('mymoneymap.securities_trades')
      .values({
        id: value.id,
        user_id: value.userId,
        position_id: value.positionId,
        instrument_id: value.instrumentId,
        side: value.side,
        quantity: value.quantity,
        unit_price: value.unitPrice,
        fee: value.fee,
        currency: value.currency,
        notional: value.notional,
        notional_base: value.notionalBase,
        fee_base: value.feeBase,
        base_currency: value.baseCurrency,
        conversion_status: value.conversionStatus,
        conversion_rate: value.conversionRate,
        conversion_provider: value.conversionProvider,
        rate_at: value.rateAt,
        fetched_at: value.fetchedAt,
        executed_at: value.executedAt,
        traded_on: value.tradedOn,
        note: value.note,
        cash_journal_entry_id: value.cashJournalEntryId,
        fee_journal_entry_id: value.feeJournalEntryId,
        reversed_by_cash_journal_entry_id: null,
        reversed_by_fee_journal_entry_id: null,
        created_at: value.createdAt,
      })
      .execute();
  }

  activeFifoTrades(userId: string, instrumentId: string, executor: Executor): Promise<FifoTrade[]> {
    return executor
      .selectFrom('mymoneymap.securities_trades')
      .select([
        'id',
        'side',
        'quantity',
        'notional',
        'fee',
        'notional_base',
        'fee_base',
        'currency',
        'base_currency',
        'executed_at',
      ])
      .where('user_id', '=', userId)
      .where('instrument_id', '=', instrumentId)
      .where('reversed_by_cash_journal_entry_id', 'is', null)
      .orderBy('executed_at')
      .orderBy('created_at')
      .orderBy('id')
      .execute()
      .then((rows) =>
        rows.map((row) => ({
          id: row.id,
          side: row.side,
          quantity: row.quantity,
          notional: row.notional,
          fee: row.fee,
          notionalBase: row.notional_base,
          feeBase: row.fee_base,
          currency: row.currency,
          baseCurrency: row.base_currency,
          executedAt: row.executed_at.toISOString(),
        })),
      );
  }

  async replaceProjection(
    transaction: Transaction<DatabaseSchema>,
    values: {
      userId: string;
      instrumentId: string;
      positionId: string;
      projection: FifoProjection;
      now: Date;
    },
  ): Promise<void> {
    await transaction
      .deleteFrom('mymoneymap.securities_lot_consumptions')
      .where(
        'lot_id',
        'in',
        transaction
          .selectFrom('mymoneymap.securities_lots')
          .select('id')
          .where('user_id', '=', values.userId)
          .where('position_id', '=', values.positionId),
      )
      .execute();
    await transaction
      .deleteFrom('mymoneymap.securities_realized_results')
      .where('user_id', '=', values.userId)
      .where('instrument_id', '=', values.instrumentId)
      .execute();
    await transaction
      .deleteFrom('mymoneymap.securities_lots')
      .where('user_id', '=', values.userId)
      .where('position_id', '=', values.positionId)
      .execute();

    const lotIds = new Map<string, string>();
    for (const lot of values.projection.lots) {
      const lotId = randomUUID();
      lotIds.set(lot.buyTradeId, lotId);
      await transaction
        .insertInto('mymoneymap.securities_lots')
        .values({
          id: lotId,
          user_id: values.userId,
          position_id: values.positionId,
          instrument_id: values.instrumentId,
          buy_trade_id: lot.buyTradeId,
          original_quantity: lot.originalQuantity,
          remaining_quantity: lot.remainingQuantity,
          total_cost_local: lot.totalCostLocal,
          total_cost_base: lot.totalCostBase,
          currency: lot.currency,
          base_currency: lot.baseCurrency,
          opened_at: new Date(lot.openedAt),
          created_at: values.now,
        })
        .execute();
    }
    for (const consumption of values.projection.consumptions) {
      await transaction
        .insertInto('mymoneymap.securities_lot_consumptions')
        .values({
          id: randomUUID(),
          user_id: values.userId,
          sell_trade_id: consumption.sellTradeId,
          lot_id: lotIds.get(consumption.buyTradeId)!,
          quantity: consumption.quantity,
          cost_local: consumption.costLocal,
          cost_base: consumption.costBase,
          created_at: values.now,
        })
        .execute();
    }
    for (const result of values.projection.realized) {
      await transaction
        .insertInto('mymoneymap.securities_realized_results')
        .values({
          id: randomUUID(),
          user_id: values.userId,
          instrument_id: values.instrumentId,
          sell_trade_id: result.sellTradeId,
          quantity: result.quantity,
          proceeds_local: result.proceedsLocal,
          cost_local: result.costLocal,
          fees_local: result.feesLocal,
          realized_local: result.realizedLocal,
          proceeds_base: result.proceedsBase,
          cost_base: result.costBase,
          fees_base: result.feesBase,
          realized_base: result.realizedBase,
          currency: result.currency,
          base_currency: result.baseCurrency,
          method: 'FIFO',
          closed_at: new Date(result.closedAt),
          created_at: values.now,
        })
        .execute();
    }
    await transaction
      .updateTable('mymoneymap.securities_positions')
      .set({
        quantity: values.projection.quantity,
        remaining_cost_local: values.projection.remainingCostLocal,
        remaining_cost_base: values.projection.remainingCostBase,
        updated_at: values.now,
      })
      .where('id', '=', values.positionId)
      .where('user_id', '=', values.userId)
      .executeTakeFirstOrThrow();
  }

  trade(
    userId: string,
    tradeId: string,
    executor: Executor,
    lock = false,
  ): Promise<SecuritiesTrade | null> {
    let query = executor
      .selectFrom('mymoneymap.securities_trades')
      .selectAll()
      .where('id', '=', tradeId)
      .where('user_id', '=', userId);
    if (lock) query = query.forUpdate();
    return query.executeTakeFirst().then((row) => (row ? trade(row) : null));
  }

  async markTradeReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    tradeId: string,
    cashReversalId: string,
    feeReversalId: string | null,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.securities_trades')
      .set({
        reversed_by_cash_journal_entry_id: cashReversalId,
        reversed_by_fee_journal_entry_id: feeReversalId,
      })
      .where('id', '=', tradeId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async insertCashMovement(
    transaction: Transaction<DatabaseSchema>,
    value: {
      id: string;
      userId: string;
      direction: 'deposit' | 'withdrawal';
      amount: string;
      currency: string;
      occurredOn: string;
      note: string | null;
      journalEntryId: string;
      createdAt: Date;
    },
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.securities_cash_movements')
      .values({
        id: value.id,
        user_id: value.userId,
        direction: value.direction,
        amount: value.amount,
        currency: value.currency,
        occurred_on: value.occurredOn,
        note: value.note,
        journal_entry_id: value.journalEntryId,
        reversed_by_journal_entry_id: null,
        created_at: value.createdAt,
      })
      .execute();
  }

  trades(userId: string, executor: Executor = this.database): Promise<SecuritiesTrade[]> {
    return executor
      .selectFrom('mymoneymap.securities_trades')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('executed_at', 'desc')
      .orderBy('id', 'desc')
      .execute()
      .then((rows) => rows.map(trade));
  }

  cashMovements(userId: string, executor: Executor = this.database) {
    return executor
      .selectFrom('mymoneymap.securities_cash_movements')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('occurred_on', 'desc')
      .orderBy('created_at', 'desc')
      .execute()
      .then((rows) =>
        rows.map((row) => ({
          id: row.id,
          direction: row.direction,
          amount: row.amount,
          currency: row.currency,
          occurredOn: databaseDate(row.occurred_on),
          note: row.note,
          journalEntryId: row.journal_entry_id,
          reversedByJournalEntryId: row.reversed_by_journal_entry_id,
          createdAt: row.created_at.toISOString(),
        })),
      );
  }

  positions(userId: string, executor: Executor = this.database) {
    return executor
      .selectFrom('mymoneymap.securities_positions as p')
      .innerJoin('mymoneymap.securities_instruments as i', 'i.id', 'p.instrument_id')
      .leftJoin('mymoneymap.securities_quotes as q', 'q.instrument_id', 'i.id')
      .select([
        'p.id',
        'p.instrument_id as instrumentId',
        'p.holding_account_id as holdingAccountId',
        'p.quantity',
        'p.remaining_cost_local as remainingCostLocal',
        'p.remaining_cost_base as remainingCostBase',
        'p.local_currency as localCurrency',
        'p.base_currency as baseCurrency',
        'i.symbol',
        'i.market',
        'i.exchange',
        'i.name',
        'i.sector',
        'i.industry',
        'q.last',
        'q.previous_close as previousClose',
        'q.currency as quoteCurrency',
        'q.provider as quoteProvider',
        'q.quote_at as quoteAt',
        'q.retrieved_at as quoteRetrievedAt',
        'q.status as quoteStatus',
      ])
      .where('p.user_id', '=', userId)
      .where('p.quantity', '>', '0')
      .orderBy('i.symbol')
      .orderBy('i.market')
      .execute();
  }

  realized(userId: string, executor: Executor = this.database) {
    return executor
      .selectFrom('mymoneymap.securities_realized_results')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('closed_at', 'desc')
      .execute();
  }

  async quote(
    instrumentId: string,
    executor: Executor = this.database,
  ): Promise<SecuritiesQuote | null> {
    const row = await executor
      .selectFrom('mymoneymap.securities_quotes')
      .selectAll()
      .where('instrument_id', '=', instrumentId)
      .executeTakeFirst();
    return row
      ? {
          status: row.status,
          last: row.last,
          previousClose: row.previous_close,
          dayHigh: row.day_high,
          dayLow: row.day_low,
          volume: row.volume,
          currency: row.currency,
          provider: row.provider,
          quoteAt: row.quote_at?.toISOString() ?? null,
          retrievedAt: row.retrieved_at.toISOString(),
        }
      : null;
  }

  prices(instrumentId: string, from: string, to: string, executor: Executor = this.database) {
    return executor
      .selectFrom('mymoneymap.securities_daily_prices')
      .selectAll()
      .where('instrument_id', '=', instrumentId)
      .where('trading_on', '>=', from)
      .where('trading_on', '<=', to)
      .orderBy('trading_on')
      .execute()
      .then((rows) =>
        rows.map((row) => ({
          tradingOn: databaseDate(row.trading_on),
          open: row.open,
          high: row.high,
          low: row.low,
          close: row.close,
          volume: row.volume,
          currency: row.currency,
          provider: row.provider,
          observedAt: row.observed_at.toISOString(),
          retrievedAt: row.retrieved_at.toISOString(),
        })),
      );
  }

  watchlist(userId: string, executor: Executor = this.database) {
    return executor
      .selectFrom('mymoneymap.securities_watchlist as w')
      .innerJoin('mymoneymap.securities_instruments as i', 'i.id', 'w.instrument_id')
      .leftJoin('mymoneymap.securities_quotes as q', 'q.instrument_id', 'i.id')
      .select([
        'i.id',
        'i.symbol',
        'i.market',
        'i.name',
        'i.currency',
        'q.last',
        'q.status',
        'q.quote_at as quoteAt',
        'q.retrieved_at as retrievedAt',
      ])
      .where('w.user_id', '=', userId)
      .orderBy('i.symbol')
      .orderBy('i.market')
      .execute();
  }

  async setWatch(userId: string, instrumentId: string, watched: boolean, now: Date): Promise<void> {
    if (watched) {
      await this.database
        .insertInto('mymoneymap.securities_watchlist')
        .values({ user_id: userId, instrument_id: instrumentId, created_at: now })
        .onConflict((conflict) => conflict.columns(['user_id', 'instrument_id']).doNothing())
        .execute();
    } else {
      await this.database
        .deleteFrom('mymoneymap.securities_watchlist')
        .where('user_id', '=', userId)
        .where('instrument_id', '=', instrumentId)
        .execute();
    }
  }

  async upsertQuote(quote: ProviderQuote): Promise<void> {
    const instrument = await this.instrumentByIdentity(
      quote.identity.symbol,
      quote.identity.market,
      this.database,
    );
    if (!instrument) throw new Error('Provider quote instrument was not found');
    await this.database
      .insertInto('mymoneymap.securities_quotes')
      .values({
        instrument_id: instrument.id,
        last: quote.last,
        previous_close: quote.previousClose,
        day_high: quote.dayHigh,
        day_low: quote.dayLow,
        volume: quote.volume,
        currency: quote.currency,
        provider: quote.provider,
        quote_at: quote.quoteAt ? new Date(quote.quoteAt) : null,
        retrieved_at: new Date(quote.retrievedAt),
        status: quote.status,
      })
      .onConflict((conflict) =>
        conflict.column('instrument_id').doUpdateSet({
          last: quote.last,
          previous_close: quote.previousClose,
          day_high: quote.dayHigh,
          day_low: quote.dayLow,
          volume: quote.volume,
          currency: quote.currency,
          provider: quote.provider,
          quote_at: quote.quoteAt ? new Date(quote.quoteAt) : null,
          retrieved_at: new Date(quote.retrievedAt),
          status: quote.status,
        }),
      )
      .execute();
  }

  async upsertPrices(prices: ProviderDailyPrice[]): Promise<void> {
    for (const price of prices) {
      const instrument = await this.instrumentByIdentity(
        price.identity.symbol,
        price.identity.market,
        this.database,
      );
      if (!instrument) throw new Error('Provider price instrument was not found');
      await this.database
        .insertInto('mymoneymap.securities_daily_prices')
        .values({
          id: randomUUID(),
          instrument_id: instrument.id,
          trading_on: price.tradingOn,
          open: price.open,
          high: price.high,
          low: price.low,
          close: price.close,
          volume: price.volume,
          currency: price.currency,
          provider: price.provider,
          observed_at: new Date(price.observedAt),
          retrieved_at: new Date(price.retrievedAt),
        })
        .onConflict((conflict) =>
          conflict.columns(['instrument_id', 'trading_on', 'provider']).doUpdateSet({
            open: price.open,
            high: price.high,
            low: price.low,
            close: price.close,
            volume: price.volume,
            currency: price.currency,
            observed_at: new Date(price.observedAt),
            retrieved_at: new Date(price.retrievedAt),
          }),
        )
        .execute();
    }
  }

  async updateMetadata(metadata: ProviderInstrumentMetadata): Promise<void> {
    await this.database
      .updateTable('mymoneymap.securities_instruments')
      .set({
        exchange: metadata.exchange,
        name: metadata.name,
        currency: metadata.currency,
        sector: metadata.sector,
        industry: metadata.industry,
        beta: metadata.beta,
        metadata_provider: metadata.provider,
        metadata_observed_at: new Date(metadata.observedAt),
        updated_at: new Date(metadata.observedAt),
      })
      .where('symbol', '=', metadata.identity.symbol)
      .where('market', '=', metadata.identity.market)
      .execute();
  }

  async storeImport(userId: string, fingerprint: string, rows: ImportPreviewRow[], now: Date) {
    const counts = importCounts(rows);
    const existing = await this.database
      .selectFrom('mymoneymap.securities_imports')
      .selectAll()
      .where('user_id', '=', userId)
      .where('fingerprint', '=', fingerprint)
      .executeTakeFirst();
    if (existing) return importRecord(existing);
    const inserted = await this.database
      .insertInto('mymoneymap.securities_imports')
      .values({
        id: randomUUID(),
        user_id: userId,
        fingerprint,
        status: 'preview',
        row_count: rows.length,
        valid_count: counts.valid,
        error_count: counts.error,
        ignored_count: counts.ignored,
        rows: JSON.stringify(rows) as unknown as JsonValue,
        committed_at: null,
        created_at: now,
      })
      .onConflict((conflict) => conflict.columns(['user_id', 'fingerprint']).doNothing())
      .returningAll()
      .executeTakeFirst();
    if (inserted) return importRecord(inserted);
    const duplicate = await this.database
      .selectFrom('mymoneymap.securities_imports')
      .selectAll()
      .where('user_id', '=', userId)
      .where('fingerprint', '=', fingerprint)
      .executeTakeFirstOrThrow();
    return importRecord(duplicate);
  }

  async importForCommit(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    importId: string,
  ) {
    const row = await transaction
      .selectFrom('mymoneymap.securities_imports')
      .selectAll()
      .where('id', '=', importId)
      .where('user_id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    return row ? importRecord(row) : null;
  }

  async markImportCommitted(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    importId: string,
    now: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.securities_imports')
      .set({ status: 'committed', committed_at: now })
      .where('id', '=', importId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async instrumentsForRefresh(userId: string, requested: string[]) {
    let query = this.database
      .selectFrom('mymoneymap.securities_instruments as i')
      .leftJoin('mymoneymap.securities_positions as p', (join) =>
        join.onRef('p.instrument_id', '=', 'i.id').on('p.user_id', '=', userId),
      )
      .leftJoin('mymoneymap.securities_watchlist as w', (join) =>
        join.onRef('w.instrument_id', '=', 'i.id').on('w.user_id', '=', userId),
      )
      .select(['i.id', 'i.symbol', 'i.market', 'i.currency'])
      .where((expression) =>
        expression.or([
          expression('p.id', 'is not', null),
          expression('w.user_id', 'is not', null),
        ]),
      );
    if (requested.length > 0) query = query.where('i.id', 'in', requested);
    return query.distinct().orderBy('i.id').execute();
  }

  async createRefreshJob(
    userId: string,
    jobId: string,
    queueJobId: string,
    now: Date,
  ): Promise<void> {
    await this.database
      .insertInto('mymoneymap.securities_refresh_jobs')
      .values({
        id: jobId,
        user_id: userId,
        queue_job_id: queueJobId,
        status: 'queued',
        attempt_count: 0,
        max_attempts: 3,
        error_code: null,
        created_at: now,
        started_at: null,
        finished_at: null,
      })
      .execute();
  }

  updateRefreshJob(
    jobId: string,
    values: {
      status: 'running' | 'completed' | 'retryable_failed' | 'dead_letter';
      attemptCount: number;
      errorCode: string | null;
      startedAt?: Date;
      finishedAt?: Date;
    },
  ): Promise<unknown> {
    return this.database
      .updateTable('mymoneymap.securities_refresh_jobs')
      .set({
        status: values.status,
        attempt_count: values.attemptCount,
        error_code: values.errorCode,
        ...(values.startedAt ? { started_at: values.startedAt } : {}),
        ...(values.finishedAt ? { finished_at: values.finishedAt } : {}),
      })
      .where('id', '=', jobId)
      .executeTakeFirstOrThrow();
  }

  refreshJob(userId: string, jobId: string) {
    return this.database
      .selectFrom('mymoneymap.securities_refresh_jobs')
      .selectAll()
      .where('id', '=', jobId)
      .where('user_id', '=', userId)
      .executeTakeFirst()
      .then((row) =>
        row
          ? {
              id: row.id,
              status: row.status,
              attemptCount: row.attempt_count,
              maxAttempts: row.max_attempts,
              errorCode: row.error_code,
              createdAt: row.created_at.toISOString(),
              startedAt: row.started_at?.toISOString() ?? null,
              finishedAt: row.finished_at?.toISOString() ?? null,
            }
          : null,
      );
  }

  async activeTradesForUser(userId: string, executor: Executor) {
    return executor
      .selectFrom('mymoneymap.securities_trades')
      .selectAll()
      .where('user_id', '=', userId)
      .where('reversed_by_cash_journal_entry_id', 'is', null)
      .orderBy('executed_at', 'desc')
      .forUpdate()
      .execute();
  }

  activeCashForUser(userId: string, executor: Executor) {
    return executor
      .selectFrom('mymoneymap.securities_cash_movements')
      .selectAll()
      .where('user_id', '=', userId)
      .where('reversed_by_journal_entry_id', 'is', null)
      .forUpdate()
      .execute();
  }

  async markCashReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    movementId: string,
    reversalId: string,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.securities_cash_movements')
      .set({ reversed_by_journal_entry_id: reversalId })
      .where('id', '=', movementId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async clearProjection(transaction: Transaction<DatabaseSchema>, userId: string, now: Date) {
    const positions = await transaction
      .selectFrom('mymoneymap.securities_positions')
      .select(['id', 'instrument_id'])
      .where('user_id', '=', userId)
      .execute();
    for (const position of positions) {
      await this.replaceProjection(transaction, {
        userId,
        instrumentId: position.instrument_id,
        positionId: position.id,
        projection: {
          quantity: '0',
          remainingCostLocal: '0',
          remainingCostBase: '0',
          currency: null,
          baseCurrency: null,
          lots: [],
          consumptions: [],
          realized: [],
        },
        now,
      });
    }
  }

  async createClearRequest(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    tradeCount: number,
    cashCount: number,
    now: Date,
  ) {
    const id = randomUUID();
    await transaction
      .insertInto('mymoneymap.securities_clear_requests')
      .values({
        id,
        user_id: userId,
        status: 'completed',
        trade_count: tradeCount,
        cash_count: cashCount,
        created_at: now,
        completed_at: now,
      })
      .execute();
    return {
      id,
      status: 'completed' as const,
      tradeCount,
      cashCount,
      completedAt: now.toISOString(),
    };
  }
}

function instrument(
  row: Selectable<DatabaseSchema['mymoneymap.securities_instruments']>,
): SecuritiesInstrument {
  return {
    id: row.id,
    symbol: row.symbol,
    market: row.market,
    exchange: row.exchange,
    name: row.name,
    currency: row.currency,
    sector: row.sector,
    industry: row.industry,
    beta: row.beta,
    metadata: {
      provider: row.metadata_provider,
      observedAt: row.metadata_observed_at?.toISOString() ?? null,
    },
    active: row.active,
  };
}

function trade(row: Selectable<DatabaseSchema['mymoneymap.securities_trades']>): SecuritiesTrade {
  return {
    id: row.id,
    instrumentId: row.instrument_id,
    side: row.side,
    quantity: row.quantity,
    unitPrice: row.unit_price,
    fee: row.fee,
    currency: row.currency,
    notional: row.notional,
    notionalBase: row.notional_base,
    feeBase: row.fee_base,
    baseCurrency: row.base_currency,
    conversion: {
      status: row.conversion_status,
      rate: row.conversion_rate,
      provider: row.conversion_provider,
      rateAt: row.rate_at.toISOString(),
      fetchedAt: row.fetched_at.toISOString(),
    },
    executedAt: row.executed_at.toISOString(),
    tradedOn: databaseDate(row.traded_on),
    note: row.note,
    cashJournalEntryId: row.cash_journal_entry_id,
    feeJournalEntryId: row.fee_journal_entry_id,
    reversedByCashJournalEntryId: row.reversed_by_cash_journal_entry_id,
    reversedByFeeJournalEntryId: row.reversed_by_fee_journal_entry_id,
  };
}

function importCounts(rows: ImportPreviewRow[]) {
  return {
    valid: rows.filter(({ status }) => status === 'valid').length,
    error: rows.filter(({ status }) => status === 'error').length,
    ignored: rows.filter(({ status }) => status === 'ignored').length,
  };
}

function importRecord(row: Selectable<DatabaseSchema['mymoneymap.securities_imports']>) {
  return {
    id: row.id,
    fingerprint: row.fingerprint,
    status: row.status,
    rowCount: row.row_count,
    validCount: row.valid_count,
    errorCount: row.error_count,
    ignoredCount: row.ignored_count,
    rows: row.rows as unknown as ImportPreviewRow[],
    committedAt: row.committed_at?.toISOString() ?? null,
    createdAt: row.created_at.toISOString(),
  };
}

function databaseDate(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    value.getFullYear().toString().padStart(4, '0'),
    (value.getMonth() + 1).toString().padStart(2, '0'),
    value.getDate().toString().padStart(2, '0'),
  ].join('-');
}
