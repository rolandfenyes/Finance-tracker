ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS main_currency TEXT,          -- user’s main at the time
  ADD COLUMN IF NOT EXISTS fx_rate_to_main NUMERIC(20,8), -- FROM->MAIN rate used
  ADD COLUMN IF NOT EXISTS amount_main NUMERIC(18,2);     -- amount converted to MAIN

-- optional index for reports
CREATE INDEX IF NOT EXISTS idx_tx_user_date ON transactions(user_id, occurred_on);
