CREATE TABLE IF NOT EXISTS investments (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type VARCHAR(32) NOT NULL,
  name VARCHAR(180) NOT NULL,
  provider VARCHAR(180),
  identifier VARCHAR(120),
  interest_rate NUMERIC(7,3),
  notes TEXT,
  created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
  CHECK (type IN ('savings','etf','stock'))
);

CREATE INDEX IF NOT EXISTS investments_user_id_idx ON investments(user_id);

ALTER TABLE scheduled_payments
  ADD COLUMN IF NOT EXISTS investment_id INT NULL REFERENCES investments(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS scheduled_payments_investment_id_idx ON scheduled_payments(investment_id);
