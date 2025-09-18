-- migrations/008_scheduled_category.sql
ALTER TABLE scheduled_payments
  ADD COLUMN IF NOT EXISTS category_id INT
  REFERENCES categories(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_sched_user_cat
  ON scheduled_payments(user_id, category_id);
