/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Transaction } from 'kysely';
import { FxConversionService } from '../currency/fx-conversion.service';
import { LedgerRepository } from '../ledger/ledger.repository';
import type { DatabaseSchema } from '../platform/database/database.types';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import { ApplicationError } from '../platform/http/application-error';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import type {
  CreateSecuritiesCashMovementDto,
  CreateSecuritiesTradeDto,
  PreviewSecuritiesImportDto,
  ReverseSecuritiesTradeDto,
} from './securities.dto';
import { rebuildFifo, technicalIndicators } from './securities-calculator';
import { previewSecuritiesCsv } from './securities-import';
import { SecuritiesRepository } from './securities.repository';
import type { ImportPreviewRow, SecuritiesInstrument } from './securities.types';

@Injectable()
export class SecuritiesService {
  constructor(
    @Inject(SecuritiesRepository) private readonly repository: SecuritiesRepository,
    @Inject(LedgerRepository) private readonly ledger: LedgerRepository,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async portfolio(userId: string) {
    const positions = await this.repository.positions(userId);
    const items = [];
    let valuedBase = ExactDecimal.create('0');
    for (const position of positions) {
      const quote =
        position.quoteStatus && position.last
          ? {
              status: position.quoteStatus,
              last: position.last,
              currency: position.quoteCurrency,
              provider: position.quoteProvider,
              quoteAt: position.quoteAt?.toISOString() ?? null,
              retrievedAt: position.quoteRetrievedAt?.toISOString() ?? null,
            }
          : { status: 'unavailable' as const, last: null };
      let marketValueLocal: string | null = null;
      let marketValueBase: string | null = null;
      let valuationConversion: object | null = null;
      if (quote.last && position.quoteCurrency) {
        marketValueLocal = ExactDecimal.create(position.quantity)
          .multiply(ExactDecimal.create(quote.last))
          .toString();
        const conversion = await this.fx.convertObserved(
          marketValueLocal,
          position.quoteCurrency,
          position.baseCurrency,
          this.clock.now().toString().slice(0, 10),
        );
        valuationConversion = conversion;
        if (conversion.status !== 'unavailable' && conversion.convertedAmount) {
          marketValueBase = conversion.convertedAmount;
          valuedBase = valuedBase.add(ExactDecimal.create(marketValueBase));
        }
      }
      items.push({
        ...position,
        quote,
        marketValueLocal,
        marketValueBase,
        valuationConversion,
      });
    }
    return {
      positions: items.map((item) => {
        const allocationPercent =
          item.marketValueBase && valuedBase.isPositive()
            ? ExactDecimal.create(item.marketValueBase)
                .multiply(ExactDecimal.create('100'))
                .divide(valuedBase, RoundingPolicy.create(6, 'HALF_EVEN'))
                .toString()
            : null;
        return {
          ...item,
          allocationPercent,
          concentrationStatus:
            allocationPercent === null
              ? 'not_evaluated'
              : technicalIndicators([], allocationPercent).concentrationStatus,
        };
      }),
      valuedMarketValueBase: valuedBase.toString(),
      valuationStatus: items.some(({ marketValueBase }) => marketValueBase === null)
        ? 'partial'
        : 'available',
    };
  }

  async activity(userId: string) {
    const [trades, cashMovements, realized] = await Promise.all([
      this.repository.trades(userId),
      this.repository.cashMovements(userId),
      this.repository.realized(userId),
    ]);
    return { trades, cashMovements, realized };
  }

  trade(userId: string, dto: CreateSecuritiesTradeDto) {
    return this.repository
      .transaction((transaction) => this.postTrade(transaction, userId, dto))
      .catch(translateSecuritiesError);
  }

  async reverseTrade(userId: string, tradeId: string, dto: ReverseSecuritiesTradeDto) {
    CalendarDate.create(dto.postedOn);
    return this.repository
      .transaction(async (transaction) => {
        const original = await this.repository.trade(userId, tradeId, transaction, true);
        if (!original) throw notFound('Trade was not found');
        if (original.reversedByCashJournalEntryId) throw conflict('Trade is already reversed');
        await this.repository.position(userId, original.instrumentId, transaction, true);
        const now = this.clock.now().toDate();
        const cash = await this.ledger.findOwnedEntry(
          transaction,
          userId,
          original.cashJournalEntryId,
        );
        const cashReversal = await this.ledger.reverse(transaction, cash, {
          userId,
          actorUserId: userId,
          postedOn: dto.postedOn,
          effectiveAt: now,
          createdAt: now,
          note: dto.note,
          idempotencyKeyHash: digest(`trade-reversal:${tradeId}:${randomUUID()}`),
          sourceModule: 'securities',
          sourceReferenceId: tradeId,
        });
        await this.fx.copyReversalSnapshot(transaction, cash.id, cashReversal.id, userId, now);
        let feeReversalId: string | null = null;
        if (original.feeJournalEntryId) {
          const fee = await this.ledger.findOwnedEntry(
            transaction,
            userId,
            original.feeJournalEntryId,
          );
          const reversal = await this.ledger.reverse(transaction, fee, {
            userId,
            actorUserId: userId,
            postedOn: dto.postedOn,
            effectiveAt: now,
            createdAt: now,
            note: dto.note,
            idempotencyKeyHash: digest(`fee-reversal:${tradeId}:${randomUUID()}`),
            sourceModule: 'securities',
            sourceReferenceId: tradeId,
          });
          await this.fx.copyReversalSnapshot(transaction, fee.id, reversal.id, userId, now);
          feeReversalId = reversal.id;
        }
        await this.repository.markTradeReversed(
          transaction,
          userId,
          tradeId,
          cashReversal.id,
          feeReversalId,
        );
        const position = await this.repository.position(userId, original.instrumentId, transaction);
        const projection = safeRebuild(
          await this.repository.activeFifoTrades(userId, original.instrumentId, transaction),
        );
        await this.repository.replaceProjection(transaction, {
          userId,
          instrumentId: original.instrumentId,
          positionId: position!.id,
          projection,
          now,
        });
        return { tradeId, reversedByCashJournalEntryId: cashReversal.id, feeReversalId };
      })
      .catch(translateSecuritiesError);
  }

  cashMovement(userId: string, dto: CreateSecuritiesCashMovementDto) {
    CalendarDate.create(dto.occurredOn);
    if (!ExactDecimal.create(dto.amount).isPositive()) {
      throw semantic('Cash movement amount must be greater than zero');
    }
    return this.repository.transaction(async (transaction) => {
      await this.assertCurrency(userId, dto.currency, transaction);
      const portfolio = await this.ensurePortfolio(userId, transaction);
      const defaultCash = await this.repository.defaultCashAccount(transaction, userId);
      const now = this.clock.now().toDate();
      const id = randomUUID();
      const entry = await this.ledger.post(transaction, {
        userId,
        actorUserId: userId,
        economicType: 'internal_transfer',
        amount: dto.amount,
        currency: dto.currency,
        postedOn: dto.occurredOn,
        effectiveAt: now,
        createdAt: now,
        sourceAccountId: dto.direction === 'deposit' ? defaultCash : portfolio.cashAccountId,
        destinationAccountId: dto.direction === 'deposit' ? portfolio.cashAccountId : defaultCash,
        note: dto.note,
        sourceModule: 'securities',
        sourceReferenceId: id,
        idempotencyKeyHash: digest(`cash:${id}`),
      });
      await this.fx.snapshotPostedEntry(transaction, entry, userId, dto.occurredOn, now);
      await this.repository.insertCashMovement(transaction, {
        id,
        userId,
        direction: dto.direction,
        amount: dto.amount,
        currency: dto.currency,
        occurredOn: dto.occurredOn,
        note: dto.note?.trim() ?? null,
        journalEntryId: entry.id,
        createdAt: now,
      });
      return { id, journalEntryId: entry.id };
    });
  }

  previewImport(userId: string, dto: PreviewSecuritiesImportDto) {
    const preview = previewSecuritiesCsv(dto.csv, dto.defaultMarket);
    return this.repository.storeImport(
      userId,
      preview.fingerprint,
      preview.rows,
      this.clock.now().toDate(),
    );
  }

  commitImport(userId: string, importId: string) {
    return this.repository.transaction(async (transaction) => {
      const value = await this.repository.importForCommit(transaction, userId, importId);
      if (!value) throw notFound('Import preview was not found');
      if (value.status === 'committed') return value;
      if (value.errorCount > 0) throw semantic('Import contains invalid rows');
      for (const row of value.rows) {
        if (row.status !== 'valid') continue;
        if (row.trade) {
          await this.postTrade(transaction, userId, {
            ...row.trade,
            note: row.trade.note ?? undefined,
          });
        }
        if (row.cash) await this.postCashImport(transaction, userId, row.cash);
      }
      const now = this.clock.now().toDate();
      await this.repository.markImportCommitted(transaction, userId, importId, now);
      return { ...value, status: 'committed', committedAt: now.toISOString() };
    });
  }

  async instrument(userId: string, instrumentId: string) {
    const instrument = await this.ownedInstrument(userId, instrumentId);
    return {
      instrument,
      quote: await this.repository.quote(instrumentId),
      watched: (await this.repository.watchlist(userId)).some(({ id }) => id === instrumentId),
    };
  }

  async prices(userId: string, instrumentId: string, from: string, to: string) {
    CalendarDate.create(from);
    CalendarDate.create(to);
    if (from > to) throw new ApplicationError(400, 'BAD_REQUEST', 'from must not follow to');
    await this.ownedInstrument(userId, instrumentId);
    const prices = await this.repository.prices(instrumentId, from, to);
    return {
      items: prices,
      indicators: technicalIndicators(prices.map(({ close }) => close)),
    };
  }

  async quote(userId: string, instrumentId: string) {
    await this.ownedInstrument(userId, instrumentId);
    return (
      (await this.repository.quote(instrumentId)) ?? {
        status: 'unavailable',
        last: null,
        quoteAt: null,
        retrievedAt: null,
      }
    );
  }

  async watch(userId: string, instrumentId: string, watched: boolean) {
    const instrument = await this.repository.instrumentById(instrumentId);
    if (!instrument) throw notFound('Instrument was not found');
    await this.repository.setWatch(userId, instrumentId, watched, this.clock.now().toDate());
    return { items: await this.repository.watchlist(userId) };
  }

  clear(userId: string, confirmation: 'CLEAR') {
    if (confirmation !== 'CLEAR') {
      throw new ApplicationError(400, 'BAD_REQUEST', 'Explicit CLEAR confirmation is required');
    }
    return this.repository
      .transaction(async (transaction) => {
        const now = this.clock.now().toDate();
        const postedOn = now.toISOString().slice(0, 10);
        const trades = await this.repository.activeTradesForUser(userId, transaction);
        const cashMovements = await this.repository.activeCashForUser(userId, transaction);
        for (const trade of trades) {
          const original = await this.ledger.findOwnedEntry(
            transaction,
            userId,
            trade.cash_journal_entry_id,
          );
          const reversal = await this.ledger.reverse(transaction, original, {
            userId,
            actorUserId: userId,
            postedOn,
            effectiveAt: now,
            createdAt: now,
            sourceModule: 'securities',
            sourceReferenceId: trade.id,
            idempotencyKeyHash: digest(`clear-trade:${trade.id}:${randomUUID()}`),
          });
          await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
          let feeReversalId: string | null = null;
          if (trade.fee_journal_entry_id) {
            const fee = await this.ledger.findOwnedEntry(
              transaction,
              userId,
              trade.fee_journal_entry_id,
            );
            const feeReversal = await this.ledger.reverse(transaction, fee, {
              userId,
              actorUserId: userId,
              postedOn,
              effectiveAt: now,
              createdAt: now,
              sourceModule: 'securities',
              sourceReferenceId: trade.id,
              idempotencyKeyHash: digest(`clear-fee:${trade.id}:${randomUUID()}`),
            });
            await this.fx.copyReversalSnapshot(transaction, fee.id, feeReversal.id, userId, now);
            feeReversalId = feeReversal.id;
          }
          await this.repository.markTradeReversed(
            transaction,
            userId,
            trade.id,
            reversal.id,
            feeReversalId,
          );
        }
        for (const cash of cashMovements) {
          const original = await this.ledger.findOwnedEntry(
            transaction,
            userId,
            cash.journal_entry_id,
          );
          const reversal = await this.ledger.reverse(transaction, original, {
            userId,
            actorUserId: userId,
            postedOn,
            effectiveAt: now,
            createdAt: now,
            sourceModule: 'securities',
            sourceReferenceId: cash.id,
            idempotencyKeyHash: digest(`clear-cash:${cash.id}:${randomUUID()}`),
          });
          await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
          await this.repository.markCashReversed(transaction, userId, cash.id, reversal.id);
        }
        await this.repository.clearProjection(transaction, userId, now);
        return this.repository.createClearRequest(
          transaction,
          userId,
          trades.length,
          cashMovements.length,
          now,
        );
      })
      .catch(translateSecuritiesError);
  }

  private async postTrade(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    dto: CreateSecuritiesTradeDto,
  ) {
    const executedAt = new Date(dto.executedAt);
    const tradedOn = executedAt.toISOString().slice(0, 10);
    const now = this.clock.now().toDate();
    const symbol = dto.symbol.trim().toUpperCase();
    const market = dto.market.trim().toUpperCase();
    await this.assertCurrency(userId, dto.currency, transaction);
    const main = await this.repository.mainCurrency(userId, transaction);
    const portfolio = await this.ensurePortfolio(userId, transaction);
    let instrument = await this.repository.instrumentByIdentity(symbol, market, transaction);
    if (!instrument) {
      instrument = await this.repository.createInstrument(transaction, {
        id: randomUUID(),
        symbol,
        market,
        exchange: dto.exchange?.trim() ?? null,
        name: dto.name?.trim() ?? null,
        currency: dto.currency,
        now,
      });
    }
    if (instrument.currency !== dto.currency) {
      throw semantic('Trade currency must match the canonical instrument currency');
    }
    let position = await this.repository.position(userId, instrument.id, transaction, true);
    if (!position) {
      const positionId = randomUUID();
      const holdingAccountId = await this.ledger.createModuleAccount(
        transaction,
        userId,
        'securities_holding',
        positionId,
        now,
      );
      await this.repository.createPosition(transaction, {
        id: positionId,
        userId,
        instrumentId: instrument.id,
        holdingAccountId,
        localCurrency: dto.currency,
        baseCurrency: main.code,
        now,
      });
      position = (await this.repository.position(userId, instrument.id, transaction, true))!;
    }
    const quantity = ExactDecimal.create(dto.quantity);
    const notional = quantity.multiply(ExactDecimal.create(dto.unitPrice));
    const fee = ExactDecimal.create(dto.fee ?? '0');
    const [notionalFx, feeFx] = await Promise.all([
      this.fx.convertObserved(notional.toString(), dto.currency, main.code, tradedOn),
      fee.isZero()
        ? Promise.resolve(null)
        : this.fx.convertObserved(fee.toString(), dto.currency, main.code, tradedOn),
    ]);
    if (
      notionalFx.status === 'unavailable' ||
      !notionalFx.convertedAmount ||
      (feeFx && (feeFx.status === 'unavailable' || !feeFx.convertedAmount))
    ) {
      throw semantic('Observed FX conversion is required for the trade date');
    }
    const id = randomUUID();
    const conversionRate = ExactDecimal.create(notionalFx.conversionRate!);
    const notionalBase = notional.multiply(conversionRate);
    const feeBase = fee.multiply(conversionRate);
    const entry = await this.ledger.post(transaction, {
      userId,
      actorUserId: userId,
      economicType: 'trade_cash',
      amount: notional.toString(),
      currency: dto.currency,
      postedOn: tradedOn,
      effectiveAt: executedAt,
      createdAt: now,
      sourceAccountId: dto.side === 'buy' ? portfolio.cashAccountId : position.holdingAccountId,
      destinationAccountId:
        dto.side === 'buy' ? position.holdingAccountId : portfolio.cashAccountId,
      note: dto.note,
      sourceModule: 'securities',
      sourceReferenceId: id,
      idempotencyKeyHash: digest(`trade:${id}`),
    });
    await this.fx.snapshotPostedEntry(transaction, entry, userId, tradedOn, now);
    let feeEntryId: string | null = null;
    if (!fee.isZero()) {
      const feeEntry = await this.ledger.post(transaction, {
        userId,
        actorUserId: userId,
        economicType: 'fee',
        amount: fee.toString(),
        currency: dto.currency,
        postedOn: tradedOn,
        effectiveAt: executedAt,
        createdAt: now,
        accountId: portfolio.cashAccountId,
        note: dto.note,
        sourceModule: 'securities',
        sourceReferenceId: id,
        idempotencyKeyHash: digest(`trade-fee:${id}`),
      });
      await this.fx.snapshotPostedEntry(transaction, feeEntry, userId, tradedOn, now);
      feeEntryId = feeEntry.id;
    }
    await this.repository.insertTrade(transaction, {
      id,
      userId,
      positionId: position.id,
      instrumentId: instrument.id,
      side: dto.side,
      quantity: quantity.toString(),
      unitPrice: ExactDecimal.create(dto.unitPrice).toString(),
      fee: fee.toString(),
      currency: dto.currency,
      notional: notional.toString(),
      notionalBase: notionalBase.toString(),
      feeBase: feeBase.toString(),
      baseCurrency: main.code,
      conversionStatus: notionalFx.status,
      conversionRate: notionalFx.conversionRate!,
      conversionProvider: notionalFx.provider!,
      rateAt: new Date(notionalFx.rateAt!),
      fetchedAt: new Date(notionalFx.fetchedAt!),
      executedAt,
      tradedOn,
      note: dto.note?.trim() ?? null,
      cashJournalEntryId: entry.id,
      feeJournalEntryId: feeEntryId,
      createdAt: now,
    });
    const projection = safeRebuild(
      await this.repository.activeFifoTrades(userId, instrument.id, transaction),
    );
    await this.repository.replaceProjection(transaction, {
      userId,
      instrumentId: instrument.id,
      positionId: position.id,
      projection,
      now,
    });
    return this.repository.trade(userId, id, transaction);
  }

  private async postCashImport(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    cash: NonNullable<ImportPreviewRow['cash']>,
  ) {
    await this.assertCurrency(userId, cash.currency, transaction);
    const portfolio = await this.ensurePortfolio(userId, transaction);
    const defaultCash = await this.repository.defaultCashAccount(transaction, userId);
    const now = this.clock.now().toDate();
    const id = randomUUID();
    const entry = await this.ledger.post(transaction, {
      userId,
      actorUserId: userId,
      economicType: 'internal_transfer',
      amount: cash.amount,
      currency: cash.currency,
      postedOn: cash.occurredOn,
      effectiveAt: now,
      createdAt: now,
      sourceAccountId: cash.direction === 'deposit' ? defaultCash : portfolio.cashAccountId,
      destinationAccountId: cash.direction === 'deposit' ? portfolio.cashAccountId : defaultCash,
      note: cash.note ?? undefined,
      sourceModule: 'securities',
      sourceReferenceId: id,
      idempotencyKeyHash: digest(`import-cash:${id}`),
    });
    await this.fx.snapshotPostedEntry(transaction, entry, userId, cash.occurredOn, now);
    await this.repository.insertCashMovement(transaction, {
      id,
      userId,
      direction: cash.direction,
      amount: cash.amount,
      currency: cash.currency,
      occurredOn: cash.occurredOn,
      note: cash.note,
      journalEntryId: entry.id,
      createdAt: now,
    });
  }

  private async ensurePortfolio(userId: string, transaction: Transaction<DatabaseSchema>) {
    const existing = await this.repository.portfolio(userId, transaction);
    if (existing) return existing;
    const now = this.clock.now().toDate();
    const id = randomUUID();
    const cashAccountId = await this.ledger.createModuleAccount(
      transaction,
      userId,
      'securities_cash',
      id,
      now,
    );
    await this.repository.createPortfolio(transaction, id, userId, cashAccountId, now);
    return { id, cashAccountId };
  }

  private async assertCurrency(
    userId: string,
    currency: string,
    transaction: Transaction<DatabaseSchema>,
  ) {
    if (!(await this.repository.currencyOwned(userId, currency, transaction))) {
      throw semantic('Currency is not enabled for this user');
    }
  }

  private async ownedInstrument(
    userId: string,
    instrumentId: string,
  ): Promise<SecuritiesInstrument> {
    const instrument = await this.repository.instrumentById(instrumentId);
    if (!instrument) throw notFound('Instrument was not found');
    const [position, watchlist] = await Promise.all([
      this.repository.position(userId, instrumentId),
      this.repository.watchlist(userId),
    ]);
    if (!position && !watchlist.some(({ id }) => id === instrumentId)) {
      throw notFound('Instrument was not found');
    }
    return instrument;
  }
}

function digest(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}

function notFound(message: string): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', message);
}

function conflict(message: string): ApplicationError {
  return new ApplicationError(409, 'CONFLICT', message);
}

function semantic(message: string): ApplicationError {
  return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', message);
}

function safeRebuild(trades: Parameters<typeof rebuildFifo>[0]) {
  try {
    return rebuildFifo(trades);
  } catch (error) {
    throw semantic(error instanceof Error ? error.message : 'FIFO projection failed');
  }
}

function translateSecuritiesError(error: unknown): never {
  if (error instanceof ApplicationError) throw error;
  if (
    typeof error === 'object' &&
    error !== null &&
    'code' in error &&
    ['23503', '23505', '23514', '40001'].includes(String(error.code))
  ) {
    throw semantic('Securities transaction violated an ownership or financial invariant');
  }
  throw error;
}
