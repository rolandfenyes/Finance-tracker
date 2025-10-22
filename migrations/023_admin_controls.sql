-- Admin controls & audit logging
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS admin_role TEXT CHECK (admin_role IN ('superadmin','support','finance','dev')),
  ADD COLUMN IF NOT EXISTS admin_notes TEXT,
  ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGSERIAL PRIMARY KEY,
  actor_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  subject_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  meta JSONB DEFAULT '{}'::JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS admin_audit_log_subject_created
  ON admin_audit_log(subject_id, created_at DESC);

CREATE INDEX IF NOT EXISTS admin_audit_log_actor_created
  ON admin_audit_log(actor_id, created_at DESC);
