\set ON_ERROR_STOP on

-- Run once as the database owner/administrative identity. Authentication secrets are
-- provisioned by the hosting platform or secret manager and never belong in this file.
DO $roles$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mymoneymap_migrator') THEN
    CREATE ROLE mymoneymap_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mymoneymap_runtime') THEN
    CREATE ROLE mymoneymap_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
  END IF;
END
$roles$;

ALTER ROLE mymoneymap_migrator NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;
ALTER ROLE mymoneymap_runtime NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;

SELECT format('GRANT CONNECT ON DATABASE %I TO mymoneymap_migrator', current_database()) \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO mymoneymap_runtime', current_database()) \gexec

REVOKE CREATE ON SCHEMA public FROM PUBLIC;

CREATE SCHEMA IF NOT EXISTS mymoneymap AUTHORIZATION mymoneymap_migrator;
CREATE SCHEMA IF NOT EXISTS mymoneymap_meta AUTHORIZATION mymoneymap_migrator;
ALTER SCHEMA mymoneymap OWNER TO mymoneymap_migrator;
ALTER SCHEMA mymoneymap_meta OWNER TO mymoneymap_migrator;

REVOKE ALL ON SCHEMA mymoneymap, mymoneymap_meta FROM PUBLIC;
GRANT USAGE ON SCHEMA mymoneymap TO mymoneymap_runtime;
REVOKE ALL ON SCHEMA mymoneymap_meta FROM mymoneymap_runtime;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA mymoneymap TO mymoneymap_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA mymoneymap TO mymoneymap_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE mymoneymap_migrator IN SCHEMA mymoneymap
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO mymoneymap_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE mymoneymap_migrator IN SCHEMA mymoneymap
  GRANT USAGE, SELECT ON SEQUENCES TO mymoneymap_runtime;
