ALTER TABLE stock_trades
  ADD COLUMN IF NOT EXISTS amount NUMERIC(18,4),
  ADD COLUMN IF NOT EXISTS fee NUMERIC(18,4);

UPDATE stock_trades
SET amount = COALESCE(amount, quantity * price),
    fee = COALESCE(fee, 0)
WHERE amount IS NULL OR fee IS NULL;

ALTER TABLE stock_trades
  ALTER COLUMN amount SET NOT NULL,
  ALTER COLUMN fee SET NOT NULL;

CREATE OR REPLACE VIEW v_stock_positions AS
SELECT user_id, symbol,
  SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) AS qty,
  CASE WHEN SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) <> 0
       THEN SUM(CASE WHEN side='buy' THEN amount + fee ELSE 0 END)
            / NULLIF(SUM(CASE WHEN side='buy' THEN quantity ELSE 0 END),0)
  END AS avg_buy_price
FROM stock_trades
GROUP BY user_id, symbol;
