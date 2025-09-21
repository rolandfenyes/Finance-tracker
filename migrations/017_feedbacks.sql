CREATE TABLE IF NOT EXISTS feedback (
  id           BIGSERIAL PRIMARY KEY,
  user_id      BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind         TEXT NOT NULL CHECK (kind IN ('bug','idea')),
  title        TEXT NOT NULL,
  message      TEXT NOT NULL,
  severity     TEXT NULL CHECK (severity IN ('low','medium','high')),
  status       TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','in_progress','resolved','closed')),
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS feedback_user_kind_idx   ON feedback(user_id, kind);
CREATE INDEX IF NOT EXISTS feedback_status_created  ON feedback(status, created_at DESC);
