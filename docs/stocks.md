# Stocks module

The Stocks feature relies on external market data providers. Configure credentials in your environment file:

```
STOCKS_PROVIDER=finnhub
FINNHUB_API_KEY=your-key-here
```

If no provider is configured the app falls back to cached/local prices.

## Database migrations

Run the migrations to create equity tables:

```
php scripts/migrate.php
```

This will create the following tables:

- `stocks`, `stock_prices_last`, `price_daily`
- `stock_positions`, `stock_lots`, `stock_realized_pl`
- `watchlist`, `user_settings_stocks`

Existing `stock_trades` entries are migrated automatically. Each distinct symbol becomes a `stocks` entry and historical trades are re-linked.

## Backfilling prices

Use the CLI script to import historical daily candles:

```
php scripts/stocks_backfill.php AAPL MSFT --range=1Y
```

This populates the `price_daily` table for the requested symbols. The service automatically fetches missing history when rendering charts, but backfilling improves responsiveness.

## Cost basis settings

Users can configure preferred cost-basis methods in `user_settings_stocks`:

- `cost_basis_unrealized`: defaults to `AVERAGE`
- `realized_method`: defaults to `FIFO`
- `target_allocations`: optional JSON map of symbol to target weight percentage

These values influence portfolio insights and suggestions.

## Live pricing

The `PriceDataService` caches quotes in memory and in `stock_prices_last`. Client pages poll `/api/stocks/live` every few seconds. To reduce provider usage the backend de-bounces repeated requests and uses cached data when API limits are hit.

## Testing

Unit tests live under `tests/` and can be executed with:

```
php tests/stocks_tests.php
```

The suite covers FIFO cost basis, average cost recalculation, FX conversion and signal generation.
