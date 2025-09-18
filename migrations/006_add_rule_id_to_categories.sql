-- migrations/006_categories_cashflow_fk.sql
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS cashflow_rule_id INT
  REFERENCES cashflow_rules(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_categories_rule ON categories(cashflow_rule_id);
