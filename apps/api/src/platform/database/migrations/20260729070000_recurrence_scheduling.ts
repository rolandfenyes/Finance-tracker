import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      title varchar(120) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      economic_type varchar(16) NOT NULL,
      starts_on date NOT NULL,
      rrule varchar(512) NOT NULL DEFAULT '',
      category_id uuid,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT recurring_rules_title_check CHECK (
        char_length(btrim(title)) BETWEEN 1 AND 120
      ),
      CONSTRAINT recurring_rules_amount_check CHECK (amount > 0),
      CONSTRAINT recurring_rules_economic_type_check CHECK (
        economic_type IN ('income', 'expense', 'transfer')
      ),
      CONSTRAINT recurring_rules_rrule_check CHECK (
        char_length(rrule) <= 512 AND rrule = btrim(rrule)
      ),
      CONSTRAINT recurring_rules_transfer_category_check CHECK (
        economic_type <> 'transfer' OR category_id IS NULL
      ),
      CONSTRAINT recurring_rules_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT recurring_rules_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT recurring_rules_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT,
      CONSTRAINT recurring_rules_category_owner_fk
        FOREIGN KEY (category_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (id, user_id)
        ON DELETE RESTRICT
    );
    CREATE INDEX recurring_rules_user_start_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      (user_id, starts_on, lower(title), id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_executions`)} (
      id uuid PRIMARY KEY,
      job_key varchar(100) NOT NULL UNIQUE,
      queue_job_id varchar(100) NOT NULL,
      due_through date NOT NULL,
      status varchar(24) NOT NULL,
      attempt_count integer NOT NULL DEFAULT 0,
      max_attempts integer NOT NULL,
      error_code varchar(64),
      started_at timestamptz,
      finished_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT recurrence_job_status_check CHECK (
        status IN ('queued', 'running', 'completed', 'retryable_failed', 'dead_letter')
      ),
      CONSTRAINT recurrence_job_attempt_check CHECK (
        attempt_count >= 0 AND max_attempts BETWEEN 1 AND 20
        AND attempt_count <= max_attempts
      ),
      CONSTRAINT recurrence_job_terminal_time_check CHECK (
        (status IN ('completed', 'dead_letter') AND finished_at IS NOT NULL)
        OR (status NOT IN ('completed', 'dead_letter'))
      )
    );
    CREATE INDEX recurrence_job_status_updated_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_executions`)}
      (status, updated_at, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_events`)} (
      id uuid PRIMARY KEY,
      execution_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_executions`)} (id)
        ON DELETE CASCADE,
      status varchar(24) NOT NULL,
      attempt integer NOT NULL,
      error_code varchar(64),
      occurred_at timestamptz NOT NULL,
      CONSTRAINT recurrence_job_event_status_check CHECK (
        status IN ('queued', 'running', 'completed', 'retryable_failed', 'dead_letter')
      ),
      CONSTRAINT recurrence_job_event_attempt_check CHECK (attempt >= 0)
    );
    CREATE INDEX recurrence_job_events_execution_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_events`)}
      (execution_id, occurred_at, id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)} (
      id uuid PRIMARY KEY,
      rule_id uuid NOT NULL,
      user_id uuid NOT NULL,
      due_on date NOT NULL,
      economic_type varchar(16) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      category_id uuid,
      state varchar(16) NOT NULL DEFAULT 'forecast',
      job_execution_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_executions`)} (id)
        ON DELETE RESTRICT,
      created_at timestamptz NOT NULL,
      CONSTRAINT recurring_occurrences_rule_owner_fk
        FOREIGN KEY (rule_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} (id, user_id)
        ON DELETE CASCADE,
      CONSTRAINT recurring_occurrences_category_owner_fk
        FOREIGN KEY (category_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT recurring_occurrences_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT recurring_occurrences_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT,
      CONSTRAINT recurring_occurrences_economic_type_check CHECK (
        economic_type IN ('income', 'expense', 'transfer')
      ),
      CONSTRAINT recurring_occurrences_amount_check CHECK (amount > 0),
      CONSTRAINT recurring_occurrences_state_check CHECK (state = 'forecast'),
      CONSTRAINT recurring_occurrences_transfer_category_check CHECK (
        economic_type <> 'transfer' OR category_id IS NULL
      ),
      CONSTRAINT recurring_occurrences_rule_due_unique UNIQUE (rule_id, due_on)
    );
    CREATE INDEX recurring_occurrences_user_due_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)}
      (user_id, due_on, rule_id);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_recurring_category_semantics()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      category_kind varchar(16);
    BEGIN
      IF NEW.category_id IS NULL THEN
        RETURN NEW;
      END IF;
      SELECT kind
        INTO category_kind
        FROM mymoneymap.categories
       WHERE id = NEW.category_id
         AND user_id = NEW.user_id;
      IF (
        (NEW.economic_type = 'income' AND category_kind <> 'income')
        OR (NEW.economic_type = 'expense' AND category_kind <> 'spending')
        OR NEW.economic_type = 'transfer'
      ) THEN
        RAISE EXCEPTION 'recurring category kind does not match economic type'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER recurring_rules_category_kind_guard
      BEFORE INSERT OR UPDATE OF category_id, economic_type
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_recurring_category_semantics();
    CREATE TRIGGER recurring_occurrences_category_kind_guard
      BEFORE INSERT
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_recurring_category_semantics();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS recurring_occurrences_category_kind_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)};
    DROP TRIGGER IF EXISTS recurring_rules_category_kind_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_recurring_category_semantics();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_events`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurrence_job_executions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)};
  `.execute(database);
}
