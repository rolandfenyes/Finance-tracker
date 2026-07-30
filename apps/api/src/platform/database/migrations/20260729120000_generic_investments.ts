import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.investments`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      type varchar(16) NOT NULL,
      name varchar(180) NOT NULL,
      provider varchar(180),
      identifier varchar(120),
      notes varchar(2000),
      currency char(3) NOT NULL,
      scenario_annual_rate numeric(30, 12),
      scenario_frequency varchar(16) NOT NULL,
      scenario_version varchar(64) NOT NULL,
      account_id uuid NOT NULL,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT investments_type_check CHECK (type IN ('savings', 'etf', 'stock')),
      CONSTRAINT investments_name_check CHECK (char_length(btrim(name)) BETWEEN 1 AND 180),
      CONSTRAINT investments_provider_check CHECK (provider IS NULL OR char_length(btrim(provider)) BETWEEN 1 AND 180),
      CONSTRAINT investments_identifier_check CHECK (identifier IS NULL OR char_length(btrim(identifier)) BETWEEN 1 AND 120),
      CONSTRAINT investments_notes_check CHECK (notes IS NULL OR char_length(btrim(notes)) BETWEEN 1 AND 2000),
      CONSTRAINT investments_rate_check CHECK (scenario_annual_rate IS NULL OR scenario_annual_rate >= 0),
      CONSTRAINT investments_frequency_check CHECK (scenario_frequency IN ('daily', 'weekly', 'monthly', 'annual')),
      CONSTRAINT investments_version_check CHECK (scenario_version = 'nominal_compound_scenario_v1'),
      CONSTRAINT investments_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT investments_account_unique UNIQUE (account_id),
      CONSTRAINT investments_currency_fk FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      CONSTRAINT investments_currency_membership_fk FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code) ON DELETE RESTRICT,
      CONSTRAINT investments_account_owner_fk FOREIGN KEY (account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id) ON DELETE RESTRICT
    );
    CREATE INDEX investments_user_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.investments`)} (user_id, created_at DESC, id DESC);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_account()
    RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM mymoneymap.ledger_accounts
         WHERE id = NEW.account_id AND user_id = NEW.user_id
           AND kind = 'investment' AND module_reference_id = NEW.id
      ) THEN
        RAISE EXCEPTION 'investment requires its owned investment account' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER investments_account_guard
      AFTER INSERT OR UPDATE OF user_id, account_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.investments`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_account();

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      investment_id uuid NOT NULL,
      journal_entry_id uuid NOT NULL,
      direction varchar(16) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      investment_amount numeric(30, 12) NOT NULL,
      investment_currency char(3) NOT NULL,
      occurred_on date NOT NULL,
      note varchar(1000),
      reversed_by_journal_entry_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT investment_movements_direction_check CHECK (direction IN ('deposit', 'withdrawal')),
      CONSTRAINT investment_movements_amount_check CHECK (amount > 0 AND investment_amount > 0),
      CONSTRAINT investment_movements_note_check CHECK (note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000),
      CONSTRAINT investment_movements_id_owner_investment_unique UNIQUE (id, user_id, investment_id),
      CONSTRAINT investment_movements_journal_unique UNIQUE (journal_entry_id),
      CONSTRAINT investment_movements_reversal_unique UNIQUE (reversed_by_journal_entry_id),
      CONSTRAINT investment_movements_investment_owner_fk FOREIGN KEY (investment_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.investments`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT investment_movements_journal_owner_fk FOREIGN KEY (journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT investment_movements_reversal_owner_fk FOREIGN KEY (reversed_by_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT investment_movements_currency_fk FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      CONSTRAINT investment_movements_investment_currency_fk FOREIGN KEY (investment_currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT
    );
    CREATE INDEX investment_movements_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)}
      (user_id, investment_id, occurred_on DESC, created_at DESC, id DESC);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_journal()
    RETURNS trigger LANGUAGE plpgsql AS $$
    DECLARE investment_account uuid; entry_type varchar(32); entry_module varchar(32);
      entry_reference uuid; reversal_target uuid; expected_side varchar(8);
    BEGIN
      SELECT account_id INTO investment_account FROM mymoneymap.investments
       WHERE id = NEW.investment_id AND user_id = NEW.user_id;
      SELECT economic_type, source_module, source_reference_id, reverses_entry_id
        INTO entry_type, entry_module, entry_reference, reversal_target
        FROM mymoneymap.journal_entries WHERE id = NEW.journal_entry_id AND user_id = NEW.user_id;
      expected_side := CASE WHEN NEW.direction = 'deposit' THEN 'debit' ELSE 'credit' END;
      IF entry_type <> 'internal_transfer' OR entry_module <> 'investments'
         OR entry_reference <> NEW.id OR reversal_target IS NOT NULL
         OR NOT EXISTS (
           SELECT 1 FROM mymoneymap.journal_legs
            WHERE entry_id = NEW.journal_entry_id AND user_id = NEW.user_id
              AND account_id = investment_account AND side = expected_side
              AND amount = NEW.amount AND currency = NEW.currency
         ) THEN
        RAISE EXCEPTION 'investment movement must reference its balanced transfer journal'
          USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_journal_entry_id AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.journal_entry_id
      ) THEN
        RAISE EXCEPTION 'investment movement reversal must invert its journal' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER investment_movement_journal_guard
      BEFORE INSERT OR UPDATE OF journal_entry_id, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_journal();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_immutable()
    RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN
      IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'investment movement history is immutable' USING ERRCODE = '23514';
      END IF;
      IF ROW(OLD.id, OLD.user_id, OLD.investment_id, OLD.journal_entry_id, OLD.direction,
        OLD.amount, OLD.currency, OLD.investment_amount, OLD.investment_currency,
        OLD.occurred_on, OLD.note, OLD.created_at)
        IS DISTINCT FROM ROW(NEW.id, NEW.user_id, NEW.investment_id, NEW.journal_entry_id,
        NEW.direction, NEW.amount, NEW.currency, NEW.investment_amount,
        NEW.investment_currency, NEW.occurred_on, NEW.note, NEW.created_at)
        OR OLD.reversed_by_journal_entry_id IS NOT NULL OR NEW.reversed_by_journal_entry_id IS NULL THEN
        RAISE EXCEPTION 'investment movement history is immutable' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER investment_movement_immutable_guard
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_immutable();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_nonnegative()
    RETURNS trigger LANGUAGE plpgsql AS $$
    DECLARE target_user uuid; target_investment uuid; derived_balance numeric(30, 12);
    BEGIN
      target_user := COALESCE(NEW.user_id, OLD.user_id);
      target_investment := COALESCE(NEW.investment_id, OLD.investment_id);
      SELECT COALESCE(sum(CASE WHEN direction = 'deposit' THEN investment_amount ELSE -investment_amount END)
        FILTER (WHERE reversed_by_journal_entry_id IS NULL), 0)
        INTO derived_balance FROM mymoneymap.investment_movements
       WHERE user_id = target_user AND investment_id = target_investment;
      IF derived_balance < 0 THEN
        RAISE EXCEPTION 'investment balance cannot be negative' USING ERRCODE = '23514';
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER investment_nonnegative_guard
      AFTER INSERT OR UPDATE OF reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_nonnegative();

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} ADD COLUMN investment_id uuid;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_investment_owner_fk FOREIGN KEY (investment_id, user_id)
      REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.investments`)} (id, user_id) ON DELETE RESTRICT;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_investment_transfer_check CHECK (
        investment_id IS NULL OR (
          economic_type = 'transfer' AND category_id IS NULL
          AND goal_id IS NULL AND loan_id IS NULL
        )
      );
    CREATE UNIQUE INDEX recurring_rules_one_investment_link
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} (user_id, investment_id)
      WHERE investment_id IS NOT NULL;
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP INDEX IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules_one_investment_link`)};
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_investment_transfer_check;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_investment_owner_fk;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} DROP COLUMN IF EXISTS investment_id;
    DROP TRIGGER IF EXISTS investment_nonnegative_guard ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_nonnegative();
    DROP TRIGGER IF EXISTS investment_movement_immutable_guard ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_immutable();
    DROP TRIGGER IF EXISTS investment_movement_journal_guard ON ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_movement_journal();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.investment_movements`)};
    DROP TRIGGER IF EXISTS investments_account_guard ON ${sql.table(`${APPLICATION_SCHEMA}.investments`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_investment_account();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.investments`)};
  `.execute(database);
}
