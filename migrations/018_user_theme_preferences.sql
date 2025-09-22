ALTER TABLE users
  ADD COLUMN IF NOT EXISTS theme TEXT;

UPDATE users
   SET theme = 'verdant-horizon'
 WHERE COALESCE(NULLIF(theme, ''), '') = '';

ALTER TABLE users
  ALTER COLUMN theme SET DEFAULT 'verdant-horizon';

ALTER TABLE users
  ALTER COLUMN theme SET NOT NULL;
