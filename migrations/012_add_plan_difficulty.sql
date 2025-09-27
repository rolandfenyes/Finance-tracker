ALTER TABLE advanced_plans
  ADD COLUMN IF NOT EXISTS difficulty_level TEXT NOT NULL DEFAULT 'medium'
  CHECK (difficulty_level IN ('easy','medium','hard'));
