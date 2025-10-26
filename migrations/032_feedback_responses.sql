CREATE TABLE IF NOT EXISTS feedback_responses (
  id          BIGSERIAL PRIMARY KEY,
  feedback_id BIGINT NOT NULL REFERENCES feedback(id) ON DELETE CASCADE,
  admin_id    BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
  message     TEXT NOT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS feedback_responses_feedback_idx
    ON feedback_responses(feedback_id, created_at DESC);
