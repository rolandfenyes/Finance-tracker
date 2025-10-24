ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin'));

-- Backfill existing rows to ensure the new constraint passes even if defaults are not applied automatically.
UPDATE users
SET role = 'user'
WHERE role IS NULL OR role NOT IN ('user', 'admin');
