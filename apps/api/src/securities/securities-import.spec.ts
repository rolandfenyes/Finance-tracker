import { previewSecuritiesCsv } from './securities-import';

describe('securities CSV preview', () => {
  const csv =
    'Date,Type,Symbol,Market,Quantity,Price,Fee,Currency\n' +
    '2026-01-02T10:00:00Z,BUY,ACME,NASDAQ,1.000000000000000001,10.25,0.10,USD\n' +
    '2026-01-03,SPLIT,ACME,NASDAQ,,,,USD';

  it('produces a stable fingerprint and exact-decimal trade preview', () => {
    const first = previewSecuritiesCsv(csv);
    const second = previewSecuritiesCsv(csv);
    expect(first.fingerprint).toBe(second.fingerprint);
    expect(first.rows[0]).toMatchObject({
      status: 'valid',
      kind: 'trade',
      trade: {
        symbol: 'ACME',
        market: 'NASDAQ',
        quantity: '1.000000000000000001',
        unitPrice: '10.25',
        fee: '0.1',
      },
    });
    expect(first.rows[1]).toMatchObject({ status: 'ignored', kind: 'ignored' });
  });

  it('does not invent a market when canonical identity evidence is absent', () => {
    const result = previewSecuritiesCsv(
      'Date,Type,Symbol,Quantity,Price,Currency\n2026-01-02,BUY,ACME,1,10,USD',
    );
    expect(result.rows[0]?.errors).toContain(
      'Market is required for canonical instrument identity',
    );
  });

  it('accepts an explicit import-level market decision', () => {
    const result = previewSecuritiesCsv(
      'Date,Type,Symbol,Quantity,Price,Currency\n2026-01-02,BUY,ACME,1,10,USD',
      'NYSE',
    );
    expect(result.rows[0]?.trade?.market).toBe('NYSE');
  });
});
