import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.budget_rules`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      label varchar(120) NOT NULL,
      percent numeric(7, 4) NOT NULL,
      target_hint varchar(500),
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT budget_rules_label_check CHECK (
        char_length(btrim(label)) BETWEEN 1 AND 120
      ),
      CONSTRAINT budget_rules_percent_check CHECK (
        percent >= 0 AND percent <= 100
      ),
      CONSTRAINT budget_rules_target_hint_check CHECK (
        target_hint IS NULL OR char_length(btrim(target_hint)) BETWEEN 1 AND 500
      ),
      CONSTRAINT budget_rules_id_user_unique UNIQUE (id, user_id)
    );
    CREATE INDEX budget_rules_user_label_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.budget_rules`)} (user_id, lower(label), id);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      label varchar(120) NOT NULL,
      kind varchar(16) NOT NULL,
      color varchar(7) NOT NULL,
      budget_rule_id uuid,
      system_key varchar(64),
      protected boolean NOT NULL DEFAULT false,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT categories_label_check CHECK (
        char_length(btrim(label)) BETWEEN 1 AND 120
      ),
      CONSTRAINT categories_kind_check CHECK (kind IN ('income', 'spending')),
      CONSTRAINT categories_color_check CHECK (
        color ~ '^#([0-9A-F]{3}|[0-9A-F]{6})$'
      ),
      CONSTRAINT categories_income_rule_check CHECK (
        kind = 'spending' OR budget_rule_id IS NULL
      ),
      CONSTRAINT categories_system_check CHECK (
        (system_key IS NULL AND NOT protected)
        OR (system_key IS NOT NULL AND protected)
      ),
      CONSTRAINT categories_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT categories_rule_owner_fk
        FOREIGN KEY (budget_rule_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.budget_rules`)} (id, user_id)
        ON DELETE RESTRICT
    );
    CREATE INDEX categories_user_kind_label_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (user_id, kind, lower(label), id);
    CREATE INDEX categories_user_rule_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (user_id, budget_rule_id)
      WHERE budget_rule_id IS NOT NULL;
    CREATE UNIQUE INDEX categories_user_system_key_unique
      ON ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (user_id, system_key)
      WHERE system_key IS NOT NULL;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      label varchar(120) NOT NULL,
      amount numeric(30, 12) NOT NULL,
      currency char(3) NOT NULL,
      valid_from date NOT NULL,
      valid_to date,
      category_id uuid,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT basic_incomes_label_check CHECK (
        char_length(btrim(label)) BETWEEN 1 AND 120
      ),
      CONSTRAINT basic_incomes_amount_check CHECK (amount > 0),
      CONSTRAINT basic_incomes_date_range_check CHECK (
        valid_to IS NULL OR valid_to >= valid_from
      ),
      CONSTRAINT basic_incomes_currency_fk
        FOREIGN KEY (currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      CONSTRAINT basic_incomes_currency_membership_fk
        FOREIGN KEY (user_id, currency)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id, code)
        ON DELETE RESTRICT,
      CONSTRAINT basic_incomes_category_owner_fk
        FOREIGN KEY (category_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (id, user_id)
        ON DELETE RESTRICT
    );
    CREATE INDEX basic_incomes_user_dates_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)}
      (user_id, valid_from, valid_to, lower(label), id);
    CREATE INDEX basic_incomes_user_category_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)} (user_id, category_id)
      WHERE category_id IS NOT NULL;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      ADD CONSTRAINT journal_entries_category_owner_fk
      FOREIGN KEY (category_id, user_id)
      REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.categories`)} (id, user_id)
      ON DELETE RESTRICT;

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_category_semantics()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      category_kind varchar(16);
    BEGIN
      IF TG_TABLE_NAME = 'categories' THEN
        IF NEW.kind <> OLD.kind AND (
          EXISTS (
            SELECT 1
              FROM mymoneymap.basic_incomes
             WHERE user_id = NEW.user_id
               AND category_id = NEW.id
          )
          OR EXISTS (
            SELECT 1
              FROM mymoneymap.journal_entries
             WHERE user_id = NEW.user_id
               AND category_id = NEW.id
          )
        ) THEN
          RAISE EXCEPTION 'referenced category kind cannot change'
            USING ERRCODE = '23514';
        END IF;
        RETURN NEW;
      END IF;

      IF NEW.category_id IS NULL THEN
        RETURN NEW;
      END IF;

      SELECT kind
        INTO category_kind
        FROM mymoneymap.categories
       WHERE id = NEW.category_id
         AND user_id = NEW.user_id;

      IF TG_TABLE_NAME = 'basic_incomes' AND category_kind <> 'income' THEN
        RAISE EXCEPTION 'basic income requires an income category'
          USING ERRCODE = '23514';
      END IF;
      IF TG_TABLE_NAME = 'basic_incomes' THEN
        RETURN NEW;
      END IF;
      IF (
        (NEW.economic_type = 'external_income' AND category_kind <> 'income')
        OR (
          NEW.economic_type IN ('external_expense', 'fee')
          AND category_kind <> 'spending'
        )
      ) THEN
        RAISE EXCEPTION 'journal category kind does not match economic type'
          USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER categories_kind_reference_guard
      BEFORE UPDATE OF kind ON ${sql.table(`${APPLICATION_SCHEMA}.categories`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_category_semantics();
    CREATE TRIGGER basic_incomes_category_kind_guard
      BEFORE INSERT OR UPDATE OF category_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_category_semantics();
    CREATE TRIGGER journal_entries_category_kind_guard
      BEFORE INSERT ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_category_semantics();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS journal_entries_category_kind_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)};
    DROP TRIGGER IF EXISTS basic_incomes_category_kind_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)};
    DROP TRIGGER IF EXISTS categories_kind_reference_guard
      ON ${sql.table(`${APPLICATION_SCHEMA}.categories`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_category_semantics();
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      DROP CONSTRAINT IF EXISTS journal_entries_category_owner_fk;
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.basic_incomes`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.categories`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.budget_rules`)};
  `.execute(database);
}
