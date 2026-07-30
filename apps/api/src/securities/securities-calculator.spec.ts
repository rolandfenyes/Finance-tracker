import { rebuildFifo, technicalIndicators, type FifoTrade } from './securities-calculator';

describe('securities FIFO calculator', () => {
  it('allocates buy fees into FIFO cost and sell fees into realized result', () => {
    const projection = rebuildFifo([
      trade('buy-1', 'buy', '2', '20', '1', '40', '2', '2026-01-01T10:00:00.000Z'),
      trade('buy-2', 'buy', '3', '60', '3', '120', '6', '2026-01-02T10:00:00.000Z'),
      trade('sell-1', 'sell', '4', '120', '4', '240', '8', '2026-01-03T10:00:00.000Z'),
    ]);

    expect(projection.quantity).toBe('1');
    expect(projection.remainingCostLocal).toBe('21');
    expect(projection.remainingCostBase).toBe('42');
    expect(projection.consumptions).toEqual([
      {
        sellTradeId: 'sell-1',
        buyTradeId: 'buy-1',
        quantity: '2',
        costLocal: '21',
        costBase: '42',
      },
      {
        sellTradeId: 'sell-1',
        buyTradeId: 'buy-2',
        quantity: '2',
        costLocal: '42',
        costBase: '84',
      },
    ]);
    expect(projection.realized[0]).toMatchObject({
      proceedsLocal: '120',
      costLocal: '63',
      feesLocal: '4',
      realizedLocal: '53',
      realizedBase: '106',
    });
  });

  it('rejects an oversell before a projection can be persisted', () => {
    expect(() =>
      rebuildFifo([
        trade('buy', 'buy', '0.1', '1', '0', '2', '0', '2026-01-01T00:00:00.000Z'),
        trade(
          'sell',
          'sell',
          '0.100000000000000001',
          '2',
          '0',
          '4',
          '0',
          '2026-01-02T00:00:00.000Z',
        ),
      ]),
    ).toThrow('Sell quantity exceeds available holdings');
  });

  it('preserves quantities beyond JavaScript floating-point precision', () => {
    expect(
      rebuildFifo([
        trade(
          'buy',
          'buy',
          '9007199254740993.000000000000000001',
          '9007199254740993.000000000001',
          '0',
          '9007199254740993.000000000001',
          '0',
          '2026-01-01T00:00:00.000Z',
        ),
      ]).quantity,
    ).toBe('9007199254740993.000000000000000001');
  });
});

describe('descriptive securities indicators', () => {
  it('does not issue trading advice and labels concentration descriptively', () => {
    const indicators = technicalIndicators(
      Array.from({ length: 51 }, (_, index) => String(index + 1)),
      '15.000001',
    );
    expect(indicators).toEqual({
      sma20: '41.5',
      sma50: '26.5',
      rsi14: '100',
      concentrationPercent: '15.000001',
      concentrationStatus: 'above_15_percent',
    });
    expect(Object.keys(indicators)).not.toContain('recommendation');
  });
});

function trade(
  id: string,
  side: 'buy' | 'sell',
  quantity: string,
  notional: string,
  fee: string,
  notionalBase: string,
  feeBase: string,
  executedAt: string,
): FifoTrade {
  return {
    id,
    side,
    quantity,
    notional,
    fee,
    notionalBase,
    feeBase,
    currency: 'USD',
    baseCurrency: 'HUF',
    executedAt,
  };
}
