-- Users & Auth
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  full_name TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Currencies master (ISO-like)
CREATE TABLE IF NOT EXISTS currencies (
  code TEXT PRIMARY KEY,
  name TEXT NOT NULL
);
INSERT INTO currencies(code,name) VALUES
('USD','US Dollar') ON CONFLICT DO NOTHING;
INSERT INTO currencies(code,name) VALUES
('EUR','Euro') ON CONFLICT DO NOTHING;
INSERT INTO currencies(code,name) VALUES
('HUF','Hungarian Forint') ON CONFLICT DO NOTHING;

-- User currencies & main selection
CREATE TABLE IF NOT EXISTS user_currencies (
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  code TEXT REFERENCES currencies(code) ON DELETE RESTRICT,
  is_main BOOLEAN DEFAULT FALSE,
  PRIMARY KEY(user_id, code)
);

-- Categories (income / spending)
CREATE TABLE IF NOT EXISTS categories (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  kind TEXT CHECK (kind IN ('income','spending')) NOT NULL
);

-- Unified transactions table for incomes & spendings
CREATE TABLE IF NOT EXISTS transactions (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  kind TEXT CHECK (kind IN ('income','spending')) NOT NULL,
  category_id INT REFERENCES categories(id) ON DELETE SET NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  occurred_on DATE NOT NULL,
  note TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, occurred_on);

-- Goals
CREATE TABLE IF NOT EXISTS goals (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  target_amount NUMERIC(18,2) NOT NULL,
  current_amount NUMERIC(18,2) DEFAULT 0,
  currency TEXT REFERENCES currencies(code),
  deadline DATE,
  priority INT DEFAULT 3,
  status TEXT DEFAULT 'active'
);

-- Loans
CREATE TABLE IF NOT EXISTS loans (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  principal NUMERIC(18,2) NOT NULL,
  interest_rate NUMERIC(6,3) NOT NULL, -- annual %
  start_date DATE NOT NULL,
  end_date DATE,
  payment_day INT CHECK (payment_day BETWEEN 1 AND 28),
  extra_payment NUMERIC(18,2) DEFAULT 0,
  balance NUMERIC(18,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS loan_payments (
  id SERIAL PRIMARY KEY,
  loan_id INT REFERENCES loans(id) ON DELETE CASCADE,
  paid_on DATE NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  principal_component NUMERIC(18,2) DEFAULT 0,
  interest_component NUMERIC(18,2) DEFAULT 0,
  currency TEXT REFERENCES currencies(code)
);

-- Emergency fund
CREATE TABLE IF NOT EXISTS emergency_fund (
  user_id INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  target_amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  total NUMERIC(18,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS emergency_transactions (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  occurred_on DATE NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  kind TEXT CHECK (kind IN ('deposit','withdraw')) NOT NULL,
  note TEXT
);

-- Stocks
CREATE TABLE IF NOT EXISTS stock_trades (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  symbol TEXT NOT NULL,
  trade_on DATE NOT NULL,
  side TEXT CHECK (side IN ('buy','sell')) NOT NULL,
  quantity NUMERIC(18,6) NOT NULL,
  price NUMERIC(18,4) NOT NULL,
  amount NUMERIC(18,4) NOT NULL DEFAULT 0,
  fee NUMERIC(18,4) NOT NULL DEFAULT 0,
  currency TEXT REFERENCES currencies(code)
);

CREATE OR REPLACE VIEW v_stock_positions AS
SELECT user_id, symbol,
  SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) AS qty,
  CASE WHEN SUM(CASE WHEN side='buy' THEN quantity ELSE -quantity END) <> 0
       THEN SUM(CASE WHEN side='buy' THEN amount + fee ELSE 0 END)
            / NULLIF(SUM(CASE WHEN side='buy' THEN quantity ELSE 0 END),0)
  END AS avg_buy_price
FROM stock_trades
GROUP BY user_id, symbol;

-- Scheduled payments (RRULE-ish string)
CREATE TABLE IF NOT EXISTS scheduled_payments (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  rrule TEXT NOT NULL, -- e.g. FREQ=MONTHLY;BYMONTHDAY=10
  next_due DATE
);

-- Basic (regular) incomes with salary raises support
CREATE TABLE IF NOT EXISTS basic_incomes (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  amount NUMERIC(18,2) NOT NULL,
  currency TEXT REFERENCES currencies(code),
  valid_from DATE NOT NULL,
  valid_to DATE
);

-- Cashflow allocation rules (e.g., 50% needs)
CREATE TABLE IF NOT EXISTS cashflow_rules (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  label TEXT NOT NULL,
  percent NUMERIC(6,2) CHECK (percent >= 0 AND percent <= 100) NOT NULL,
  target_hint TEXT -- e.g., 'needs','investments','giving'
);

-- Dave Ramsey Baby Steps per user
CREATE TABLE IF NOT EXISTS baby_steps (
  user_id INT REFERENCES users(id) ON DELETE CASCADE,
  step INT CHECK (step BETWEEN 1 AND 7),
  status TEXT DEFAULT 'in_progress',
  note TEXT,
  PRIMARY KEY (user_id, step)
);