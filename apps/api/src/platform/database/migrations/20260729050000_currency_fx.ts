import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (
      code char(3) PRIMARY KEY,
      name varchar(100) NOT NULL,
      minor_unit smallint NOT NULL,
      rounding_mode varchar(16) NOT NULL DEFAULT 'HALF_EVEN',
      active boolean NOT NULL DEFAULT true,
      CONSTRAINT currencies_code_check CHECK (code ~ '^[A-Z]{3}$'),
      CONSTRAINT currencies_name_check CHECK (char_length(btrim(name)) BETWEEN 1 AND 100),
      CONSTRAINT currencies_minor_unit_check CHECK (minor_unit BETWEEN 0 AND 4),
      CONSTRAINT currencies_rounding_mode_check CHECK (
        rounding_mode IN ('DOWN', 'UP', 'HALF_UP', 'HALF_EVEN')
      )
    );

    INSERT INTO ${sql.table(`${APPLICATION_SCHEMA}.currencies`)}
      (code, name, minor_unit)
    VALUES
      ('AUD', 'Australian Dollar', 2),
      ('BRL', 'Brazilian Real', 2),
      ('CAD', 'Canadian Dollar', 2),
      ('CHF', 'Swiss Franc', 2),
      ('CNY', 'Chinese Yuan', 2),
      ('CZK', 'Czech Koruna', 2),
      ('DKK', 'Danish Krone', 2),
      ('EUR', 'Euro', 2),
      ('GBP', 'Pound Sterling', 2),
      ('HKD', 'Hong Kong Dollar', 2),
      ('HUF', 'Hungarian Forint', 2),
      ('IDR', 'Indonesian Rupiah', 2),
      ('ILS', 'Israeli New Shekel', 2),
      ('INR', 'Indian Rupee', 2),
      ('ISK', 'Icelandic Króna', 0),
      ('JPY', 'Japanese Yen', 0),
      ('KRW', 'South Korean Won', 0),
      ('MXN', 'Mexican Peso', 2),
      ('MYR', 'Malaysian Ringgit', 2),
      ('NOK', 'Norwegian Krone', 2),
      ('NZD', 'New Zealand Dollar', 2),
      ('PHP', 'Philippine Peso', 2),
      ('PLN', 'Polish Złoty', 2),
      ('RON', 'Romanian Leu', 2),
      ('SEK', 'Swedish Krona', 2),
      ('SGD', 'Singapore Dollar', 2),
      ('THB', 'Thai Baht', 2),
      ('TRY', 'Turkish Lira', 2),
      ('USD', 'US Dollar', 2),
      ('ZAR', 'South African Rand', 2);

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)}
      ADD CONSTRAINT journal_legs_currency_fk
      FOREIGN KEY (currency)
      REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
      ON DELETE RESTRICT;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (
      user_id uuid NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id)
        ON DELETE CASCADE,
      code char(3) NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      is_main boolean NOT NULL DEFAULT false,
      created_at timestamptz NOT NULL,
      PRIMARY KEY (user_id, code)
    );
    CREATE UNIQUE INDEX user_currencies_one_main
      ON ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} (user_id)
      WHERE is_main;

    INSERT INTO ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)}
      (user_id, code, is_main, created_at)
    SELECT id, 'HUF', true, created_at
      FROM ${sql.table(`${APPLICATION_SCHEMA}.users`)}
     WHERE role IN ('free', 'premium');

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.ensure_default_user_currency()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF NEW.role IN ('free', 'premium') THEN
        INSERT INTO mymoneymap.user_currencies (user_id, code, is_main, created_at)
        VALUES (NEW.id, 'HUF', true, NEW.created_at)
        ON CONFLICT (user_id, code) DO NOTHING;
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER users_ensure_default_currency
      AFTER INSERT OR UPDATE OF role ON ${sql.table(`${APPLICATION_SCHEMA}.users`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.ensure_default_user_currency();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_user_has_one_main_currency()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    DECLARE
      target_user uuid;
      finance_user boolean;
      main_count integer;
    BEGIN
      target_user := COALESCE(NEW.user_id, OLD.user_id);
      SELECT role IN ('free', 'premium')
        INTO finance_user
        FROM mymoneymap.users
       WHERE id = target_user;
      IF COALESCE(finance_user, false) THEN
        SELECT count(*)::integer
          INTO main_count
          FROM mymoneymap.user_currencies
         WHERE user_id = target_user
           AND is_main;
        IF main_count <> 1 THEN
          RAISE EXCEPTION 'personal-finance user must have exactly one main currency'
            USING ERRCODE = '23514';
        END IF;
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER user_currencies_main_required
      AFTER INSERT OR UPDATE OR DELETE
      ON ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_user_has_one_main_currency();

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.fx_quotes`)} (
      id uuid PRIMARY KEY,
      provider varchar(32) NOT NULL,
      base_code char(3) NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      quote_code char(3) NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      rate numeric(30, 18) NOT NULL,
      observed_on date NOT NULL,
      observed_at timestamptz NOT NULL,
      fetched_at timestamptz NOT NULL,
      quality varchar(24) NOT NULL,
      status varchar(16) NOT NULL,
      CONSTRAINT fx_quotes_pair_check CHECK (base_code <> quote_code),
      CONSTRAINT fx_quotes_eur_pivot_check CHECK (base_code = 'EUR'),
      CONSTRAINT fx_quotes_rate_check CHECK (rate > 0),
      CONSTRAINT fx_quotes_observation_time_check CHECK (
        observed_at <= fetched_at
        AND observed_on = (observed_at AT TIME ZONE 'UTC')::date
      ),
      CONSTRAINT fx_quotes_quality_check CHECK (
        quality IN ('provider_observed', 'legacy_imported')
      ),
      CONSTRAINT fx_quotes_status_check CHECK (status IN ('available', 'rejected')),
      CONSTRAINT fx_quotes_provider_date_unique
        UNIQUE (provider, base_code, quote_code, observed_on)
    );
    CREATE INDEX fx_quotes_as_of_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.fx_quotes`)}
      (base_code, quote_code, observed_on DESC, fetched_at DESC)
      WHERE status = 'available';

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)} (
      id uuid PRIMARY KEY,
      entry_id uuid NOT NULL,
      user_id uuid NOT NULL,
      source_currency char(3) NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      target_currency char(3) NOT NULL
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code)
        ON DELETE RESTRICT,
      source_amount numeric(30, 12) NOT NULL,
      converted_amount numeric(30, 12),
      source_rate numeric(30, 18),
      target_rate numeric(30, 18),
      conversion_rate numeric(30, 18),
      source_quote_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.fx_quotes`)} (id)
        ON DELETE RESTRICT,
      target_quote_id uuid REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.fx_quotes`)} (id)
        ON DELETE RESTRICT,
      provider varchar(32),
      rate_at timestamptz,
      fetched_at timestamptz,
      status varchar(16) NOT NULL,
      precision smallint NOT NULL,
      rounding_mode varchar(16) NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT fx_snapshots_entry_owner_fk
        FOREIGN KEY (entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id)
        ON DELETE RESTRICT,
      CONSTRAINT fx_snapshots_entry_unique UNIQUE (entry_id),
      CONSTRAINT fx_snapshots_amount_check CHECK (source_amount > 0),
      CONSTRAINT fx_snapshots_status_check CHECK (
        status IN ('available', 'stale', 'unavailable')
      ),
      CONSTRAINT fx_snapshots_precision_check CHECK (precision BETWEEN 0 AND 4),
      CONSTRAINT fx_snapshots_rounding_check CHECK (
        rounding_mode IN ('DOWN', 'UP', 'HALF_UP', 'HALF_EVEN')
      ),
      CONSTRAINT fx_snapshots_result_shape_check CHECK (
        (
          status = 'unavailable'
          AND converted_amount IS NULL
          AND source_rate IS NULL
          AND target_rate IS NULL
          AND conversion_rate IS NULL
          AND provider IS NULL
          AND rate_at IS NULL
          AND fetched_at IS NULL
        )
        OR (
          status IN ('available', 'stale')
          AND converted_amount IS NOT NULL
          AND source_rate IS NOT NULL
          AND target_rate IS NOT NULL
          AND conversion_rate IS NOT NULL
          AND provider IS NOT NULL
          AND rate_at IS NOT NULL
          AND fetched_at IS NOT NULL
        )
      )
    );
    CREATE INDEX fx_snapshots_user_entry_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)} (user_id, entry_id);
    CREATE TRIGGER fx_conversion_snapshots_immutable
      BEFORE UPDATE OR DELETE
      ON ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.reject_immutable_journal_change();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS fx_conversion_snapshots_immutable
      ON ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.fx_quotes`)};
    DROP TRIGGER IF EXISTS user_currencies_main_required
      ON ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_user_has_one_main_currency();
    DROP TRIGGER IF EXISTS users_ensure_default_currency
      ON ${sql.table(`${APPLICATION_SCHEMA}.users`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.ensure_default_user_currency();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)};
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)}
      DROP CONSTRAINT IF EXISTS journal_legs_currency_fk;
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.currencies`)};
  `.execute(database);
}
