CREATE TABLE IF NOT EXISTS user_subscriptions (
  id                   BIGSERIAL PRIMARY KEY,
  user_id              BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  plan_code            TEXT NOT NULL,
  plan_name            TEXT NOT NULL,
  status               TEXT NOT NULL CHECK (status IN ('active','trialing','past_due','canceled','expired')),
  billing_interval     TEXT NOT NULL CHECK (billing_interval IN ('weekly','monthly','yearly','lifetime')),
  interval_count       INT  NOT NULL DEFAULT 1 CHECK (interval_count > 0),
  amount               NUMERIC(18,2) NOT NULL DEFAULT 0,
  currency             CHAR(3) NOT NULL DEFAULT 'USD',
  started_at           TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  current_period_start TIMESTAMPTZ NULL,
  current_period_end   TIMESTAMPTZ NULL,
  cancel_at            TIMESTAMPTZ NULL,
  canceled_at          TIMESTAMPTZ NULL,
  trial_ends_at        TIMESTAMPTZ NULL,
  notes                TEXT NULL,
  created_at           TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS user_subscriptions_user_idx
    ON user_subscriptions(user_id, status, current_period_end DESC);

CREATE TABLE IF NOT EXISTS user_invoices (
  id               BIGSERIAL PRIMARY KEY,
  user_id          BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  subscription_id  BIGINT NULL REFERENCES user_subscriptions(id) ON DELETE SET NULL,
  invoice_number   TEXT NOT NULL,
  status           TEXT NOT NULL CHECK (status IN ('draft','open','paid','failed','past_due','refunded','void')),
  total_amount     NUMERIC(18,2) NOT NULL DEFAULT 0,
  currency         CHAR(3) NOT NULL DEFAULT 'USD',
  issued_at        TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at           TIMESTAMPTZ NULL,
  paid_at          TIMESTAMPTZ NULL,
  failure_reason   TEXT NULL,
  refund_reason    TEXT NULL,
  notes            TEXT NULL,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (invoice_number)
);

CREATE INDEX IF NOT EXISTS user_invoices_user_idx
    ON user_invoices(user_id, issued_at DESC);
CREATE INDEX IF NOT EXISTS user_invoices_subscription_idx
    ON user_invoices(subscription_id, issued_at DESC);

CREATE TABLE IF NOT EXISTS user_payments (
  id                    BIGSERIAL PRIMARY KEY,
  user_id               BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  invoice_id            BIGINT NULL REFERENCES user_invoices(id) ON DELETE SET NULL,
  type                  TEXT NOT NULL CHECK (type IN ('charge','refund','adjustment')),
  status                TEXT NOT NULL CHECK (status IN ('pending','succeeded','failed','canceled')),
  amount                NUMERIC(18,2) NOT NULL DEFAULT 0,
  currency              CHAR(3) NOT NULL DEFAULT 'USD',
  gateway               TEXT NULL,
  transaction_reference TEXT NULL,
  failure_reason        TEXT NULL,
  notes                 TEXT NULL,
  processed_at          TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at            TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS user_payments_user_idx
    ON user_payments(user_id, processed_at DESC);
CREATE INDEX IF NOT EXISTS user_payments_invoice_idx
    ON user_payments(invoice_id, processed_at DESC);
