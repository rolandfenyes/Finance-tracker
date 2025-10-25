ALTER TABLE investments
  ADD COLUMN IF NOT EXISTS stock_id INT REFERENCES stocks(id) ON DELETE SET NULL;

ALTER TABLE investments
  ADD COLUMN IF NOT EXISTS units NUMERIC(18,6);

CREATE INDEX IF NOT EXISTS investments_stock_id_idx
  ON investments(stock_id);
