-- Stores daily FX rates normalized to a single base (EUR by default)
CREATE TABLE IF NOT EXISTS fx_rates (
  rate_date DATE NOT NULL,
  base_code TEXT NOT NULL DEFAULT 'EUR',
  code      TEXT NOT NULL,
  rate      NUMERIC(20,8) NOT NULL, -- 1 base_code = rate code
  PRIMARY KEY(rate_date, base_code, code)
);
CREATE INDEX IF NOT EXISTS idx_fx_rates_code_date ON fx_rates(code, rate_date);

-- Helpful view to see latest available rate on/before a date
CREATE OR REPLACE VIEW v_fx_latest AS
SELECT DISTINCT ON (code, asof)
  code,
  asof,
  rate
FROM (
  SELECT f.code,
         d::date AS asof,
         (SELECT rate FROM fx_rates fr WHERE fr.code=f.code AND fr.rate_date<=d ORDER BY fr.rate_date DESC LIMIT 1) AS rate
  FROM (
    SELECT DISTINCT code FROM fx_rates
  ) f
  CROSS JOIN LATERAL generate_series(date_trunc('day', CURRENT_DATE - INTERVAL '365 days')::date, CURRENT_DATE + INTERVAL '365 days', INTERVAL '1 day') AS d
) s
WHERE rate IS NOT NULL;