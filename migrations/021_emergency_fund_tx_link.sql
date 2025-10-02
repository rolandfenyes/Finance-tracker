-- ensure transactions can link back to emergency fund entries
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS ef_tx_id bigint;

CREATE INDEX IF NOT EXISTS idx_transactions_ef_tx_id
  ON transactions(ef_tx_id)
  WHERE ef_tx_id IS NOT NULL;
