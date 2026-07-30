import { createHash } from 'node:crypto';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import type { ImportPreviewRow, TradeSide } from './securities.types';

const ZERO = ExactDecimal.create('0');

export function previewSecuritiesCsv(
  csv: string,
  defaultMarket?: string,
): { fingerprint: string; rows: ImportPreviewRow[] } {
  const normalizedMarket = defaultMarket?.trim().toUpperCase();
  const matrix = parseCsv(csv);
  const fingerprint = createHash('sha256').update(csv, 'utf8').digest('hex');
  if (matrix.length === 0) {
    return {
      fingerprint,
      rows: [{ row: 1, status: 'error', kind: 'ignored', errors: ['CSV is empty'] }],
    };
  }
  const header = headerMap(matrix[0]!);
  if (header.date === undefined || header.type === undefined) {
    return {
      fingerprint,
      rows: [
        {
          row: 1,
          status: 'error',
          kind: 'ignored',
          errors: ['Date and Type columns are required'],
        },
      ],
    };
  }
  return {
    fingerprint,
    rows: matrix.slice(1).flatMap((cells, index) => {
      if (cells.every((cell) => cell.trim() === '')) return [];
      return [previewRow(cells, header, index + 2, normalizedMarket)];
    }),
  };
}

function previewRow(
  cells: string[],
  header: Record<string, number>,
  row: number,
  defaultMarket?: string,
): ImportPreviewRow {
  const value = (key: string): string => cells[header[key] ?? -1]?.trim() ?? '';
  const type = value('type').toUpperCase();
  const side = tradeSide(type);
  if (side) {
    const symbol = value('symbol').toUpperCase();
    const market = (value('market') || defaultMarket || '').toUpperCase();
    const quantity = decimal(value('quantity'));
    const unitPrice = decimal(value('price'));
    const fee = decimal(value('fee')) ?? '0';
    const currency = value('currency').toUpperCase();
    const executedAt = instant(value('date'));
    const errors = [
      ...(symbol && /^[A-Z0-9._-]{1,32}$/.test(symbol) ? [] : ['Valid Symbol is required']),
      ...(market && /^[A-Z0-9._-]{1,48}$/.test(market)
        ? []
        : ['Market is required for canonical instrument identity']),
      ...(quantity && ExactDecimal.create(quantity).isPositive()
        ? []
        : ['Quantity must be a positive decimal']),
      ...(unitPrice && ExactDecimal.create(unitPrice).isPositive()
        ? []
        : ['Price must be a positive decimal']),
      ...(ExactDecimal.create(fee).isNegative() ? ['Fee must not be negative'] : []),
      ...(/^[A-Z]{3}$/.test(currency) ? [] : ['Currency must be a three-letter code']),
      ...(executedAt ? [] : ['Date must be a valid date or timestamp']),
    ];
    if (errors.length > 0) return { row, status: 'error', kind: 'trade', errors };
    let resolvedFee = fee;
    const total = decimal(value('total'));
    if (resolvedFee === '0' && total) {
      const notional = ExactDecimal.create(quantity!).multiply(ExactDecimal.create(unitPrice!));
      const difference =
        side === 'buy'
          ? ExactDecimal.create(total).subtract(notional)
          : notional.subtract(ExactDecimal.create(total));
      if (difference.isPositive()) resolvedFee = difference.toString();
    }
    return {
      row,
      status: 'valid',
      kind: 'trade',
      errors: [],
      trade: {
        symbol,
        market,
        side,
        quantity: quantity!,
        unitPrice: unitPrice!,
        fee: resolvedFee,
        currency,
        executedAt: executedAt!,
        note: type ? `CSV import: ${type}` : null,
      },
    };
  }
  if (cashLike(type)) {
    const raw = decimal(value('total')) ?? decimal(value('price'));
    const currency = value('currency').toUpperCase();
    const occurredOn = dateOnly(value('date'));
    const amount = raw ? absolute(raw) : null;
    const direction = cashDirection(type, raw);
    const errors = [
      ...(amount && ExactDecimal.create(amount).isPositive()
        ? []
        : ['Cash amount must be a non-zero decimal']),
      ...(/^[A-Z]{3}$/.test(currency) ? [] : ['Currency must be a three-letter code']),
      ...(occurredOn ? [] : ['Date must be valid']),
    ];
    if (errors.length > 0) return { row, status: 'error', kind: 'cash', errors };
    return {
      row,
      status: 'valid',
      kind: 'cash',
      errors: [],
      cash: {
        direction,
        amount: amount!,
        currency,
        occurredOn: occurredOn!,
        note: type ? `CSV import: ${type}` : null,
      },
    };
  }
  if (['SPLIT', 'MERGER', 'TRANSFER', 'REORG'].some((token) => type.includes(token))) {
    return {
      row,
      status: 'ignored',
      kind: 'ignored',
      errors: ['Unsupported corporate-action or transfer row'],
    };
  }
  return { row, status: 'error', kind: 'ignored', errors: ['Unsupported row type'] };
}

function parseCsv(source: string): string[][] {
  const delimiter = detectDelimiter(source.split(/\r?\n/, 1)[0] ?? '');
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let quoted = false;
  for (let index = 0; index < source.length; index += 1) {
    const character = source[index]!;
    if (quoted) {
      if (character === '"' && source[index + 1] === '"') {
        cell += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        cell += character;
      }
      continue;
    }
    if (character === '"') quoted = true;
    else if (character === delimiter) {
      row.push(cell);
      cell = '';
    } else if (character === '\n') {
      row.push(cell.replace(/\r$/, ''));
      rows.push(row);
      row = [];
      cell = '';
    } else cell += character;
  }
  if (cell !== '' || row.length > 0) {
    row.push(cell.replace(/\r$/, ''));
    rows.push(row);
  }
  return rows;
}

function detectDelimiter(header: string): string {
  return [',', ';', '\t', '|'].sort(
    (left, right) => header.split(right).length - header.split(left).length,
  )[0]!;
}

function headerMap(header: string[]): Record<string, number> {
  const aliases: Record<string, string> = {
    date: 'date',
    timestamp: 'date',
    'executed at': 'date',
    type: 'type',
    action: 'type',
    side: 'type',
    ticker: 'symbol',
    symbol: 'symbol',
    asset: 'symbol',
    market: 'market',
    exchange: 'market',
    quantity: 'quantity',
    qty: 'quantity',
    shares: 'quantity',
    price: 'price',
    'price per share': 'price',
    'price/share': 'price',
    'trade price': 'price',
    total: 'total',
    amount: 'total',
    'total amount': 'total',
    'gross amount': 'total',
    'net amount': 'total',
    currency: 'currency',
    fee: 'fee',
    commission: 'fee',
  };
  const map: Record<string, number> = {};
  header.forEach((label, index) => {
    const normalized = label
      .replace(/^\uFEFF/, '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, ' ');
    const key = aliases[normalized];
    if (key && map[key] === undefined) map[key] = index;
  });
  return map;
}

function tradeSide(type: string): TradeSide | null {
  if (type.includes('BUY')) return 'buy';
  if (type.includes('SELL')) return 'sell';
  return null;
}

function cashLike(type: string): boolean {
  return ['CASH', 'DIVIDEND', 'INTEREST', 'FEE', 'TAX', 'DEPOSIT', 'WITHDRAW'].some((token) =>
    type.includes(token),
  );
}

function cashDirection(type: string, raw: string | null): 'deposit' | 'withdrawal' {
  if (['WITHDRAW', 'FEE', 'TAX'].some((token) => type.includes(token))) return 'withdrawal';
  if (raw && ExactDecimal.create(raw).isNegative()) return 'withdrawal';
  return 'deposit';
}

function decimal(value: string): string | null {
  if (!value) return null;
  const normalized = value.replace(/[^\d.,-]/g, '').replaceAll(',', '');
  if (!/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(normalized)) return null;
  try {
    return ExactDecimal.create(normalized).toString();
  } catch {
    return null;
  }
}

function absolute(value: string): string {
  const number = ExactDecimal.create(value);
  return number.isNegative() ? ZERO.subtract(number).toString() : number.toString();
}

function instant(value: string): string | null {
  const time = Date.parse(value);
  return Number.isFinite(time) ? new Date(time).toISOString() : null;
}

function dateOnly(value: string): string | null {
  const valueInstant = instant(value);
  return valueInstant?.slice(0, 10) ?? null;
}
