-- goal transactions (simple ledger)
CREATE TABLE IF NOT EXISTS goal_transactions (
  id           SERIAL PRIMARY KEY,
  user_id      INT NOT NULL,
  goal_id      INT NOT NULL REFERENCES goals(id) ON DELETE CASCADE,
  occurred_on  DATE NOT NULL,
  amount       NUMERIC(14,2) NOT NULL,         -- positive = add money
  currency     VARCHAR(8) NOT NULL DEFAULT 'HUF',
  note         TEXT
);

-- allow linking a schedule directly to a goal (like loans)
ALTER TABLE scheduled_payments
  ADD COLUMN IF NOT EXISTS goal_id INT NULL REFERENCES goals(id) ON DELETE SET NULL;
