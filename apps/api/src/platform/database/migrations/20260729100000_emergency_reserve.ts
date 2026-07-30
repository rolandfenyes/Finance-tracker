import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserves`)} (
      user_id uuid PRIMARY KEY
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      target_amount numeric(30, 12) NOT NULL DEFAULT 0,
      currency char(3) NOT NULL,
      reserve_account_id uuid NOT NULL,
      linked_investment_account_id uuid,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT emergency_reserves_target_check CHECK (target_amount >= 0),
      CONSTRAINT emergency_reserves_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserves_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserves_account_owner_fk
        FOREIGN KEY (reserve_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserves_investment_account_owner_fk
        FOREIGN KEY (linked_investment_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserves_distinct_accounts_check CHECK (
        linked_investment_account_id IS NULL
        OR linked_investment_account_id <> reserve_account_id
      )
    );

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_accounts()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF NOT EXISTS (
        SELECT 1
          FROM mymoneymap.ledger_accounts
         WHERE id = NEW.reserve_account_id
           AND user_id = NEW.user_id
           AND kind = 'emergency_reserve'
      ) THEN
        RAISE EXCEPTION 'emergency reserve requires its owned reserve account'
          USING ERRCODE = '23514';
      END IF;
      IF NEW.linked_investment_account_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
          FROM mymoneymap.ledger_accounts
         WHERE id = NEW.linked_investment_account_id
           AND user_id = NEW.user_id
           AND kind = 'investment'
      ) THEN
        RAISE EXCEPTION 'linked investment must be an owned investment account'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER emergency_reserve_accounts_guard
      BEFORE INSERT OR UPDATE OF user_id, reserve_account_id, linked_investment_account_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserves`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_accounts();

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      journal_entry_id uuid NOT NULL,
      holding_account_id uuid NOT NULL,
      direction varchar(16) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      reserve_amount numeric(30, 12) NOT NULL,
      reserve_currency char(3) NOT NULL,
      occurred_on date NOT NULL,
      note varchar(1000),
      reversed_by_journal_entry_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT emergency_reserve_movements_direction_check CHECK (
        direction IN ('contribution', 'withdrawal')
      ),
      CONSTRAINT emergency_reserve_movements_amount_check CHECK (
        amount > 0 AND reserve_amount > 0
      ),
      CONSTRAINT emergency_reserve_movements_note_check CHECK (
        note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000
      ),
      CONSTRAINT emergency_reserve_movements_id_owner_unique UNIQUE (id, user_id),
      CONSTRAINT emergency_reserve_movements_journal_unique UNIQUE (journal_entry_id),
      CONSTRAINT emergency_reserve_movements_reversal_unique
        UNIQUE (reversed_by_journal_entry_id),
      CONSTRAINT emergency_reserve_movements_owner_fk
        FOREIGN KEY (user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserves`)} (user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserve_movements_journal_owner_fk
        FOREIGN KEY (journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserve_movements_holding_account_owner_fk
        FOREIGN KEY (holding_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserve_movements_reversal_owner_fk
        FOREIGN KEY (reversed_by_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserve_movements_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT emergency_reserve_movements_reserve_currency_fk
        FOREIGN KEY (reserve_currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT
    );
    CREATE INDEX emergency_reserve_movements_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)}
      (user_id, occurred_on DESC, created_at DESC, id DESC);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_movement_journal()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      entry_type varchar(32);
      entry_module varchar(32);
      entry_reference uuid;
      reversal_target uuid;
      expected_side varchar(8);
    BEGIN
      SELECT economic_type, source_module, source_reference_id, reverses_entry_id
        INTO entry_type, entry_module, entry_reference, reversal_target
        FROM mymoneymap.journal_entries
       WHERE id = NEW.journal_entry_id
         AND user_id = NEW.user_id;
      expected_side := CASE
        WHEN NEW.direction = 'contribution' THEN 'debit'
        ELSE 'credit'
      END;
      IF entry_type <> 'internal_transfer'
         OR entry_module <> 'emergency_fund'
         OR entry_reference <> NEW.id
         OR reversal_target IS NOT NULL
         OR NOT EXISTS (
           SELECT 1
             FROM mymoneymap.journal_legs
            WHERE entry_id = NEW.journal_entry_id
              AND user_id = NEW.user_id
              AND account_id = NEW.holding_account_id
              AND side = expected_side
              AND amount = NEW.amount
              AND currency = NEW.currency
         ) THEN
        RAISE EXCEPTION 'emergency movement must reference its single posted transfer'
          USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
          FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_journal_entry_id
           AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.journal_entry_id
      ) THEN
        RAISE EXCEPTION 'emergency movement reversal must invert its movement journal'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER emergency_reserve_movement_journal_guard
      BEFORE INSERT OR UPDATE OF journal_entry_id, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_movement_journal();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_nonnegative()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_user uuid;
      derived_balance numeric(30, 12);
    BEGIN
      target_user := COALESCE(NEW.user_id, OLD.user_id);
      SELECT COALESCE(sum(
        CASE WHEN direction = 'contribution' THEN reserve_amount ELSE -reserve_amount END
      ) FILTER (WHERE reversed_by_journal_entry_id IS NULL), 0)
        INTO derived_balance
        FROM mymoneymap.emergency_reserve_movements
       WHERE user_id = target_user;
      IF derived_balance < 0 THEN
        RAISE EXCEPTION 'emergency reserve allocation cannot be negative'
          USING ERRCODE = '23514';
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER emergency_reserve_nonnegative_guard
      AFTER INSERT OR UPDATE OF reserve_amount, direction, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_nonnegative();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS emergency_reserve_nonnegative_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_nonnegative();
    DROP TRIGGER IF EXISTS emergency_reserve_movement_journal_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_movement_journal();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserve_movements`)};
    DROP TRIGGER IF EXISTS emergency_reserve_accounts_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserves`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_emergency_reserve_accounts();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.emergency_reserves`)};
  `.execute(database);
}
