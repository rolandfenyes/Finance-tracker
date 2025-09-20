-- categories: allow a hidden identity that survives renames
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS system_key text,
  ADD COLUMN IF NOT EXISTS protected boolean DEFAULT false;

-- ensure uniqueness per user (only when system_key is set)
CREATE UNIQUE INDEX IF NOT EXISTS categories_user_systemkey_uniq
  ON categories(user_id, system_key) WHERE system_key IS NOT NULL;

-- transactions: mark rows created from EF so UI & server can lock them
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS source text,               -- e.g. 'ef'
  ADD COLUMN IF NOT EXISTS source_ref_id integer,     -- points to emergency_fund_tx.id
  ADD COLUMN IF NOT EXISTS locked boolean DEFAULT false;
