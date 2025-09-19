CREATE TABLE IF NOT EXISTS goal_contributions (
  id           BIGSERIAL PRIMARY KEY,
  user_id      BIGINT NOT NULL,
  goal_id      BIGINT NOT NULL,
  amount       NUMERIC(14,2) NOT NULL,
  currency     TEXT NOT NULL,               -- native currency of this contribution
  occurred_on  DATE NOT NULL,
  note         TEXT,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),

  CONSTRAINT fk_gc_goal  FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_gc_user_goal_date ON goal_contributions(user_id, goal_id, occurred_on);
