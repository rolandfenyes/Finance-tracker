import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_portfolios`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL UNIQUE REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      cash_account_id uuid NOT NULL UNIQUE,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_portfolios_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT securities_portfolios_cash_owner_fk FOREIGN KEY (cash_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id) ON DELETE RESTRICT
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (
      id uuid PRIMARY KEY,
      symbol varchar(32) NOT NULL,
      market varchar(48) NOT NULL,
      exchange varchar(120),
      name varchar(240),
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      sector varchar(180),
      industry varchar(180),
      beta numeric(30, 12),
      metadata_provider varchar(48),
      metadata_observed_at timestamptz,
      active boolean NOT NULL DEFAULT true,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT securities_instruments_symbol_check CHECK (symbol = upper(btrim(symbol)) AND char_length(symbol) BETWEEN 1 AND 32),
      CONSTRAINT securities_instruments_market_check CHECK (market = upper(btrim(market)) AND char_length(market) BETWEEN 1 AND 48),
      CONSTRAINT securities_instruments_exchange_check CHECK (exchange IS NULL OR char_length(btrim(exchange)) BETWEEN 1 AND 120),
      CONSTRAINT securities_instruments_name_check CHECK (name IS NULL OR char_length(btrim(name)) BETWEEN 1 AND 240),
      CONSTRAINT securities_instruments_beta_check CHECK (beta IS NULL OR beta >= 0),
      CONSTRAINT securities_instruments_identity_unique UNIQUE (symbol, market)
    );
    CREATE INDEX securities_instruments_active_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (active, symbol, market);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      instrument_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (id) ON DELETE RESTRICT,
      holding_account_id uuid NOT NULL UNIQUE,
      quantity numeric(36, 18) NOT NULL DEFAULT 0,
      remaining_cost_local numeric(36, 12) NOT NULL DEFAULT 0,
      remaining_cost_base numeric(36, 12) NOT NULL DEFAULT 0,
      local_currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      base_currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT securities_positions_nonnegative_check CHECK (
        quantity >= 0 AND remaining_cost_local >= 0 AND remaining_cost_base >= 0
      ),
      CONSTRAINT securities_positions_id_user_instrument_unique UNIQUE (id, user_id, instrument_id),
      CONSTRAINT securities_positions_user_instrument_unique UNIQUE (user_id, instrument_id),
      CONSTRAINT securities_positions_holding_owner_fk FOREIGN KEY (holding_account_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.ledger_accounts`)} (id, user_id) ON DELETE RESTRICT
    );
    CREATE INDEX securities_positions_user_quantity_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)} (user_id, instrument_id)
      WHERE quantity > 0;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      position_id uuid NOT NULL,
      instrument_id uuid NOT NULL,
      side varchar(4) NOT NULL,
      quantity numeric(36, 18) NOT NULL,
      unit_price numeric(36, 12) NOT NULL,
      fee numeric(36, 12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      notional numeric(36, 12) NOT NULL,
      notional_base numeric(36, 12) NOT NULL,
      fee_base numeric(36, 12) NOT NULL,
      base_currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      conversion_status varchar(16) NOT NULL,
      conversion_rate numeric(36, 18) NOT NULL,
      conversion_provider varchar(64) NOT NULL,
      rate_at timestamptz NOT NULL,
      fetched_at timestamptz NOT NULL,
      executed_at timestamptz NOT NULL,
      traded_on date NOT NULL,
      note varchar(1000),
      cash_journal_entry_id uuid NOT NULL,
      fee_journal_entry_id uuid,
      reversed_by_cash_journal_entry_id uuid,
      reversed_by_fee_journal_entry_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_trades_side_check CHECK (side IN ('buy', 'sell')),
      CONSTRAINT securities_trades_values_check CHECK (
        quantity > 0 AND unit_price > 0 AND fee >= 0
        AND notional = quantity * unit_price
        AND notional_base > 0 AND fee_base >= 0 AND conversion_rate > 0
      ),
      CONSTRAINT securities_trades_conversion_check CHECK (conversion_status IN ('available', 'stale')),
      CONSTRAINT securities_trades_note_check CHECK (note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000),
      CONSTRAINT securities_trades_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT securities_trades_position_owner_fk FOREIGN KEY (position_id, user_id, instrument_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)} (id, user_id, instrument_id) ON DELETE RESTRICT,
      CONSTRAINT securities_trades_cash_journal_owner_fk FOREIGN KEY (cash_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_trades_fee_journal_owner_fk FOREIGN KEY (fee_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_trades_cash_reversal_owner_fk FOREIGN KEY (reversed_by_cash_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_trades_fee_reversal_owner_fk FOREIGN KEY (reversed_by_fee_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_trades_cash_journal_unique UNIQUE (cash_journal_entry_id),
      CONSTRAINT securities_trades_fee_journal_unique UNIQUE (fee_journal_entry_id),
      CONSTRAINT securities_trades_cash_reversal_unique UNIQUE (reversed_by_cash_journal_entry_id),
      CONSTRAINT securities_trades_fee_reversal_unique UNIQUE (reversed_by_fee_journal_entry_id),
      CONSTRAINT securities_trades_fee_pair_check CHECK (
        (fee = 0 AND fee_journal_entry_id IS NULL AND fee_base = 0)
        OR (fee > 0 AND fee_journal_entry_id IS NOT NULL AND fee_base > 0)
      ),
      CONSTRAINT securities_trades_reversal_pair_check CHECK (
        (reversed_by_cash_journal_entry_id IS NULL AND reversed_by_fee_journal_entry_id IS NULL)
        OR (
          reversed_by_cash_journal_entry_id IS NOT NULL
          AND (fee_journal_entry_id IS NULL OR reversed_by_fee_journal_entry_id IS NOT NULL)
        )
      )
    );
    CREATE INDEX securities_trades_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)}
      (user_id, executed_at DESC, id DESC);
    CREATE INDEX securities_trades_rebuild_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)}
      (user_id, instrument_id, executed_at, id)
      WHERE reversed_by_cash_journal_entry_id IS NULL;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_lots`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      position_id uuid NOT NULL,
      instrument_id uuid NOT NULL,
      buy_trade_id uuid NOT NULL,
      original_quantity numeric(36, 18) NOT NULL,
      remaining_quantity numeric(36, 18) NOT NULL,
      total_cost_local numeric(36, 12) NOT NULL,
      total_cost_base numeric(36, 12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      base_currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      opened_at timestamptz NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_lots_values_check CHECK (
        original_quantity > 0 AND remaining_quantity >= 0
        AND remaining_quantity <= original_quantity
        AND total_cost_local > 0 AND total_cost_base > 0
      ),
      CONSTRAINT securities_lots_position_owner_fk FOREIGN KEY (position_id, user_id, instrument_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)} (id, user_id, instrument_id) ON DELETE CASCADE,
      CONSTRAINT securities_lots_trade_owner_fk FOREIGN KEY (buy_trade_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_lots_buy_trade_unique UNIQUE (buy_trade_id)
    );
    CREATE INDEX securities_lots_fifo_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_lots`)}
      (user_id, position_id, opened_at, id)
      WHERE remaining_quantity > 0;

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_lot_consumptions`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      sell_trade_id uuid NOT NULL,
      lot_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_lots`)} (id) ON DELETE CASCADE,
      quantity numeric(36, 18) NOT NULL,
      cost_local numeric(36, 12) NOT NULL,
      cost_base numeric(36, 12) NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_lot_consumptions_values_check CHECK (
        quantity > 0 AND cost_local > 0 AND cost_base > 0
      ),
      CONSTRAINT securities_lot_consumptions_trade_owner_fk FOREIGN KEY (sell_trade_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_lot_consumptions_unique UNIQUE (sell_trade_id, lot_id)
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_realized_results`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      instrument_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (id) ON DELETE RESTRICT,
      sell_trade_id uuid NOT NULL,
      quantity numeric(36, 18) NOT NULL,
      proceeds_local numeric(36, 12) NOT NULL,
      cost_local numeric(36, 12) NOT NULL,
      fees_local numeric(36, 12) NOT NULL,
      realized_local numeric(36, 12) NOT NULL,
      proceeds_base numeric(36, 12) NOT NULL,
      cost_base numeric(36, 12) NOT NULL,
      fees_base numeric(36, 12) NOT NULL,
      realized_base numeric(36, 12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      base_currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      method varchar(8) NOT NULL,
      closed_at timestamptz NOT NULL,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_realized_values_check CHECK (
        quantity > 0 AND proceeds_local > 0 AND cost_local > 0
        AND fees_local >= 0 AND proceeds_base > 0 AND cost_base > 0 AND fees_base >= 0
      ),
      CONSTRAINT securities_realized_method_check CHECK (method = 'FIFO'),
      CONSTRAINT securities_realized_trade_owner_fk FOREIGN KEY (sell_trade_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_realized_trade_unique UNIQUE (sell_trade_id)
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL,
      direction varchar(16) NOT NULL,
      amount numeric(36, 12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      occurred_on date NOT NULL,
      note varchar(1000),
      journal_entry_id uuid NOT NULL,
      reversed_by_journal_entry_id uuid,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_cash_direction_check CHECK (direction IN ('deposit', 'withdrawal')),
      CONSTRAINT securities_cash_amount_check CHECK (amount > 0),
      CONSTRAINT securities_cash_note_check CHECK (note IS NULL OR char_length(btrim(note)) BETWEEN 1 AND 1000),
      CONSTRAINT securities_cash_id_user_unique UNIQUE (id, user_id),
      CONSTRAINT securities_cash_journal_owner_fk FOREIGN KEY (journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_cash_reversal_owner_fk FOREIGN KEY (reversed_by_journal_entry_id, user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.journal_entries`)} (id, user_id) ON DELETE RESTRICT,
      CONSTRAINT securities_cash_journal_unique UNIQUE (journal_entry_id),
      CONSTRAINT securities_cash_reversal_unique UNIQUE (reversed_by_journal_entry_id)
    );
    CREATE INDEX securities_cash_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)}
      (user_id, occurred_on DESC, created_at DESC, id DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_quotes`)} (
      instrument_id uuid PRIMARY KEY REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (id) ON DELETE RESTRICT,
      last numeric(36, 12),
      previous_close numeric(36, 12),
      day_high numeric(36, 12),
      day_low numeric(36, 12),
      volume numeric(36, 6),
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      provider varchar(48) NOT NULL,
      quote_at timestamptz,
      retrieved_at timestamptz NOT NULL,
      status varchar(16) NOT NULL,
      CONSTRAINT securities_quotes_status_check CHECK (status IN ('available', 'delayed', 'stale', 'unavailable')),
      CONSTRAINT securities_quotes_values_check CHECK (
        (status = 'unavailable' AND last IS NULL)
        OR (status <> 'unavailable' AND last IS NOT NULL AND last > 0 AND quote_at IS NOT NULL)
      ),
      CONSTRAINT securities_quotes_nonnegative_check CHECK (
        previous_close IS NULL OR previous_close >= 0
      )
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_daily_prices`)} (
      id uuid PRIMARY KEY,
      instrument_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (id) ON DELETE RESTRICT,
      trading_on date NOT NULL,
      open numeric(36, 12),
      high numeric(36, 12),
      low numeric(36, 12),
      close numeric(36, 12) NOT NULL,
      volume numeric(36, 6),
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code) ON DELETE RESTRICT,
      provider varchar(48) NOT NULL,
      observed_at timestamptz NOT NULL,
      retrieved_at timestamptz NOT NULL,
      CONSTRAINT securities_daily_prices_values_check CHECK (
        close > 0 AND (open IS NULL OR open > 0) AND (high IS NULL OR high > 0)
        AND (low IS NULL OR low > 0) AND (volume IS NULL OR volume >= 0)
      ),
      CONSTRAINT securities_daily_prices_unique UNIQUE (instrument_id, trading_on, provider)
    );
    CREATE INDEX securities_daily_prices_history_index
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_daily_prices`)}
      (instrument_id, trading_on DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_watchlist`)} (
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      instrument_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)} (id) ON DELETE RESTRICT,
      created_at timestamptz NOT NULL,
      PRIMARY KEY (user_id, instrument_id)
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_imports`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      fingerprint char(64) NOT NULL,
      status varchar(16) NOT NULL,
      row_count integer NOT NULL,
      valid_count integer NOT NULL,
      error_count integer NOT NULL,
      ignored_count integer NOT NULL,
      rows jsonb NOT NULL,
      committed_at timestamptz,
      created_at timestamptz NOT NULL,
      CONSTRAINT securities_imports_status_check CHECK (status IN ('preview', 'committed')),
      CONSTRAINT securities_imports_counts_check CHECK (
        row_count >= 0 AND valid_count >= 0 AND error_count >= 0 AND ignored_count >= 0
        AND row_count = valid_count + error_count + ignored_count
      ),
      CONSTRAINT securities_imports_commit_check CHECK (
        (status = 'preview' AND committed_at IS NULL)
        OR (status = 'committed' AND committed_at IS NOT NULL AND error_count = 0)
      ),
      CONSTRAINT securities_imports_user_fingerprint_unique UNIQUE (user_id, fingerprint),
      CONSTRAINT securities_imports_id_user_unique UNIQUE (id, user_id)
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_refresh_jobs`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      queue_job_id varchar(160) NOT NULL UNIQUE,
      status varchar(24) NOT NULL,
      attempt_count integer NOT NULL DEFAULT 0,
      max_attempts integer NOT NULL,
      error_code varchar(80),
      created_at timestamptz NOT NULL,
      started_at timestamptz,
      finished_at timestamptz,
      CONSTRAINT securities_refresh_status_check CHECK (
        status IN ('queued', 'running', 'completed', 'retryable_failed', 'dead_letter')
      ),
      CONSTRAINT securities_refresh_attempt_check CHECK (
        attempt_count >= 0 AND max_attempts BETWEEN 1 AND 10 AND attempt_count <= max_attempts
      )
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.securities_clear_requests`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      status varchar(16) NOT NULL,
      trade_count integer NOT NULL,
      cash_count integer NOT NULL,
      created_at timestamptz NOT NULL,
      completed_at timestamptz NOT NULL,
      CONSTRAINT securities_clear_status_check CHECK (status = 'completed'),
      CONSTRAINT securities_clear_counts_check CHECK (trade_count >= 0 AND cash_count >= 0)
    );

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_accounts()
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
    CREATE CONSTRAINT TRIGGER securities_portfolio_account_guard
      AFTER INSERT OR UPDATE OF user_id, cash_account_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_portfolios`)}
      DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
      EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_accounts();
    CREATE CONSTRAINT TRIGGER securities_position_account_guard
      AFTER INSERT OR UPDATE OF user_id, holding_account_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)}
      DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
      EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_accounts();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_journals()
    RETURNS trigger LANGUAGE plpgsql AS $$
    DECLARE cash_account uuid; holding_account uuid;
    BEGIN
      SELECT p.cash_account_id, sp.holding_account_id INTO cash_account, holding_account
        FROM mymoneymap.securities_portfolios p
        JOIN mymoneymap.securities_positions sp ON sp.user_id = p.user_id
       WHERE p.user_id = NEW.user_id AND sp.id = NEW.position_id;
      IF NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries e
         WHERE e.id = NEW.cash_journal_entry_id AND e.user_id = NEW.user_id
           AND e.economic_type = 'trade_cash' AND e.source_module = 'securities'
           AND e.source_reference_id = NEW.id AND e.reverses_entry_id IS NULL
      ) OR NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_legs l
         WHERE l.entry_id = NEW.cash_journal_entry_id AND l.user_id = NEW.user_id
           AND l.account_id = cash_account
           AND l.side = CASE WHEN NEW.side = 'buy' THEN 'credit' ELSE 'debit' END
           AND l.amount = NEW.notional AND l.currency = NEW.currency
      ) OR NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_legs l
         WHERE l.entry_id = NEW.cash_journal_entry_id AND l.user_id = NEW.user_id
           AND l.account_id = holding_account
           AND l.side = CASE WHEN NEW.side = 'buy' THEN 'debit' ELSE 'credit' END
           AND l.amount = NEW.notional AND l.currency = NEW.currency
      ) THEN
        RAISE EXCEPTION 'trade must reference its balanced securities journal' USING ERRCODE = '23514';
      END IF;
      IF NEW.fee_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries e
        JOIN mymoneymap.journal_legs l ON l.entry_id = e.id AND l.user_id = e.user_id
         WHERE e.id = NEW.fee_journal_entry_id AND e.user_id = NEW.user_id
           AND e.economic_type = 'fee' AND e.source_module = 'securities'
           AND e.source_reference_id = NEW.id AND e.reverses_entry_id IS NULL
           AND l.account_id = cash_account AND l.side = 'credit'
           AND l.amount = NEW.fee AND l.currency = NEW.currency
      ) THEN
        RAISE EXCEPTION 'trade fee must reference its cash fee journal' USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_cash_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_cash_journal_entry_id AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.cash_journal_entry_id
      ) THEN
        RAISE EXCEPTION 'trade cash reversal must invert the original journal' USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_fee_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_fee_journal_entry_id AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.fee_journal_entry_id
      ) THEN
        RAISE EXCEPTION 'trade fee reversal must invert the original fee journal' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER securities_trade_journal_guard
      AFTER INSERT OR UPDATE OF cash_journal_entry_id, fee_journal_entry_id,
        reversed_by_cash_journal_entry_id, reversed_by_fee_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)}
      DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
      EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_journals();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_immutable()
    RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN
      IF TG_OP = 'DELETE' OR ROW(
        OLD.id, OLD.user_id, OLD.position_id, OLD.instrument_id, OLD.side, OLD.quantity,
        OLD.unit_price, OLD.fee, OLD.currency, OLD.notional, OLD.notional_base, OLD.fee_base,
        OLD.base_currency, OLD.conversion_status, OLD.conversion_rate, OLD.conversion_provider,
        OLD.rate_at, OLD.fetched_at, OLD.executed_at, OLD.traded_on, OLD.note,
        OLD.cash_journal_entry_id, OLD.fee_journal_entry_id, OLD.created_at
      ) IS DISTINCT FROM ROW(
        NEW.id, NEW.user_id, NEW.position_id, NEW.instrument_id, NEW.side, NEW.quantity,
        NEW.unit_price, NEW.fee, NEW.currency, NEW.notional, NEW.notional_base, NEW.fee_base,
        NEW.base_currency, NEW.conversion_status, NEW.conversion_rate, NEW.conversion_provider,
        NEW.rate_at, NEW.fetched_at, NEW.executed_at, NEW.traded_on, NEW.note,
        NEW.cash_journal_entry_id, NEW.fee_journal_entry_id, NEW.created_at
      ) OR OLD.reversed_by_cash_journal_entry_id IS NOT NULL
        OR (NEW.reversed_by_cash_journal_entry_id IS NULL
          AND NEW.reversed_by_fee_journal_entry_id IS NULL) THEN
        RAISE EXCEPTION 'securities trade history is immutable' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER securities_trade_immutable_guard
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_immutable();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_journal()
    RETURNS trigger LANGUAGE plpgsql AS $$
    DECLARE cash_account uuid; expected_side varchar(8);
    BEGIN
      SELECT cash_account_id INTO cash_account FROM mymoneymap.securities_portfolios
       WHERE user_id = NEW.user_id;
      expected_side := CASE WHEN NEW.direction = 'deposit' THEN 'debit' ELSE 'credit' END;
      IF NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries e
        JOIN mymoneymap.journal_legs l ON l.entry_id = e.id AND l.user_id = e.user_id
         WHERE e.id = NEW.journal_entry_id AND e.user_id = NEW.user_id
           AND e.economic_type = 'internal_transfer' AND e.source_module = 'securities'
           AND e.source_reference_id = NEW.id AND e.reverses_entry_id IS NULL
           AND l.account_id = cash_account AND l.side = expected_side
           AND l.amount = NEW.amount AND l.currency = NEW.currency
      ) THEN
        RAISE EXCEPTION 'securities cash movement must reference its transfer journal' USING ERRCODE = '23514';
      END IF;
      IF NEW.reversed_by_journal_entry_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM mymoneymap.journal_entries
         WHERE id = NEW.reversed_by_journal_entry_id AND user_id = NEW.user_id
           AND reverses_entry_id = NEW.journal_entry_id
      ) THEN
        RAISE EXCEPTION 'securities cash reversal must invert its journal' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE CONSTRAINT TRIGGER securities_cash_journal_guard
      AFTER INSERT OR UPDATE OF journal_entry_id, reversed_by_journal_entry_id
      ON ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)}
      DEFERRABLE INITIALLY DEFERRED FOR EACH ROW
      EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_journal();

    CREATE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_immutable()
    RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN
      IF TG_OP = 'DELETE' OR ROW(
        OLD.id, OLD.user_id, OLD.direction, OLD.amount, OLD.currency,
        OLD.occurred_on, OLD.note, OLD.journal_entry_id, OLD.created_at
      ) IS DISTINCT FROM ROW(
        NEW.id, NEW.user_id, NEW.direction, NEW.amount, NEW.currency,
        NEW.occurred_on, NEW.note, NEW.journal_entry_id, NEW.created_at
      ) OR OLD.reversed_by_journal_entry_id IS NOT NULL
        OR NEW.reversed_by_journal_entry_id IS NULL THEN
        RAISE EXCEPTION 'securities cash history is immutable' USING ERRCODE = '23514';
      END IF;
      RETURN NEW;
    END;
    $$;
    CREATE TRIGGER securities_cash_immutable_guard
      BEFORE UPDATE OR DELETE ON ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)}
      FOR EACH ROW EXECUTE FUNCTION ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_immutable();
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    DROP TRIGGER IF EXISTS securities_cash_immutable_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_immutable();
    DROP TRIGGER IF EXISTS securities_cash_journal_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_cash_journal();
    DROP TRIGGER IF EXISTS securities_trade_immutable_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_immutable();
    DROP TRIGGER IF EXISTS securities_trade_journal_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_trade_journals();
    DROP TRIGGER IF EXISTS securities_position_account_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)};
    DROP TRIGGER IF EXISTS securities_portfolio_account_guard ON ${sql.table(`${APPLICATION_SCHEMA}.securities_portfolios`)};
    DROP FUNCTION IF EXISTS ${sql.raw(APPLICATION_SCHEMA)}.assert_securities_accounts();
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_clear_requests`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_refresh_jobs`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_imports`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_watchlist`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_daily_prices`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_quotes`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_cash_movements`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_realized_results`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_lot_consumptions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_lots`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_trades`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_positions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_instruments`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.securities_portfolios`)};
  `.execute(database);
}
