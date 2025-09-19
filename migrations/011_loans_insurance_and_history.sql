-- Insurance cost per month that is NOT counted toward principal
ALTER TABLE loans ADD COLUMN IF NOT EXISTS insurance_monthly numeric(18,2) DEFAULT 0 NOT NULL;

-- If the loan started in the past, user can confirm they’ve paid each month up to today.
ALTER TABLE loans ADD COLUMN IF NOT EXISTS history_confirmed boolean DEFAULT false NOT NULL;
