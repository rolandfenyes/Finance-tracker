ALTER TABLE loans
    ADD COLUMN IF NOT EXISTS finished_at TIMESTAMP NULL;

UPDATE loans
   SET finished_at = CURRENT_TIMESTAMP
 WHERE finished_at IS NULL
   AND COALESCE(balance, 0) <= 0.01;
