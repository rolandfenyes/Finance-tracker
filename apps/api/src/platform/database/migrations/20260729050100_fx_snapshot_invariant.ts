import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    INSERT INTO ${sql.table(`${APPLICATION_SCHEMA}.fx_conversion_snapshots`)}
      (
        id, entry_id, user_id, source_currency, target_currency, source_amount,
        converted_amount, source_rate, target_rate, conversion_rate,
        provider, rate_at, fetched_at, status, precision, rounding_mode, created_at
      )
    SELECT
      gen_random_uuid(),
      entry.id,
      entry.user_id,
      native.currency,
      main.code,
      native.amount,
      CASE WHEN native.currency = main.code THEN native.amount ELSE NULL END,
      CASE WHEN native.currency = main.code THEN 1 ELSE NULL END,
      CASE WHEN native.currency = main.code THEN 1 ELSE NULL END,
      CASE WHEN native.currency = main.code THEN 1 ELSE NULL END,
      CASE WHEN native.currency = main.code THEN 'identity' ELSE NULL END,
      CASE
        WHEN native.currency = main.code
        THEN entry.posted_on::timestamp AT TIME ZONE 'UTC'
        ELSE NULL
      END,
      CASE WHEN native.currency = main.code THEN entry.created_at ELSE NULL END,
      CASE WHEN native.currency = main.code THEN 'available' ELSE 'unavailable' END,
      currency.minor_unit,
      currency.rounding_mode,
      entry.created_at
    FROM ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} entry
    JOIN LATERAL (
      SELECT amount, currency
        FROM ${sql.table(`${APPLICATION_SCHEMA}.journal_legs`)} leg
       WHERE leg.entry_id = entry.id
       ORDER BY leg.id
       LIMIT 1
    ) native ON true
    JOIN ${sql.table(`${APPLICATION_SCHEMA}.user_currencies`)} main
      ON main.user_id = entry.user_id
     AND main.is_main
    JOIN ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} currency
      ON currency.code = main.code
    ON CONFLICT (entry_id) DO NOTHING;

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_journal_fx_snapshot()
    RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
      IF (
        SELECT count(*)
          FROM mymoneymap.fx_conversion_snapshots snapshot
         WHERE snapshot.entry_id = NEW.id
           AND snapshot.user_id = NEW.user_id
      ) <> 1 THEN
        RAISE EXCEPTION 'posted journal entry requires one FX conversion snapshot'
          USING ERRCODE = '23514';
      END IF;
      RETURN NULL;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER journal_entries_fx_snapshot_required
      AFTER INSERT ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)}
      DEFERRABLE INITIALLY DEFERRED
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_journal_fx_snapshot();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS journal_entries_fx_snapshot_required
      ON ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_journal_fx_snapshot();
  `.execute(database);
}
