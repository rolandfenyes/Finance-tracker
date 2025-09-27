CREATE TABLE IF NOT EXISTS advanced_plans (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  horizon_months INT NOT NULL CHECK (horizon_months IN (3,6,12)),
  plan_start DATE NOT NULL,
  plan_end DATE NOT NULL,
  main_currency TEXT NOT NULL,
  total_budget NUMERIC(18,2) DEFAULT 0,
  monthly_income NUMERIC(18,2) DEFAULT 0,
  monthly_commitments NUMERIC(18,2) DEFAULT 0,
  monthly_discretionary NUMERIC(18,2) DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','active','archived')),
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS advanced_plan_items (
  id SERIAL PRIMARY KEY,
  plan_id INT NOT NULL REFERENCES advanced_plans(id) ON DELETE CASCADE,
  kind TEXT NOT NULL CHECK (kind IN ('emergency','investment','loan','goal','custom')),
  reference_id INT,
  reference_label TEXT,
  target_amount NUMERIC(18,2) NOT NULL,
  current_amount NUMERIC(18,2) DEFAULT 0,
  required_amount NUMERIC(18,2) NOT NULL,
  monthly_allocation NUMERIC(18,2) NOT NULL,
  priority INT DEFAULT 1,
  sort_order INT DEFAULT 0,
  target_due_date DATE,
  status TEXT NOT NULL DEFAULT 'planned' CHECK (status IN ('planned','active','done','skipped')),
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS advanced_plan_category_limits (
  id SERIAL PRIMARY KEY,
  plan_id INT NOT NULL REFERENCES advanced_plans(id) ON DELETE CASCADE,
  category_id INT REFERENCES categories(id) ON DELETE CASCADE,
  category_label TEXT,
  suggested_limit NUMERIC(18,2) NOT NULL,
  average_spent NUMERIC(18,2) DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE(plan_id, category_id)
);
