ALTER TABLE loan_payments
  ADD COLUMN IF NOT EXISTS transaction_id INT REFERENCES transactions(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_loan_payments_transaction
  ON loan_payments(transaction_id);
