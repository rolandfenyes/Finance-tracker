import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';

const MONEY_POLICY = RoundingPolicy.create(12, 'HALF_EVEN');
const RATIO_POLICY = RoundingPolicy.create(18, 'HALF_EVEN');
const ZERO = ExactDecimal.create('0');
const HUNDRED = ExactDecimal.create('100');

export interface FifoTrade {
  id: string;
  side: 'buy' | 'sell';
  quantity: string;
  notional: string;
  fee: string;
  notionalBase: string;
  feeBase: string;
  currency: string;
  baseCurrency: string;
  executedAt: string;
}

export interface FifoLot {
  buyTradeId: string;
  originalQuantity: string;
  remainingQuantity: string;
  totalCostLocal: string;
  totalCostBase: string;
  currency: string;
  baseCurrency: string;
  openedAt: string;
}

export interface FifoConsumption {
  sellTradeId: string;
  buyTradeId: string;
  quantity: string;
  costLocal: string;
  costBase: string;
}

export interface FifoRealizedResult {
  sellTradeId: string;
  quantity: string;
  proceedsLocal: string;
  costLocal: string;
  feesLocal: string;
  realizedLocal: string;
  proceedsBase: string;
  costBase: string;
  feesBase: string;
  realizedBase: string;
  currency: string;
  baseCurrency: string;
  closedAt: string;
}

export interface FifoProjection {
  quantity: string;
  remainingCostLocal: string;
  remainingCostBase: string;
  currency: string | null;
  baseCurrency: string | null;
  lots: FifoLot[];
  consumptions: FifoConsumption[];
  realized: FifoRealizedResult[];
}

export function rebuildFifo(trades: readonly FifoTrade[]): FifoProjection {
  const lots: Array<FifoLot & { remaining: ExactDecimal }> = [];
  const consumptions: FifoConsumption[] = [];
  const realized: FifoRealizedResult[] = [];
  let quantity = ZERO;
  let currency: string | null = null;
  let baseCurrency: string | null = null;

  for (const trade of trades) {
    const tradeQuantity = positive(trade.quantity, 'Trade quantity');
    const notional = positive(trade.notional, 'Trade notional');
    const fee = nonNegative(trade.fee, 'Trade fee');
    const notionalBase = positive(trade.notionalBase, 'Trade base notional');
    const feeBase = nonNegative(trade.feeBase, 'Trade base fee');
    currency ??= trade.currency;
    baseCurrency ??= trade.baseCurrency;
    if (currency !== trade.currency || baseCurrency !== trade.baseCurrency) {
      throw new Error('All trades for one position must use stable local and base currencies');
    }

    if (trade.side === 'buy') {
      lots.push({
        buyTradeId: trade.id,
        originalQuantity: tradeQuantity.toString(),
        remainingQuantity: tradeQuantity.toString(),
        remaining: tradeQuantity,
        totalCostLocal: notional.add(fee).round(MONEY_POLICY).toString(),
        totalCostBase: notionalBase.add(feeBase).round(MONEY_POLICY).toString(),
        currency: trade.currency,
        baseCurrency: trade.baseCurrency,
        openedAt: trade.executedAt,
      });
      quantity = quantity.add(tradeQuantity);
      continue;
    }

    if (quantity.compare(tradeQuantity) < 0) {
      throw new Error('Sell quantity exceeds available holdings');
    }
    let remaining = tradeQuantity;
    let costLocal = ZERO;
    let costBase = ZERO;
    for (const lot of lots) {
      if (remaining.isZero()) break;
      if (lot.remaining.isZero()) continue;
      const consumed = lot.remaining.compare(remaining) <= 0 ? lot.remaining : remaining;
      const original = ExactDecimal.create(lot.originalQuantity);
      const local = ExactDecimal.create(lot.totalCostLocal)
        .multiply(consumed)
        .divide(original, MONEY_POLICY);
      const base = ExactDecimal.create(lot.totalCostBase)
        .multiply(consumed)
        .divide(original, MONEY_POLICY);
      lot.remaining = lot.remaining.subtract(consumed);
      lot.remainingQuantity = lot.remaining.toString();
      remaining = remaining.subtract(consumed);
      costLocal = costLocal.add(local);
      costBase = costBase.add(base);
      consumptions.push({
        sellTradeId: trade.id,
        buyTradeId: lot.buyTradeId,
        quantity: consumed.toString(),
        costLocal: local.toString(),
        costBase: base.toString(),
      });
    }
    if (!remaining.isZero()) throw new Error('FIFO lots do not reconcile to the position quantity');
    quantity = quantity.subtract(tradeQuantity);
    realized.push({
      sellTradeId: trade.id,
      quantity: tradeQuantity.toString(),
      proceedsLocal: notional.toString(),
      costLocal: costLocal.round(MONEY_POLICY).toString(),
      feesLocal: fee.toString(),
      realizedLocal: notional.subtract(fee).subtract(costLocal).round(MONEY_POLICY).toString(),
      proceedsBase: notionalBase.toString(),
      costBase: costBase.round(MONEY_POLICY).toString(),
      feesBase: feeBase.toString(),
      realizedBase: notionalBase
        .subtract(feeBase)
        .subtract(costBase)
        .round(MONEY_POLICY)
        .toString(),
      currency: trade.currency,
      baseCurrency: trade.baseCurrency,
      closedAt: trade.executedAt,
    });
  }

  let remainingCostLocal = ZERO;
  let remainingCostBase = ZERO;
  for (const lot of lots) {
    const original = ExactDecimal.create(lot.originalQuantity);
    remainingCostLocal = remainingCostLocal.add(
      ExactDecimal.create(lot.totalCostLocal)
        .multiply(lot.remaining)
        .divide(original, MONEY_POLICY),
    );
    remainingCostBase = remainingCostBase.add(
      ExactDecimal.create(lot.totalCostBase).multiply(lot.remaining).divide(original, MONEY_POLICY),
    );
  }
  return {
    quantity: quantity.toString(),
    remainingCostLocal: remainingCostLocal.round(MONEY_POLICY).toString(),
    remainingCostBase: remainingCostBase.round(MONEY_POLICY).toString(),
    currency,
    baseCurrency,
    lots: lots.map((value) => ({
      buyTradeId: value.buyTradeId,
      originalQuantity: value.originalQuantity,
      remainingQuantity: value.remainingQuantity,
      totalCostLocal: value.totalCostLocal,
      totalCostBase: value.totalCostBase,
      currency: value.currency,
      baseCurrency: value.baseCurrency,
      openedAt: value.openedAt,
    })),
    consumptions,
    realized,
  };
}

export function technicalIndicators(
  closes: readonly string[],
  positionWeightPercent?: string,
): {
  sma20: string | null;
  sma50: string | null;
  rsi14: string | null;
  concentrationPercent: string | null;
  concentrationStatus: 'not_evaluated' | 'within_threshold' | 'above_15_percent';
} {
  const values = closes.map((value) => positive(value, 'Closing price'));
  const sma20 = movingAverage(values, 20);
  const sma50 = movingAverage(values, 50);
  const rsi14 = rsi(values, 14);
  const weight =
    positionWeightPercent === undefined
      ? null
      : nonNegative(positionWeightPercent, 'Position weight');
  return {
    sma20: sma20?.toString() ?? null,
    sma50: sma50?.toString() ?? null,
    rsi14: rsi14?.toString() ?? null,
    concentrationPercent: weight?.toString() ?? null,
    concentrationStatus:
      weight === null
        ? 'not_evaluated'
        : weight.compare(ExactDecimal.create('15')) > 0
          ? 'above_15_percent'
          : 'within_threshold',
  };
}

function movingAverage(values: readonly ExactDecimal[], period: number): ExactDecimal | null {
  if (values.length < period) return null;
  return values
    .slice(-period)
    .reduce((sum, value) => sum.add(value), ZERO)
    .divide(ExactDecimal.create(String(period)), MONEY_POLICY);
}

function rsi(values: readonly ExactDecimal[], period: number): ExactDecimal | null {
  if (values.length <= period) return null;
  let gains = ZERO;
  let losses = ZERO;
  const recent = values.slice(-(period + 1));
  for (let index = 1; index < recent.length; index += 1) {
    const change = recent[index]!.subtract(recent[index - 1]!);
    if (change.isNegative()) losses = losses.add(ZERO.subtract(change));
    else gains = gains.add(change);
  }
  const divisor = ExactDecimal.create(String(period));
  const averageGain = gains.divide(divisor, RATIO_POLICY);
  const averageLoss = losses.divide(divisor, RATIO_POLICY);
  if (averageLoss.isZero()) return HUNDRED;
  const relativeStrength = averageGain.divide(averageLoss, RATIO_POLICY);
  return HUNDRED.subtract(
    HUNDRED.divide(ExactDecimal.create('1').add(relativeStrength), RATIO_POLICY),
  ).round(RoundingPolicy.create(6, 'HALF_EVEN'));
}

function positive(value: string, label: string): ExactDecimal {
  const decimal = ExactDecimal.create(value);
  if (!decimal.isPositive()) throw new Error(`${label} must be greater than zero`);
  return decimal;
}

function nonNegative(value: string, label: string): ExactDecimal {
  const decimal = ExactDecimal.create(value);
  if (decimal.isNegative()) throw new Error(`${label} must not be negative`);
  return decimal;
}
