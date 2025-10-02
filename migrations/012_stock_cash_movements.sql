BEGIN;

CREATE TABLE IF NOT EXISTS stock_cash_movements (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount NUMERIC(18, 2) NOT NULL,
    currency CHAR(3) NOT NULL,
    executed_at TIMESTAMPTZ NOT NULL,
    note TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS stock_cash_movements_user_idx ON stock_cash_movements(user_id);
CREATE INDEX IF NOT EXISTS stock_cash_movements_executed_idx ON stock_cash_movements(executed_at);

COMMIT;
