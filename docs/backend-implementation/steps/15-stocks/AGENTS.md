# Step 15 — Securities Portfolio

## Objective

Reimplement current stock import/trade/cash/portfolio/price/chart/signal behavior while correcting verified accounting and provider defects.

## Evidence

All `src/stocks/` classes, `src/controllers/stocks.php`, stock migrations 011/012, current stock test, `docs/stocks.md`, and findings F-22 through F-28, D-07/D-09, O-15.

## Required behavior

- canonical instrument identity including market/exchange;
- buy/sell trades, fees, FIFO lots, positions, realized P/L, linked portfolio cash entries, watchlist, quotes/history, allocation and chart read models;
- transactional posting and reversal/rebuild;
- reject sell quantity above available holdings under a lock;
- trade-linked cash reversal;
- acquisition/sale/valuation FX provenance;
- explicit quote timestamp, delayed/stale/unavailable status;
- provider adapter with rate-limit/retry/cache behavior;
- CSV import preview, validation, fingerprint/idempotency, and rollback.

## Corrections

Never use cost as invisible market value. Correct Finnhub metadata mapping. Signals must be descriptive technical indicators, not buy/trim advice. Do not add tax lots/corporate actions/dividend calendars unless Step 00 includes them.

## Acceptance

FIFO/fees, oversell, concurrent sells, trade reversal, import duplicate/rollback, symbol-market collision, missing quote, FX attribution, weekend history, provider failure, and portfolio reconciliation tests pass.

