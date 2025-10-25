INSERT INTO users (email, password_hash, role, needs_tutorial, onboard_step)
SELECT 'info@mymoneymap.hu', '$2y$12$nLoSClWbc1ZlAeM8fThzc.nSSPSmiTql7BUd9QjI0YO3Q38MikILy', 'admin', FALSE, 0
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'info@mymoneymap.hu'
);

UPDATE users
   SET password_hash = '$2y$12$nLoSClWbc1ZlAeM8fThzc.nSSPSmiTql7BUd9QjI0YO3Q38MikILy',
       role = 'admin',
       needs_tutorial = FALSE,
       onboard_step = 0
 WHERE email = 'info@mymoneymap.hu';
