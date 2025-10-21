ALTER TABLE emergency_fund
  ADD COLUMN IF NOT EXISTS investment_id INT REFERENCES investments(id) ON DELETE SET NULL;

CREATE UNIQUE INDEX IF NOT EXISTS emergency_fund_investment_id_unique
  ON emergency_fund (investment_id)
  WHERE investment_id IS NOT NULL;

ALTER TABLE emergency_fund_tx
  ADD COLUMN IF NOT EXISTS investment_tx_id INT REFERENCES investment_transactions(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS emergency_fund_tx_investment_tx_id_idx
  ON emergency_fund_tx (investment_tx_id);
