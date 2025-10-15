-- Email verification + notification metadata
ALTER TABLE users
    ADD COLUMN email_verified_at TIMESTAMPTZ,
    ADD COLUMN email_verification_token TEXT,
    ADD COLUMN email_verification_sent_at TIMESTAMPTZ;

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_verification_token
    ON users(email_verification_token)
    WHERE email_verification_token IS NOT NULL;
