CREATE TABLE IF NOT EXISTS user_passkeys (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    credential_id TEXT NOT NULL UNIQUE,
    public_key_pem TEXT NOT NULL,
    sign_count BIGINT NOT NULL DEFAULT 0,
    label TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_used TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS user_passkeys_user_id_idx ON user_passkeys(user_id);
