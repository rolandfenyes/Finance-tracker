import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE OR REPLACE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_accounts()
    RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN
      IF TG_TABLE_NAME = 'securities_portfolios' THEN
        IF NOT EXISTS (
          SELECT 1 FROM mymoneymap.ledger_accounts
           WHERE id = NEW.cash_account_id AND user_id = NEW.user_id
             AND kind = 'securities_cash' AND module_reference_id = NEW.id
        ) THEN
          RAISE EXCEPTION 'securities portfolio requires its owned cash account' USING ERRCODE = '23514';
        END IF;
      ELSIF TG_TABLE_NAME = 'securities_positions' THEN
        IF NOT EXISTS (
          SELECT 1 FROM mymoneymap.ledger_accounts
           WHERE id = NEW.holding_account_id AND user_id = NEW.user_id
             AND kind = 'securities_holding' AND module_reference_id = NEW.id
        ) THEN
          RAISE EXCEPTION 'securities position requires its owned holding account' USING ERRCODE = '23514';
        END IF;
      END IF;
      RETURN NEW;
    END;
    $$;

  `.execute(database);
  await applySecuritiesLedgerGuard(database);
}

export async function applySecuritiesLedgerGuard(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE OR REPLACE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_balanced_journal_entry()
    RETURNS trigger LANGUAGE plpgsql AS $$
    DECLARE
      target_entry uuid; target_type varchar(32); reversed_entry uuid;
      leg_count integer; owned_count integer; owned_debit_count integer;
      debit_count integer; credit_count integer; currency_count integer;
      imbalance numeric(30, 12); distinct_owned_accounts integer;
    BEGIN
      IF TG_TABLE_NAME = 'journal_entries' THEN target_entry := NEW.id;
      ELSE target_entry := NEW.entry_id;
      END IF;
      SELECT economic_type, reverses_entry_id INTO target_type, reversed_entry
        FROM mymoneymap.journal_entries WHERE id = target_entry;
      SELECT count(*)::integer, count(account_id)::integer,
        count(*) FILTER (WHERE account_id IS NOT NULL AND side = 'debit')::integer,
        count(*) FILTER (WHERE side = 'debit')::integer,
        count(*) FILTER (WHERE side = 'credit')::integer,
        count(DISTINCT currency)::integer,
        COALESCE(sum(CASE WHEN side = 'debit' THEN amount ELSE -amount END), 0),
        count(DISTINCT account_id)::integer
      INTO leg_count, owned_count, owned_debit_count, debit_count, credit_count,
        currency_count, imbalance, distinct_owned_accounts
      FROM mymoneymap.journal_legs WHERE entry_id = target_entry;
      IF leg_count <> 2 OR debit_count <> 1 OR credit_count <> 1
         OR currency_count <> 1 OR imbalance <> 0 THEN
        RAISE EXCEPTION 'journal entry must contain one balanced debit and credit in one currency'
          USING ERRCODE = '23514';
      END IF;
      IF target_type IN ('internal_transfer', 'loan_repayment', 'trade_cash') THEN
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
        IF target_type IN ('external_expense', 'fee') AND owned_debit_count <> 0 THEN
          RAISE EXCEPTION 'expense-like entries must decrease the owned account'
            USING ERRCODE = '23514';
        END IF;
      ELSE
        IF target_type <> (
          SELECT economic_type FROM mymoneymap.journal_entries WHERE id = reversed_entry
        ) OR EXISTS (
          SELECT account_id, side, amount, currency FROM mymoneymap.journal_legs
           WHERE entry_id = target_entry
          EXCEPT
          SELECT account_id, CASE WHEN side = 'debit' THEN 'credit' ELSE 'debit' END,
                 amount, currency
            FROM mymoneymap.journal_legs WHERE entry_id = reversed_entry
        ) OR EXISTS (
          SELECT account_id, CASE WHEN side = 'debit' THEN 'credit' ELSE 'debit' END,
                 amount, currency
            FROM mymoneymap.journal_legs WHERE entry_id = reversed_entry
          EXCEPT
          SELECT account_id, side, amount, currency FROM mymoneymap.journal_legs
           WHERE entry_id = target_entry
        ) THEN
          RAISE EXCEPTION 'reversal legs must exactly invert the original entry'
            USING ERRCODE = '23514';
        END IF;
      END IF;
      RETURN NULL;
    END;
    $$;
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  // The revision only makes the Step 15 trigger valid for both record shapes.
  // The complete securities down migration owns function removal.
  await sql`SELECT 1`.execute(database);
}
