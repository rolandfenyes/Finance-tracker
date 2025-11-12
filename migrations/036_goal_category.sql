ALTER TABLE goals
  ADD COLUMN IF NOT EXISTS category_id INT NULL REFERENCES categories(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_goals_category ON goals(category_id);
