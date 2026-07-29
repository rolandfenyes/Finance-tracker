import { parseSingleRateCsv } from './frankfurter-fx.provider';

describe('Frankfurter FX provider parsing', () => {
  it('keeps provider rates as exact decimal strings', () => {
    expect(
      parseSingleRateCsv('date,base,quote,rate\n2026-07-24,EUR,HUF,392.123456789012345678'),
    ).toEqual({
      date: '2026-07-24',
      base: 'EUR',
      quote: 'HUF',
      rate: '392.123456789012345678',
    });
  });

  it.each([
    '',
    'date,base,quote,rate\n2026-07-24,EUR,HUF,not-a-decimal',
    'date,base,quote,rate\n2026-02-30,EUR,HUF,390',
    'date,base,quote,rate\n2026-07-24,eur,HUF,390',
    'date,base,quote,rate\n2026-07-24,EUR,HUF,390,extra',
  ])('rejects malformed provider data', (body) => {
    expect(parseSingleRateCsv(body)).toBeNull();
  });
});
