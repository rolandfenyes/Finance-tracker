import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.goals`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      title varchar(120) NOT NULL,
      target_amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      deadline date,
      priority integer NOT NULL DEFAULT 3,
      status varchar(16) NOT NULL DEFAULT 'active',
      category_id uuid
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (id)
        ON DELETE SET NULL,
      archived_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT goals_title_check CHECK (
        char_length(btrim(title)) BETWEEN 1 AND 120
      ),
      CONSTRAINT goals_target_check CHECK (target_amount > 0),
      CONSTRAINT goals_status_check CHECK (
        status IN ('active', 'paused', 'completed')
      ),
      CONSTRAINT goals_archive_time_check CHECK (
        archived_at IS NULL OR archived_at >= created_at
      ),
      CONSTRAINT goals_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT goals_id_user_currency_unique UNIQUE (id, user_id, currency),
      CONSTRAINT goals_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT goals_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT
    );
    CREATE INDEX goals_user_lifecycle_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)}
      (user_id, archived_at, status, deadline, priority, id);
    CREATE INDEX goals_category_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)} (user_id, category_id);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_category_owner()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF NEW.category_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
          FROM mymoneymap.categories
         WHERE id = NEW.category_id
           AND user_id = NEW.user_id
      ) THEN
        RAISE EXCEPTION 'goal category must be owned by the goal owner'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER goals_category_owner_guard
      BEFORE INSERT OR UPDATE OF category_id, user_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_category_owner();

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      goal_id uuid NOT NULL,
      journal_entry_id uuid NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      goal_amount numeric(30, 12) NOT NULL,
      goal_currency char(3) NOT NULL,
      occurred_on date NOT NULL,
      note varchar(1000),
      reversed_by_journal_entry_id uuid,
      corrects_contribution_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT goal_contributions_amount_check CHECK (
        amount > 0 AND goal_amount > 0
      ),
      CONSTRAINT goal_contributions_note_check CHECK (
        note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000
      ),
      CONSTRAINT goal_contributions_id_owner_goal_unique
        UNIQUE (id, user_id, goal_id),
      CONSTRAINT goal_contributions_journal_unique
        UNIQUE (journal_entry_id),
      CONSTRAINT goal_contributions_reversal_unique
        UNIQUE (reversed_by_journal_entry_id),
      CONSTRAINT goal_contributions_correction_unique
        UNIQUE (corrects_contribution_id),
      CONSTRAINT goal_contributions_goal_owner_currency_fk
        FOREIGN KEY (goal_id, user_id, goal_currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.goals`)} (id, user_id, currency)
        ON DELETE RESTRICT,
      CONSTRAINT goal_contributions_journal_owner_fk
        FOREIGN KEY (journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT goal_contributions_reversal_owner_fk
        FOREIGN KEY (reversed_by_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT goal_contributions_correction_owner_fk
        FOREIGN KEY (corrects_contribution_id, user_id, goal_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)} (id, user_id, goal_id)
        ON DELETE RESTRICT,
      CONSTRAINT goal_contributions_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT goal_contributions_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT
    );
    CREATE INDEX goal_contributions_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)}
      (user_id, goal_id, occurred_on, created_at, id);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_contribution_journal()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      entry_type varchar(32);
      entry_module varchar(32);
      entry_reference uuid;
      reversal_target uuid;
    BEGIN
      SELECT economic_type, source_module, source_reference_id, reverses_entry_id
        INTO entry_type, entry_module, entry_reference, reversal_target
        FROM mymoneymap.journal_entries
       WHERE id = NEW.journal_entry_id
         AND user_id = NEW.user_id;
      IF entry_type <> 'internal_transfer'
         OR entry_module <> 'goals'
         OR entry_reference <> NEW.id
         OR reversal_target IS NOT NULL
         OR NOT EXISTS (
           SELECT 1
             FROM mymoneymap.journal_legs l
             JOIN mymoneymap.ledger_accounts a
               ON a.id = l.account_id
              AND a.user_id = l.user_id
            WHERE l.entry_id = NEW.journal_entry_id
              AND l.user_id = NEW.user_id
              AND l.side = 'debit'
              AND a.kind = 'goal'
              AND a.module_reference_id = NEW.goal_id
         ) THEN
        RAISE EXCEPTION 'goal contribution must reference its posted goal transfer'
          USING ERRCODE = '23514';
      END IF;

      IF NEW.reversed_by_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
          FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_journal_entry_id
           AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.journal_entry_id
      ) THEN
        RAISE EXCEPTION 'goal contribution reversal must invert its contribution journal'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER goal_contribution_journal_guard
      BEFORE INSERT OR UPDATE OF journal_entry_id, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_contribution_journal();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_not_overfunded()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_goal uuid;
      target_user uuid;
      goal_target numeric(30, 12);
      contributed numeric(30, 12);
    BEGIN
      IF TG_TABLE_NAME = 'goals' THEN
        target_goal := NEW.id;
        target_user := NEW.user_id;
        goal_target := NEW.target_amount;
      ELSE
        target_goal := NEW.goal_id;
        target_user := NEW.user_id;
        SELECT target_amount
          INTO goal_target
          FROM mymoneymap.goals
         WHERE id = target_goal
           AND user_id = target_user;
      END IF;
      SELECT COALESCE(sum(goal_amount), 0)
        INTO contributed
        FROM mymoneymap.goal_contributions
       WHERE goal_id = target_goal
         AND user_id = target_user
         AND reversed_by_journal_entry_id IS NULL;
      IF contributed > goal_target THEN
        RAISE EXCEPTION 'goal cannot be overfunded'
          USING ERRCODE = '23514';
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER goals_overfund_guard
      AFTER INSERT OR UPDATE OF target_amount
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_not_overfunded();
    CREATE CONSTRAINT TRIGGER goal_contributions_overfund_guard
      AFTER INSERT OR UPDATE OF goal_amount, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_not_overfunded();

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD COLUMN goal_id uuid;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_goal_owner_fk
      FOREIGN KEY (goal_id, user_id)
      REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.goals`)} (id, user_id)
      ON DELETE RESTRICT;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_goal_transfer_check CHECK (
        goal_id IS NULL OR (economic_type = 'transfer' AND category_id IS NULL)
      );
    CREATE UNIQUE INDEX recurring_rules_one_goal_link
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} (user_id, goal_id)
      WHERE goal_id IS NOT NULL;

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_recurring_rule()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      goal_currency char(3);
      goal_status varchar(16);
      goal_archived_at timestamptz;
    BEGIN
      IF NEW.goal_id IS NULL THEN
        RETURN NEW;
      END IF;
      SELECT currency, status, archived_at
        INTO goal_currency, goal_status, goal_archived_at
        FROM mymoneymap.goals
       WHERE id = NEW.goal_id
         AND user_id = NEW.user_id;
      IF goal_currency IS NULL
         OR NEW.currency <> goal_currency
         OR goal_status = 'completed'
         OR goal_archived_at IS NOT NULL THEN
        RAISE EXCEPTION 'goal recurring rule requires an open owned goal in its currency'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER recurring_rules_goal_guard
      BEFORE INSERT OR UPDATE OF goal_id, user_id, currency, economic_type, category_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_recurring_rule();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS recurring_rules_goal_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_recurring_rule();
    DROP INDEX IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules_one_goal_link`)};
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_goal_transfer_check;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_goal_owner_fk;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP COLUMN IF EXISTS goal_id;
    DROP TRIGGER IF EXISTS goal_contributions_overfund_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)};
    DROP TRIGGER IF EXISTS goals_overfund_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_not_overfunded();
    DROP TRIGGER IF EXISTS goal_contribution_journal_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_contribution_journal();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.goal_contributions`)};
    DROP TRIGGER IF EXISTS goals_category_owner_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.goals`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_goal_category_owner();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.goals`)};
  `.execute(database);
}
