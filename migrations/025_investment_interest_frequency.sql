ALTER TABLE investments
  ADD COLUMN IF NOT EXISTS interest_frequency VARCHAR(16) NOT NULL DEFAULT 'monthly';

UPDATE investments
   SET interest_frequency = 'monthly'
 WHERE interest_frequency IS NULL;
