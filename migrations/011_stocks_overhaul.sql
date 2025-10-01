BEGIN;

-- Core stocks master data
CREATE TABLE IF NOT EXISTS stocks (
  id SERIAL PRIMARY KEY,
  symbol TEXT NOT NULL,
  exchange TEXT,
  market TEXT,
  name TEXT,
  currency TEXT REFERENCES currencies(code),
  sector TEXT,
  industry TEXT,
  beta NUMERIC(10,4),
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_stocks_symbol_market ON stocks (UPPER(symbol), COALESCE(market, ''));

-- Extend stock_trades with richer metadata
ALTER TABLE stock_trades
  ADD COLUMN IF NOT EXISTS stock_id INT REFERENCES stocks(id) ON DELETE CASCADE,
  ADD COLUMN IF NOT EXISTS executed_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS fee NUMERIC(18,4) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS note TEXT,
  ADD COLUMN IF NOT EXISTS market TEXT,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW(),
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ;

UPDATE stock_trades SET executed_at = COALESCE(executed_at, trade_on::timestamptz);

-- Backfill stocks table from historic trades
INSERT INTO stocks(symbol, market, name, currency)
SELECT DISTINCT UPPER(symbol) AS symbol, COALESCE(market, 'US') AS market, UPPER(symbol) AS name, COALESCE(currency, 'USD')
FROM stock_trades
ON CONFLICT (UPPER(symbol), COALESCE(market, '')) DO NOTHING;

UPDATE stock_trades st
SET stock_id = s.id
FROM stocks s
WHERE st.stock_id IS NULL AND UPPER(st.symbol) = UPPER(s.symbol)
  AND (s.market IS NULL OR st.market IS NULL OR s.market = st.market);

-- Enforce not nulls after backfill
ALTER TABLE stock_trades ALTER COLUMN executed_at SET NOT NULL;
ALTER TABLE stock_trades ALTER COLUMN side TYPE TEXT;
ALTER TABLE stock_trades ALTER COLUMN side SET NOT NULL;
ALTER TABLE stock_trades ALTER COLUMN stock_id SET NOT NULL;

-- Richer portfolio state tables
CREATE TABLE IF NOT EXISTS stock_positions (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  qty NUMERIC(18,6) DEFAULT 0,
  avg_cost_ccy NUMERIC(18,6) DEFAULT 0,
  avg_cost_currency TEXT,
  cash_impact_ccy NUMERIC(20,6) DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE(user_id, stock_id)
);
CREATE INDEX IF NOT EXISTS idx_stock_positions_user_qty ON stock_positions(user_id) WHERE qty <> 0;

CREATE TABLE IF NOT EXISTS stock_lots (
  id SERIAL PRIMARY KEY,
  position_id INT NOT NULL REFERENCES stock_positions(id) ON DELETE CASCADE,
  qty_open NUMERIC(18,6) NOT NULL,
  qty_closed NUMERIC(18,6) DEFAULT 0,
  open_price NUMERIC(18,6) NOT NULL,
  fee NUMERIC(18,4) DEFAULT 0,
  opened_at TIMESTAMPTZ NOT NULL,
  closed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_stock_lots_position_opened ON stock_lots(position_id, opened_at);

CREATE TABLE IF NOT EXISTS stock_realized_pl (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  sell_trade_id INT REFERENCES stock_trades(id) ON DELETE CASCADE,
  realized_pl_base NUMERIC(20,6) NOT NULL,
  realized_pl_ccy NUMERIC(20,6) NOT NULL,
  currency TEXT,
  method TEXT DEFAULT 'FIFO',
  qty_closed NUMERIC(18,6) NOT NULL,
  closed_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_stock_realized_pl_user_date ON stock_realized_pl(user_id, closed_at);

CREATE TABLE IF NOT EXISTS stock_prices_last (
  stock_id INT PRIMARY KEY REFERENCES stocks(id) ON DELETE CASCADE,
  last NUMERIC(18,6),
  prev_close NUMERIC(18,6),
  day_high NUMERIC(18,6),
  day_low NUMERIC(18,6),
  volume NUMERIC(18,2),
  provider_ts TIMESTAMPTZ,
  stale BOOLEAN DEFAULT FALSE,
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS price_daily (
  id SERIAL PRIMARY KEY,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  date DATE NOT NULL,
  open NUMERIC(18,6),
  high NUMERIC(18,6),
  low NUMERIC(18,6),
  close NUMERIC(18,6),
  volume NUMERIC(18,2),
  provider TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE(stock_id, date)
);
CREATE INDEX IF NOT EXISTS idx_price_daily_stock_date ON price_daily(stock_id, date DESC);

CREATE TABLE IF NOT EXISTS watchlist (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE(user_id, stock_id)
);

CREATE TABLE IF NOT EXISTS user_settings_stocks (
  user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  unrealized_method TEXT DEFAULT 'AVERAGE',
  realized_method TEXT DEFAULT 'FIFO',
  target_allocations JSONB,
  refresh_seconds INT DEFAULT 10
);

-- Helper cache for quick totals
CREATE TABLE IF NOT EXISTS stock_portfolio_snapshots (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  snapshot_on DATE NOT NULL,
  total_value_base NUMERIC(20,6) NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE(user_id, snapshot_on)
);

-- Drop legacy view replaced by service managed tables
DROP VIEW IF EXISTS v_stock_positions;

COMMIT;
