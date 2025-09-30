-- Stocks feature schema upgrade
BEGIN;

CREATE TABLE IF NOT EXISTS stocks (
  id SERIAL PRIMARY KEY,
  symbol TEXT NOT NULL,
  exchange TEXT NOT NULL DEFAULT 'GENERIC',
  name TEXT,
  currency TEXT NOT NULL DEFAULT 'USD',
  sector TEXT,
  industry TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE(symbol, exchange)
);

CREATE TABLE IF NOT EXISTS stock_prices_last (
  stock_id INT PRIMARY KEY REFERENCES stocks(id) ON DELETE CASCADE,
  last NUMERIC(18,6) NOT NULL DEFAULT 0,
  prev_close NUMERIC(18,6) NOT NULL DEFAULT 0,
  day_high NUMERIC(18,6) NOT NULL DEFAULT 0,
  day_low NUMERIC(18,6) NOT NULL DEFAULT 0,
  volume NUMERIC(18,2) NOT NULL DEFAULT 0,
  provider_ts TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS price_daily (
  id SERIAL PRIMARY KEY,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  date DATE NOT NULL,
  open NUMERIC(18,6) NOT NULL DEFAULT 0,
  high NUMERIC(18,6) NOT NULL DEFAULT 0,
  low NUMERIC(18,6) NOT NULL DEFAULT 0,
  close NUMERIC(18,6) NOT NULL DEFAULT 0,
  volume NUMERIC(18,2) NOT NULL DEFAULT 0,
  provider TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (stock_id, date)
);

CREATE TABLE IF NOT EXISTS stock_positions (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  qty NUMERIC(18,6) NOT NULL DEFAULT 0,
  avg_cost_ccy NUMERIC(18,6) NOT NULL DEFAULT 0,
  avg_cost_currency TEXT NOT NULL DEFAULT 'USD',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE(user_id, stock_id)
);

CREATE TABLE IF NOT EXISTS stock_lots (
  id SERIAL PRIMARY KEY,
  position_id INT NOT NULL REFERENCES stock_positions(id) ON DELETE CASCADE,
  qty_open NUMERIC(18,6) NOT NULL,
  qty_closed NUMERIC(18,6) NOT NULL DEFAULT 0,
  open_price NUMERIC(18,6) NOT NULL,
  fee NUMERIC(18,4) NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'USD',
  opened_at TIMESTAMPTZ NOT NULL,
  closed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS stock_realized_pl (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  sell_trade_id INT,
  realized_pl_base NUMERIC(18,6) NOT NULL DEFAULT 0,
  realized_pl_ccy NUMERIC(18,6) NOT NULL DEFAULT 0,
  method TEXT NOT NULL DEFAULT 'FIFO',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS watchlist (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  stock_id INT NOT NULL REFERENCES stocks(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE(user_id, stock_id)
);

CREATE TABLE IF NOT EXISTS user_settings_stocks (
  user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  cost_basis_unrealized TEXT NOT NULL DEFAULT 'AVERAGE',
  realized_method TEXT NOT NULL DEFAULT 'FIFO',
  target_allocations JSONB
);

ALTER TABLE stock_trades RENAME COLUMN quantity TO qty_old;
ALTER TABLE stock_trades ADD COLUMN qty NUMERIC(18,6);
UPDATE stock_trades SET qty = qty_old;
ALTER TABLE stock_trades DROP COLUMN qty_old;

ALTER TABLE stock_trades ADD COLUMN stock_id INT;
ALTER TABLE stock_trades ADD COLUMN fee NUMERIC(18,4) NOT NULL DEFAULT 0;
ALTER TABLE stock_trades ADD COLUMN executed_at TIMESTAMPTZ;
ALTER TABLE stock_trades ADD COLUMN note TEXT;
ALTER TABLE stock_trades ADD COLUMN created_at TIMESTAMPTZ NOT NULL DEFAULT NOW();
ALTER TABLE stock_trades ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

UPDATE stock_trades SET executed_at = COALESCE(trade_on::timestamptz, NOW());
ALTER TABLE stock_trades DROP COLUMN trade_on;

-- Populate stocks table from legacy trades
INSERT INTO stocks(symbol, exchange, name, currency)
SELECT DISTINCT symbol, 'LEGACY', symbol, COALESCE(currency, 'USD')
FROM stock_trades
WHERE symbol IS NOT NULL
ON CONFLICT DO NOTHING;

UPDATE stock_trades st SET stock_id = s.id
FROM stocks s
WHERE s.symbol = st.symbol
  AND st.stock_id IS NULL;

ALTER TABLE stock_trades
  ALTER COLUMN stock_id SET NOT NULL,
  ALTER COLUMN executed_at SET NOT NULL,
  ALTER COLUMN side TYPE TEXT,
  ALTER COLUMN side SET NOT NULL,
  ALTER COLUMN qty SET NOT NULL,
  ALTER COLUMN currency SET NOT NULL;

UPDATE stock_trades SET side = UPPER(side);

ALTER TABLE stock_trades ADD CONSTRAINT stock_trades_side_check CHECK (side IN ('BUY','SELL'));
ALTER TABLE stock_trades ADD CONSTRAINT stock_trades_stock_fk FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE;

DROP VIEW IF EXISTS v_stock_positions;

CREATE INDEX IF NOT EXISTS idx_stock_positions_user ON stock_positions(user_id);
CREATE INDEX IF NOT EXISTS idx_stock_lots_position ON stock_lots(position_id);
CREATE INDEX IF NOT EXISTS idx_price_daily_stock_date ON price_daily(stock_id, date);
CREATE INDEX IF NOT EXISTS idx_stock_realized_user_stock ON stock_realized_pl(user_id, stock_id);

COMMIT;
