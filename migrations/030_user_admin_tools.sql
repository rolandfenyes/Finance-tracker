ALTER TABLE users
    ADD COLUMN IF NOT EXISTS status TEXT NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS deactivated_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS full_name_search TEXT;

UPDATE users
   SET status = 'active'
 WHERE status IS NULL OR status = '';

CREATE TABLE IF NOT EXISTS user_login_activity (
    id BIGSERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    email TEXT,
    success BOOLEAN NOT NULL DEFAULT TRUE,
    method TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_login_activity_user_created
    ON user_login_activity(user_id, created_at DESC);
