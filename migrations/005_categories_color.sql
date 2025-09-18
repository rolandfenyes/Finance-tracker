ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS color TEXT;  -- store hex like '#2563eb'

-- optional: give existing categories a neutral gray if null
UPDATE categories SET color = '#6b7280' WHERE color IS NULL;
