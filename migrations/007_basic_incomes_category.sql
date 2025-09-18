-- migrations/007_basic_incomes_category.sql
ALTER TABLE basic_incomes
  ADD COLUMN IF NOT EXISTS category_id INT
  REFERENCES categories(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_basic_incomes_user_cat
  ON basic_incomes(user_id, category_id);
