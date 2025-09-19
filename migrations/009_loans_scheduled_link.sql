-- migrations/009_loans_scheduled_link.sql
ALTER TABLE loans
  ADD COLUMN IF NOT EXISTS scheduled_payment_id INT
  REFERENCES scheduled_payments(id) ON DELETE SET NULL;

ALTER TABLE scheduled_payments
  ADD COLUMN IF NOT EXISTS loan_id INT
  REFERENCES loans(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_loans_user_sched ON loans(user_id, scheduled_payment_id);
CREATE INDEX IF NOT EXISTS idx_sched_user_loan  ON scheduled_payments(user_id, loan_id);
