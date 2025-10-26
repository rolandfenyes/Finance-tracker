ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role TEXT NOT NULL DEFAULT 'free' CHECK (role IN ('free', 'premium', 'admin'));

-- Backfill existing rows to ensure the new constraint passes even if defaults are not applied automatically.
UPDATE users
SET role = CASE
        WHEN role = 'admin' THEN 'admin'
        WHEN role = 'premium' THEN 'premium'
        ELSE 'free'
    END
WHERE role IS NULL OR role NOT IN ('free', 'premium', 'admin');
