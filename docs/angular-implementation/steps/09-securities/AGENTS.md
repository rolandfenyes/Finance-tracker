# Step 09 — Securities Portfolio

## Objective

Implement the securities portfolio, immutable activity, trade/cash commands,
broker import, instruments, stored market data, watchlist, refresh jobs, and
portfolio-clear workflow.

## Dependencies

- Steps 00–08 are complete.
- Financial command, exact-value, FX provenance, chart, polling, data-view, and
  responsive dialog patterns are stable.
- Every securities operation has a concrete generated response schema;
  `SecuritiesResponseDto.data: {}` is not acceptable.

## Required evidence

Read securities controller, DTOs, generated services, types, FIFO calculator
tests, import tests, provider adapters/gates, refresh queue behavior, route
coverage, and approved securities decisions.

## Routes and required behavior

- `/app/securities`: FIFO portfolio, positions, allocation, cash, valuation
  status, refresh, and danger zone.
- `/app/securities/activity`: trades, cash movements, fees, and realized
  results.
- `/app/securities/trade`: buy/sell with canonical instrument ID, exact
  quantity, unit price, fee, currency, date/time, and note.
- `/app/securities/cash`: transfer to/from securities cash.
- `/app/securities/import`: JSON-defined broker CSV preview, row validation,
  fingerprint state, and explicit atomic commit.
- `/app/securities/instruments/:id`: canonical symbol/market identity,
  metadata, quote, actual trading-day history, and descriptive indicators.
- `/app/securities/watchlist`: watched canonical instruments.
- trade reversal dialog;
- refresh job polling and retry outcome;
- portfolio-clear step-up confirmation.

## Business constraints

- Never allow the UI to pre-validate an oversell as authoritative; present
  server rejection and refresh holdings.
- Reversal keeps history and reverses linked cash/fee effects.
- Import preview is not committed activity.
- A successful refresh job does not prove that a quote is available.
- Preserve available, delayed, stale, and unavailable quote states.
- Never substitute cost for missing market value.
- Indicators remain descriptive and contain no buy/trim language.
- Market-data-disabled state must leave the accounting portfolio usable.
- Do not add tax lots, tax advice, corporate actions, dividend calendars,
  TWR/MWR, or unsupported import formats.

## Calculation boundary

FIFO lots, position quantities, cost, realized result, allocation, valuation,
notional, FX, and indicators come from securities read models. Chart rendering
may map returned price strings to display coordinates but must preserve missing
trading days and unavailable values.

## Tests

- portfolio/FIFO/allocation values originate from generated service fixtures;
- oversell and concurrent-state conflict presentation;
- trade/cash/reversal exact serialization and post-success invalidation;
- preview/commit separation, duplicate fingerprint, row errors, and rollback
  response;
- canonical symbol + market collisions;
- quote delayed/stale/unavailable and no cost substitution;
- refresh polling, retry, failure, and disabled-provider states;
- watch/unwatch;
- clear confirmation and reauthentication fields from the generated DTO;
- no advisory wording;
- mobile/desktop portfolio, accessible chart/table, keyboard, EN/ES/HU;
- Playwright trade, oversell, import, unavailable quote, refresh, watchlist, and
  reversal journeys.

## Acceptance criteria

- Every securities operation has intentional UI coverage.
- Accounting remains usable without market data.
- Calculation-source tests prove no frontend FIFO or valuation implementation.
- No Step 10 settings/admin work was started.
- Step 10 was not started.
