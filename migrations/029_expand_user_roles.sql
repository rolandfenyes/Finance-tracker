ALTER TABLE users
    DROP CONSTRAINT IF EXISTS users_role_check;

ALTER TABLE users
    ALTER COLUMN role SET DEFAULT 'free';

ALTER TABLE users
    ADD CONSTRAINT users_role_check CHECK (role IN ('free', 'premium', 'admin'));

UPDATE users
SET role = CASE
        WHEN role = 'admin' THEN 'admin'
        WHEN role = 'premium' THEN 'premium'
        ELSE 'free'
    END
WHERE role IS NULL OR role NOT IN ('free', 'premium', 'admin');
