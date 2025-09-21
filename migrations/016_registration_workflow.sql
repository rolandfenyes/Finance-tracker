-- users: add date of birth, onboarding step, and tutorial flag
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS date_of_birth date,
  ADD COLUMN IF NOT EXISTS onboard_step int NOT NULL DEFAULT 0,  -- 0=none, 1..6 flow
  ADD COLUMN IF NOT EXISTS needs_tutorial boolean NOT NULL DEFAULT true;

-- if you don't have it already: user_currencies.main unique per user
-- (optional but recommended)
-- ensure only one main currency per user: you may enforce app-side.

-- emergency fund row auto-create is already handled elsewhere; no change here.
