import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      kind varchar(32) NOT NULL,
      module_reference_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT ledger_accounts_kind_check CHECK (
        kind IN (
          'cash', 'goal', 'emergency_reserve', 'investment', 'loan_liability',
          'securities_cash', 'securities_holding'
        )
      ),
      CONSTRAINT ledger_accounts_module_reference_check CHECK (
        (kind = 'cash' AND module_reference_id IS NULL)
        OR (kind <> 'cash' AND module_reference_id IS NOT NULL)
      ),
      CONSTRAINT ledger_accounts_id_user_unique UNIQUE (id, user_id)
    );
    CREATE UNIQUE INDEX ledger_accounts_default_cash_unique
      ON ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (user_id)
      WHERE kind = 'cash';
    CREATE UNIQUE INDEX ledger_accounts_module_reference_unique
      ON ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (user_id, kind, module_reference_id)
      WHERE module_reference_id IS NOT NULL;

    INSERT INTO ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)}
      (id, user_id, kind, module_reference_id, created_at)
    SELECT (
      substr(md5(id::text || ':default-cash'), 1, 8) || '-' ||
      substr(md5(id::text || ':default-cash'), 9, 4) || '-' ||
      '4' || substr(md5(id::text || ':default-cash'), 14, 3) || '-' ||
      '8' || substr(md5(id::text || ':default-cash'), 18, 3) || '-' ||
      substr(md5(id::text || ':default-cash'), 21, 12)
    )::uuid, id, 'cash', NULL, created_at
    FROM ${sql.table(`${APPLICATION_SCHEMA}.users`)}
    WHERE role IN ('free', 'premium');

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.create_default_cash_account()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      account_hash text;
    BEGIN
      IF NEW.role IN ('free', 'premium') THEN
        account_hash := md5(NEW.id::text || ':default-cash');
        INSERT INTO mymoneymap.ledger_accounts
          (id, user_id, kind, module_reference_id, created_at)
        VALUES (
          (
            substr(account_hash, 1, 8) || '-' ||
            substr(account_hash, 9, 4) || '-' ||
            '4' || substr(account_hash, 14, 3) || '-' ||
            '8' || substr(account_hash, 18, 3) || '-' ||
            substr(account_hash, 21, 12)
          )::uuid,
          NEW.id,
          'cash',
          NULL,
          NEW.created_at
        )
        ON CONFLICT (user_id) WHERE kind = 'cash' DO NOTHING;
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER users_create_default_cash_account
      AFTER INSERT OR UPDATE OF role ON ${sql.table(`${APPLICATION_SCHEMA}.users`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.create_default_cash_account();

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE RESTRICT,
      economic_type varchar(32) NOT NULL,
      category_id uuid,
      note varchar(1000),
      source_module varchar(32) NOT NULL,
      source_reference_id uuid,
      idempotency_key_hash char(64) NOT NULL,
      posted_on date NOT NULL,
      effective_at timestamptz NOT NULL,
      created_at timestamptz NOT NULL,
      actor_user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE RESTRICT,
      reverses_entry_id uuid,
      replaces_entry_id uuid,
      CONSTRAINT journal_entries_economic_type_check CHECK (
        economic_type IN (
          'external_income', 'external_expense', 'internal_transfer', 'adjustment',
          'fee', 'interest', 'dividend', 'loan_repayment', 'trade_cash'
        )
      ),
      CONSTRAINT journal_entries_category_check CHECK (
        category_id IS NULL OR economic_type IN ('external_income', 'external_expense', 'fee')
      ),
      CONSTRAINT journal_entries_note_check CHECK (
        note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000
      ),
      CONSTRAINT journal_entries_source_module_check CHECK (
        source_module IN (
          'manual', 'scheduling', 'goals', 'emergency_fund', 'loans',
          'investments', 'securities', 'migration'
        )
      ),
      CONSTRAINT journal_entries_source_reference_check CHECK (
        source_module = 'manual' OR source_reference_id IS NOT NULL
      ),
      CONSTRAINT journal_entries_idempotency_hash_check CHECK (
        idempotency_key_hash ~ '^[0-9a-f]{64}$'
      ),
      CONSTRAINT journal_entries_actor_check CHECK (actor_user_id = user_id),
      CONSTRAINT journal_entries_correction_link_check CHECK (
        NOT (reverses_entry_id IS NOT NULL AND replaces_entry_id IS NOT NULL)
      ),
      CONSTRAINT journal_entries_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT journal_entries_reverses_owner_fk
        FOREIGN KEY (reverses_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT journal_entries_replaces_owner_fk
        FOREIGN KEY (replaces_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT journal_entries_source_idempotency_unique
        UNIQUE (user_id, source_module, idempotency_key_hash)
    );
    CREATE UNIQUE INDEX journal_entries_one_reversal
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (user_id, reverses_entry_id)
      WHERE reverses_entry_id IS NOT NULL;
    CREATE UNIQUE INDEX journal_entries_one_replacement
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (user_id, replaces_entry_id)
      WHERE replaces_entry_id IS NOT NULL;
    CREATE INDEX journal_entries_user_posted_cursor
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (user_id, posted_on DESC, effective_at DESC, id DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)} (
      id uuid PRIMARY KEY,
      entry_id uuid NOT NULL,
      user_id uuid NOT NULL,
      account_id uuid,
      side varchar(6) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT journal_legs_entry_owner_fk
        FOREIGN KEY (entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT journal_legs_account_owner_fk
        FOREIGN KEY (account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT journal_legs_side_check CHECK (side IN ('debit', 'credit')),
      CONSTRAINT journal_legs_amount_check CHECK (amount > 0),
      CONSTRAINT journal_legs_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
      CONSTRAINT journal_legs_entry_account_side_unique UNIQUE (entry_id, account_id, side)
    );
    CREATE INDEX journal_legs_account_balance_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)} (user_id, account_id, currency, entry_id);

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.reject_immutable_journal_change()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      RAISE EXCEPTION 'posted journal records are immutable' USING ERRCODE = '55000';
    END;
    $$;
    CREATE TRIGGER journal_entries_immutable
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.reject_immutable_journal_change();
    CREATE TRIGGER journal_legs_immutable
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.reject_immutable_journal_change();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_balanced_journal_entry()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_entry uuid;
      target_type varchar(32);
      reversed_entry uuid;
      leg_count integer;
      owned_count integer;
      owned_debit_count integer;
      debit_count integer;
      credit_count integer;
      currency_count integer;
      imbalance numeric(30, 12);
      distinct_owned_accounts integer;
    BEGIN
      IF TG_TABLE_NAME = 'journal_entries' THEN
        target_entry := NEW.id;
      ELSE
        target_entry := NEW.entry_id;
      END IF;
      SELECT economic_type, reverses_entry_id INTO target_type, reversed_entry
        FROM mymoneymap.journal_entries
       WHERE id = target_entry;

      SELECT
        count(*)::integer,
        count(account_id)::integer,
        count(*) FILTER (WHERE account_id IS NOT NULL AND side = 'debit')::integer,
        count(*) FILTER (WHERE side = 'debit')::integer,
        count(*) FILTER (WHERE side = 'credit')::integer,
        count(DISTINCT currency)::integer,
        COALESCE(sum(CASE WHEN side = 'debit' THEN amount ELSE -amount END), 0),
        count(DISTINCT account_id)::integer
      INTO leg_count, owned_count, owned_debit_count, debit_count, credit_count, currency_count, imbalance,
           distinct_owned_accounts
      FROM mymoneymap.journal_legs
      WHERE entry_id = target_entry;

      IF leg_count <> 2 OR debit_count <> 1 OR credit_count <> 1
         OR currency_count <> 1 OR imbalance <> 0 THEN
        RAISE EXCEPTION 'journal entry must contain one balanced debit and credit in one currency'
          USING ERRCODE = '23514';
      END IF;

      IF target_type IN ('internal_transfer', 'loan_repayment') THEN
        IF owned_count <> 2 OR distinct_owned_accounts <> 2 THEN
          RAISE EXCEPTION 'transfer entries require two distinct owned accounts'
            USING ERRCODE = '23514';
        END IF;
      ELSIF owned_count <> 1 THEN
        RAISE EXCEPTION 'external journal entries require one owned account and one external leg'
          USING ERRCODE = '23514';
      END IF;

      IF reversed_entry IS NULL THEN
        IF target_type IN ('external_income', 'interest', 'dividend')
           AND owned_debit_count <> 1 THEN
          RAISE EXCEPTION 'income-like entries must increase the owned account'
            USING ERRCODE = '23514';
        END IF;
        IF target_type IN ('external_expense', 'fee')
           AND owned_debit_count <> 0 THEN
          RAISE EXCEPTION 'expense-like entries must decrease the owned account'
           USING ERRCODE = '23514';
        END IF;
      ELSE
        IF target_type <> (
          SELECT economic_type
            FROM mymoneymap.journal_entries
           WHERE id = reversed_entry
        ) OR EXISTS (
          SELECT account_id, side, amount, currency
            FROM mymoneymap.journal_legs
           WHERE entry_id = target_entry
          EXCEPT
          SELECT account_id,
                 CASE WHEN side = 'debit' THEN 'credit' ELSE 'debit' END,
                 amount,
                 currency
            FROM mymoneymap.journal_legs
           WHERE entry_id = reversed_entry
        ) OR EXISTS (
          SELECT account_id,
                 CASE WHEN side = 'debit' THEN 'credit' ELSE 'debit' END,
                 amount,
                 currency
            FROM mymoneymap.journal_legs
           WHERE entry_id = reversed_entry
          EXCEPT
          SELECT account_id, side, amount, currency
            FROM mymoneymap.journal_legs
           WHERE entry_id = target_entry
        ) THEN
          RAISE EXCEPTION 'reversal legs must exactly invert the original entry'
            USING ERRCODE = '23514';
        END IF;
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER journal_entries_balance_check
      AFTER INSERT ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_balanced_journal_entry();
    CREATE CONSTRAINT TRIGGER journal_legs_balance_check
      AFTER INSERT ON ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_balanced_journal_entry();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS journal_legs_balance_check
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)};
    DROP TRIGGER IF EXISTS journal_entries_balance_check
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)};
    DROP TRIGGER IF EXISTS journal_legs_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)};
    DROP TRIGGER IF EXISTS journal_entries_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_balanced_journal_entry();
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.reject_immutable_journal_change();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)};
    DROP TRIGGER IF EXISTS users_create_default_cash_account
      ON ${sql.table(`${APPLICATION_SCHEMA}.users`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.create_default_cash_account();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)};
  `.execute(database);
}
