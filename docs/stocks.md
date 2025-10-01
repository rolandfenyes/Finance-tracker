# Stocks module

The stocks module lets you record equity trades, track positions and watchlists, and fetch live pricing through pluggable providers.

## Configuration

Set the following environment variables (or edit `config/config.php`):

- `STOCKS_PROVIDER` – provider key (`null`, `finnhub`, ...). Defaults to `null` which disables remote quotes.
- `STOCKS_REFRESH_SECONDS` – polling interval for live quotes (default `10`).
- `FINNHUB_API_KEY` – required when `STOCKS_PROVIDER=finnhub`.
- `FINNHUB_BASE_URL` – optional override for the Finnhub API base URL.

Provider-specific settings are merged with the config array returned from `config/config.php` under the `stocks.providers` key.

## Database

Run migrations to create the new tables:

```sh
psql "$DATABASE_URL" -f migrations/011_stocks_overhaul.sql
```

The migration introduces:

- `stocks` master data
- `stock_prices_last` cache of live quotes
- `price_daily` historical candles
- `stock_positions`, `stock_lots`, `stock_realized_pl` for cost basis tracking
- `watchlist` and `user_settings_stocks`

Existing trade history is preserved. New trades should be recorded through the `Stocks\TradeService` which rebuilds lots and realized P/L.

## Backfilling prices

Use the helper CLI to backfill daily candles:

```sh
php scripts/stocks_backfill.php --symbol=AAPL --from=2023-01-01 --to=2023-12-31
```

(See `scripts/stocks_backfill.php` for usage. The script will request candles via the configured provider and upsert them into `price_daily`.)

## UI notes

- On the stock detail page you can switch the price chart range (1D/5D/1M/6M/1Y/5Y). The buttons fetch `/api/stocks/{symbol}/history?range=...` via AJAX and refresh both the price line and the position value chart without a full page reload.

## Cost-basis preferences

User preferences are stored in `user_settings_stocks`:

- `unrealized_method` – currently supports `AVERAGE` (default)
- `realized_method` – currently supports `FIFO`
- `refresh_seconds` – overrides live quote polling interval per user
- `target_allocations` – JSON map of target weights (percent) used by the insights engine

You can seed defaults during onboarding:

```sql
INSERT INTO user_settings_stocks(user_id) VALUES (123) ON CONFLICT (user_id) DO NOTHING;
```

## Demo data

After running the migration, seed a few example symbols and trades for testing:

```sql
INSERT INTO stocks(symbol, market, name, currency) VALUES
  ('AAPL','NASDAQ','Apple Inc.','USD'),
  ('MSFT','NASDAQ','Microsoft Corp.','USD'),
  ('SAP','XETRA','SAP SE','EUR')
ON CONFLICT DO NOTHING;

-- Example buys
INSERT INTO stock_trades(user_id, stock_id, symbol, trade_on, side, quantity, price, currency, executed_at, fee, created_at)
SELECT 1, id, symbol, '2023-01-15', 'buy', 5, 135.25, currency, '2023-01-15 15:30:00', 1.5, NOW()
FROM stocks WHERE symbol='AAPL';
```

Then run the rebuild command to populate lots and positions:

```sh
php scripts/stocks_rebuild.php --user=1
```

## Tests

Execute the lightweight regression tests:

```sh
php tests/stocks/trade_service_test.php
```

The tests cover FIFO realised P/L, average-cost recomputation, FX conversion helpers and signal generation.
