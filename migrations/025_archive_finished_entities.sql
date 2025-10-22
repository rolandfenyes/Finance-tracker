ALTER TABLE loans
    ADD COLUMN archived_at TIMESTAMP NULL;

ALTER TABLE scheduled_payments
    ADD COLUMN archived_at TIMESTAMP NULL;

ALTER TABLE goals
    ADD COLUMN archived_at TIMESTAMP NULL;

UPDATE loans
   SET archived_at = finished_at
 WHERE finished_at IS NOT NULL
   AND archived_at IS NULL;

UPDATE scheduled_payments
   SET archived_at = CURRENT_TIMESTAMP
 WHERE archived_at IS NULL
   AND loan_id IN (
         SELECT id FROM loans WHERE finished_at IS NOT NULL
       );

UPDATE scheduled_payments
   SET archived_at = CURRENT_TIMESTAMP
 WHERE archived_at IS NULL
   AND goal_id IN (
         SELECT id FROM goals WHERE status = 'done'
       );

UPDATE goals
   SET archived_at = CURRENT_TIMESTAMP
 WHERE status = 'done'
   AND archived_at IS NULL;
