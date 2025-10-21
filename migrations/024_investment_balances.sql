ALTER TABLE investments
  ADD COLUMN IF NOT EXISTS balance NUMERIC(14,2) NOT NULL DEFAULT 0;

ALTER TABLE investments
  ADD COLUMN IF NOT EXISTS currency TEXT;

CREATE TABLE IF NOT EXISTS investment_transactions (
  id SERIAL PRIMARY KEY,
  investment_id INT NOT NULL REFERENCES investments(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  amount NUMERIC(14,2) NOT NULL,
  note TEXT,
  created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS investment_transactions_investment_id_idx
  ON investment_transactions(investment_id);

CREATE INDEX IF NOT EXISTS investment_transactions_user_id_idx
  ON investment_transactions(user_id);
