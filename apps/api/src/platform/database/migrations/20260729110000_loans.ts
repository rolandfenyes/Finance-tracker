import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.loans`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      title varchar(120) NOT NULL,
      principal numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      nominal_annual_rate numeric(30, 12) NOT NULL,
      term_months integer NOT NULL,
      starts_on date NOT NULL,
      ends_on date,
      payment_day integer,
      extra_payment_scenario numeric(30, 12) NOT NULL DEFAULT 0,
      insurance_monthly numeric(30, 12) NOT NULL DEFAULT 0,
      estimate_version varchar(64) NOT NULL,
      liability_account_id uuid NOT NULL,
      completed_at timestamptz,
      archived_at timestamptz,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT loans_title_check CHECK (char_length(btrim(title)) BETWEEN 1 AND 120),
      CONSTRAINT loans_principal_check CHECK (principal > 0),
      CONSTRAINT loans_rate_check CHECK (nominal_annual_rate >= 0),
      CONSTRAINT loans_term_check CHECK (term_months > 0),
      CONSTRAINT loans_dates_check CHECK (ends_on IS NULL OR ends_on >= starts_on),
      CONSTRAINT loans_payment_day_check CHECK (payment_day IS NULL OR payment_day BETWEEN 1 AND 31),
      CONSTRAINT loans_scenario_check CHECK (
        extra_payment_scenario >= 0 AND insurance_monthly >= 0
      ),
      CONSTRAINT loans_estimate_version_check CHECK (
        estimate_version = 'standard_nominal_monthly_annuity_v1'
      ),
      CONSTRAINT loans_archive_check CHECK (archived_at IS NULL OR completed_at IS NOT NULL),
      CONSTRAINT loans_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT loans_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT loans_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT,
      CONSTRAINT loans_account_owner_fk
        FOREIGN KEY (liability_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id)
        ON DELETE RESTRICT
    );
    CREATE INDEX loans_user_lifecycle_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.loans`)}
      (user_id, archived_at, completed_at, starts_on DESC, id DESC);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_account()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF NOT EXISTS (
        SELECT 1
          FROM mymoneymap.ledger_accounts
         WHERE id = NEW.liability_account_id
           AND user_id = NEW.user_id
           AND kind = 'loan_liability'
           AND module_reference_id = NEW.id
      ) THEN
        RAISE EXCEPTION 'loan requires its owned liability account'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER loans_account_guard
      AFTER INSERT OR UPDATE OF user_id, liability_account_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.loans`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_account();

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)}
      ADD CONSTRAINT recurring_occurrences_id_user_unique UNIQUE (id, user_id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      loan_id uuid NOT NULL,
      journal_entry_id uuid NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      principal_component numeric(30, 12) NOT NULL,
      interest_component numeric(30, 12) NOT NULL,
      fee_component numeric(30, 12) NOT NULL,
      loan_principal_component numeric(30, 12) NOT NULL,
      loan_interest_component numeric(30, 12) NOT NULL,
      loan_fee_component numeric(30, 12) NOT NULL,
      loan_currency char(3) NOT NULL,
      conversion_status varchar(16) NOT NULL,
      conversion_rate numeric(30, 18),
      conversion_provider varchar(64),
      rate_at timestamptz,
      fetched_at timestamptz,
      paid_on date NOT NULL,
      source varchar(16) NOT NULL,
      recurring_occurrence_id uuid,
      note varchar(1000),
      reversed_by_journal_entry_id uuid,
      corrects_payment_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT loan_payments_amount_check CHECK (
        amount > 0
        AND principal_component >= 0
        AND interest_component >= 0
        AND fee_component >= 0
        AND principal_component + interest_component + fee_component = amount
        AND loan_principal_component >= 0
        AND loan_interest_component >= 0
        AND loan_fee_component >= 0
        AND loan_principal_component + loan_interest_component + loan_fee_component > 0
      ),
      CONSTRAINT loan_payments_source_check CHECK (source IN ('manual', 'scheduled')),
      CONSTRAINT loan_payments_source_occurrence_check CHECK (
        (source = 'manual' AND recurring_occurrence_id IS NULL)
        OR (source = 'scheduled' AND recurring_occurrence_id IS NOT NULL)
      ),
      CONSTRAINT loan_payments_conversion_check CHECK (
        conversion_status IN ('available', 'stale')
        AND conversion_rate IS NOT NULL
        AND conversion_rate > 0
        AND conversion_provider IS NOT NULL
        AND rate_at IS NOT NULL
        AND fetched_at IS NOT NULL
      ),
      CONSTRAINT loan_payments_note_check CHECK (
        note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000
      ),
      CONSTRAINT loan_payments_id_owner_loan_unique UNIQUE (id, user_id, loan_id),
      CONSTRAINT loan_payments_journal_unique UNIQUE (journal_entry_id),
      CONSTRAINT loan_payments_reversal_unique UNIQUE (reversed_by_journal_entry_id),
      CONSTRAINT loan_payments_occurrence_unique UNIQUE (recurring_occurrence_id),
      CONSTRAINT loan_payments_loan_owner_fk
        FOREIGN KEY (loan_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.loans`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_journal_owner_fk
        FOREIGN KEY (journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_reversal_owner_fk
        FOREIGN KEY (reversed_by_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_correction_owner_fk
        FOREIGN KEY (corrects_payment_id, user_id, loan_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)} (id, user_id, loan_id)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_occurrence_owner_fk
        FOREIGN KEY (recurring_occurrence_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT loan_payments_loan_currency_fk
        FOREIGN KEY (loan_currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT
    );
    CREATE INDEX loan_payments_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)}
      (user_id, loan_id, paid_on DESC, created_at DESC, id DESC);

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD COLUMN loan_id uuid;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_loan_owner_fk
      FOREIGN KEY (loan_id, user_id)
      REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.loans`)} (id, user_id)
      ON DELETE RESTRICT;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      ADD CONSTRAINT recurring_rules_loan_expense_check CHECK (
        loan_id IS NULL OR (
          economic_type = 'expense'
          AND category_id IS NULL
          AND goal_id IS NULL
        )
      );
    CREATE UNIQUE INDEX recurring_rules_one_loan_link
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)} (user_id, loan_id)
      WHERE loan_id IS NOT NULL;

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_recurring_rule()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_currency char(3);
      target_completed timestamptz;
      target_archived timestamptz;
    BEGIN
      IF NEW.loan_id IS NULL THEN
        RETURN NEW;
      END IF;
      SELECT currency, completed_at, archived_at
        INTO target_currency, target_completed, target_archived
        FROM mymoneymap.loans
       WHERE id = NEW.loan_id
         AND user_id = NEW.user_id;
      IF target_currency IS NULL
         OR target_completed IS NOT NULL
         OR target_archived IS NOT NULL THEN
        RAISE EXCEPTION 'loan recurring rule requires an open owned loan'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER recurring_rules_loan_guard
      BEFORE INSERT OR UPDATE OF loan_id, user_id, economic_type, category_id, goal_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_recurring_rule();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_journal()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      loan_account uuid;
      entry_type varchar(32);
      entry_module varchar(32);
      entry_reference uuid;
      reversal_target uuid;
    BEGIN
      SELECT liability_account_id INTO loan_account
        FROM mymoneymap.loans
       WHERE id = NEW.loan_id AND user_id = NEW.user_id;
      SELECT economic_type, source_module, source_reference_id, reverses_entry_id
        INTO entry_type, entry_module, entry_reference, reversal_target
        FROM mymoneymap.journal_entries
       WHERE id = NEW.journal_entry_id AND user_id = NEW.user_id;
      IF entry_type <> 'loan_repayment'
         OR entry_module <> 'loans'
         OR entry_reference <> NEW.id
         OR reversal_target IS NOT NULL
         OR NOT EXISTS (
           SELECT 1 FROM mymoneymap.journal_legs
            WHERE entry_id = NEW.journal_entry_id
              AND user_id = NEW.user_id
              AND account_id = loan_account
              AND side = 'debit'
              AND amount = NEW.amount
              AND currency = NEW.currency
         ) THEN
        RAISE EXCEPTION 'loan payment must reference its posted repayment journal'
          USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_journal_entry_id
           AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.journal_entry_id
      ) THEN
        RAISE EXCEPTION 'loan payment reversal must invert its payment journal'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER loan_payment_journal_guard
      BEFORE INSERT OR UPDATE OF journal_entry_id, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_journal();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_immutable()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'loan payment history is immutable' USING ERRCODE = '23514';
      END IF;
      IF ROW(
        OLD.id, OLD.user_id, OLD.loan_id, OLD.journal_entry_id, OLD.amount, OLD.currency,
        OLD.principal_component, OLD.interest_component, OLD.fee_component,
        OLD.loan_principal_component, OLD.loan_interest_component, OLD.loan_fee_component,
        OLD.loan_currency, OLD.conversion_status, OLD.conversion_rate,
        OLD.conversion_provider, OLD.rate_at, OLD.fetched_at, OLD.paid_on, OLD.source,
        OLD.recurring_occurrence_id, OLD.note, OLD.corrects_payment_id, OLD.created_at
      ) IS DISTINCT FROM ROW(
        NEW.id, NEW.user_id, NEW.loan_id, NEW.journal_entry_id, NEW.amount, NEW.currency,
        NEW.principal_component, NEW.interest_component, NEW.fee_component,
        NEW.loan_principal_component, NEW.loan_interest_component, NEW.loan_fee_component,
        NEW.loan_currency, NEW.conversion_status, NEW.conversion_rate,
        NEW.conversion_provider, NEW.rate_at, NEW.fetched_at, NEW.paid_on, NEW.source,
        NEW.recurring_occurrence_id, NEW.note, NEW.corrects_payment_id, NEW.created_at
      ) OR OLD.reversed_by_journal_entry_id IS NOT NULL
        OR NEW.reversed_by_journal_entry_id IS NULL THEN
        RAISE EXCEPTION 'loan payment history is immutable' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER loan_payment_immutable_guard
      BEFORE UPDATE OR DELETE
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_immutable();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_not_overpaid()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_user uuid;
      target_loan uuid;
      opening_principal numeric(30, 12);
      paid_principal numeric(30, 12);
    BEGIN
      IF TG_TABLE_NAME = 'loans' THEN
        target_user := NEW.user_id;
        target_loan := NEW.id;
      ELSE
        target_user := COALESCE(NEW.user_id, OLD.user_id);
        target_loan := COALESCE(NEW.loan_id, OLD.loan_id);
      END IF;
      SELECT principal INTO opening_principal
        FROM mymoneymap.loans
       WHERE id = target_loan AND user_id = target_user;
      SELECT COALESCE(sum(loan_principal_component)
        FILTER (WHERE reversed_by_journal_entry_id IS NULL), 0)
        INTO paid_principal
        FROM mymoneymap.loan_payments
       WHERE loan_id = target_loan AND user_id = target_user;
      IF paid_principal > opening_principal THEN
        RAISE EXCEPTION 'loan principal overpayment is not permitted'
          USING ERRCODE = '23514';
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER loans_overpayment_guard
      AFTER UPDATE OF principal ON ${sql.table(`${APPLICATION_SCHEMA}.loans`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_not_overpaid();
    CREATE CONSTRAINT TRIGGER loan_payments_overpayment_guard
      AFTER INSERT OR UPDATE OF loan_principal_component, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_not_overpaid();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS loan_payments_overpayment_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)};
    DROP TRIGGER IF EXISTS loans_overpayment_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.loans`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_not_overpaid();
    DROP TRIGGER IF EXISTS loan_payment_immutable_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_immutable();
    DROP TRIGGER IF EXISTS loan_payment_journal_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_payment_journal();
    DROP TRIGGER IF EXISTS recurring_rules_loan_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_recurring_rule();
    DROP INDEX IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules_one_loan_link`)};
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_loan_expense_check;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP CONSTRAINT IF EXISTS recurring_rules_loan_owner_fk;
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_rules`)}
      DROP COLUMN IF EXISTS loan_id;
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.loan_payments`)};
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.recurring_occurrences`)}
      DROP CONSTRAINT IF EXISTS recurring_occurrences_id_user_unique;
    DROP TRIGGER IF EXISTS loans_account_guard ON ${sql.table(`${APPLICATION_SCHEMA}.loans`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_loan_account();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.loans`)};
  `.execute(database);
}
