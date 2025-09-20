-- 01. ensure emergency_fund has a currency column (you already seem to have it)
ALTER TABLE emergency_fund
  ADD COLUMN IF NOT EXISTS currency text;

-- 02. transaction table: each add/withdraw stored with both native and converted amounts
CREATE TABLE IF NOT EXISTS emergency_fund_tx (
  id            bigserial PRIMARY KEY,
  user_id       bigint      NOT NULL,
  occurred_on   date        NOT NULL,
  kind          text        NOT NULL CHECK (kind IN ('add','withdraw')),
  -- what the user typed (always the EF target currency, enforced by controller)
  amount_native numeric(18,2) NOT NULL,
  currency_native text       NOT NULL,
  -- snapshot for reporting, so monthly views don’t shift when rates change
  amount_main   numeric(18,2) NOT NULL,
  main_currency text          NOT NULL,
  rate_used     numeric(18,8) NOT NULL, -- from native -> main at occurred_on
  note          text
);

CREATE INDEX IF NOT EXISTS idx_emf_tx_user_date ON emergency_fund_tx(user_id, occurred_on);
