CREATE TABLE IF NOT EXISTS billing_settings (
  id                  INT PRIMARY KEY DEFAULT 1,
  stripe_secret_key   TEXT NULL,
  stripe_publishable_key TEXT NULL,
  stripe_webhook_secret  TEXT NULL,
  default_currency    CHAR(3) NOT NULL DEFAULT 'USD',
  created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO billing_settings (id, default_currency)
VALUES (1, 'USD')
ON CONFLICT (id) DO NOTHING;

CREATE OR REPLACE FUNCTION billing_settings_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_billing_settings_updated_at ON billing_settings;
CREATE TRIGGER trigger_billing_settings_updated_at
BEFORE UPDATE ON billing_settings
FOR EACH ROW
EXECUTE FUNCTION billing_settings_touch_updated_at();

CREATE TABLE IF NOT EXISTS billing_plans (
  id                BIGSERIAL PRIMARY KEY,
  code              TEXT NOT NULL UNIQUE,
  name              TEXT NOT NULL,
  description       TEXT NULL,
  price             NUMERIC(18,2) NOT NULL DEFAULT 0,
  currency          CHAR(3) NOT NULL DEFAULT 'USD',
  billing_interval  TEXT NOT NULL CHECK (billing_interval IN ('weekly','monthly','yearly','lifetime')),
  interval_count    INT  NOT NULL DEFAULT 1 CHECK (interval_count > 0),
  role_slug         TEXT NOT NULL REFERENCES roles(slug) ON DELETE RESTRICT,
  trial_days        INT NULL CHECK (trial_days IS NULL OR trial_days >= 0),
  is_active         BOOLEAN NOT NULL DEFAULT TRUE,
  stripe_product_id TEXT NULL,
  stripe_price_id   TEXT NULL,
  metadata          JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS billing_plans_role_idx
    ON billing_plans(LOWER(role_slug));

CREATE OR REPLACE FUNCTION billing_plans_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_billing_plans_updated_at ON billing_plans;
CREATE TRIGGER trigger_billing_plans_updated_at
BEFORE UPDATE ON billing_plans
FOR EACH ROW
EXECUTE FUNCTION billing_plans_touch_updated_at();

INSERT INTO billing_plans (code, name, description, price, currency, billing_interval, interval_count, role_slug, trial_days, is_active)
VALUES
    (
        'free',
        'Free',
        'Complimentary tier with limited features.',
        0,
        'USD',
        'monthly',
        1,
        'free',
        NULL,
        TRUE
    ),
    (
        'premium-monthly',
        'Premium — Monthly',
        'Full access billed each month.',
        12.00,
        'USD',
        'monthly',
        1,
        'premium',
        14,
        TRUE
    ),
    (
        'premium-yearly',
        'Premium — Yearly',
        'Full access billed once per year.',
        120.00,
        'USD',
        'yearly',
        1,
        'premium',
        30,
        TRUE
    )
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    price = EXCLUDED.price,
    currency = EXCLUDED.currency,
    billing_interval = EXCLUDED.billing_interval,
    interval_count = EXCLUDED.interval_count,
    role_slug = EXCLUDED.role_slug,
    trial_days = EXCLUDED.trial_days,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

CREATE TABLE IF NOT EXISTS billing_promotions (
  id                  BIGSERIAL PRIMARY KEY,
  code                TEXT NOT NULL UNIQUE,
  name                TEXT NOT NULL,
  description         TEXT NULL,
  discount_percent    NUMERIC(5,2) NULL CHECK (discount_percent IS NULL OR (discount_percent >= 0 AND discount_percent <= 100)),
  discount_amount     NUMERIC(18,2) NULL,
  currency            CHAR(3) NULL,
  max_redemptions     INT NULL CHECK (max_redemptions IS NULL OR max_redemptions >= 0),
  redeem_by           TIMESTAMPTZ NULL,
  trial_days          INT NULL CHECK (trial_days IS NULL OR trial_days >= 0),
  plan_code           TEXT NULL REFERENCES billing_plans(code) ON DELETE SET NULL,
  stripe_coupon_id    TEXT NULL,
  stripe_promo_code_id TEXT NULL,
  metadata            JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION billing_promotions_touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_billing_promotions_updated_at ON billing_promotions;
CREATE TRIGGER trigger_billing_promotions_updated_at
BEFORE UPDATE ON billing_promotions
FOR EACH ROW
EXECUTE FUNCTION billing_promotions_touch_updated_at();

