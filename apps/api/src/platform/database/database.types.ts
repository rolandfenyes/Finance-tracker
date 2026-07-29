import type { ColumnType } from 'kysely';
import type { JsonValue } from '../events/outbox.port';

type DatabaseTimestamp = ColumnType<Date, Date, Date>;
type GeneratedTimestamp = ColumnType<Date, Date | undefined, Date>;
type DatabaseDecimal = ColumnType<string, string, string>;
type DatabaseDate = ColumnType<string | Date, string, string>;

export interface UsersTable {
  id: string;
  email: string;
  password_hash: string;
  full_name: string;
  date_of_birth: string;
  role: 'free' | 'premium' | 'admin';
  status: 'active' | 'inactive';
  email_verified_at: DatabaseTimestamp | null;
  created_at: GeneratedTimestamp;
  updated_at: DatabaseTimestamp;
}

export interface EmailVerificationTokensTable {
  id: string;
  user_id: string;
  token_hash: string;
  expires_at: DatabaseTimestamp;
  consumed_at: DatabaseTimestamp | null;
  created_at: GeneratedTimestamp;
}

export interface PasskeysTable {
  id: string;
  user_id: string;
  credential_id: string;
  public_key: Uint8Array;
  counter: ColumnType<string, string, string>;
  revision: ColumnType<string, string, string>;
  transports: string[];
  device_type: string;
  backed_up: boolean;
  label: string;
  created_at: GeneratedTimestamp;
  last_used_at: DatabaseTimestamp | null;
}

export interface LoginAuditEventsTable {
  id: string;
  user_id: string | null;
  email_hash: string;
  outcome: 'success' | 'failure' | 'throttled';
  method: 'password' | 'passkey';
  ip_hash: string;
  user_agent_hash: string;
  created_at: GeneratedTimestamp;
}

export interface IdempotencyKeysTable {
  scope_id: string;
  operation: string;
  key_hash: string;
  request_hash: string;
  status: 'completed' | 'in_progress';
  response: Readonly<Record<string, JsonValue>> | null;
  created_at: DatabaseTimestamp;
  completed_at: DatabaseTimestamp | null;
}

export interface LedgerAccountsTable {
  id: string;
  user_id: string;
  kind:
    | 'cash'
    | 'goal'
    | 'emergency_reserve'
    | 'investment'
    | 'loan_liability'
    | 'securities_cash'
    | 'securities_holding';
  module_reference_id: string | null;
  created_at: DatabaseTimestamp;
}

export interface JournalEntriesTable {
  id: string;
  user_id: string;
  economic_type:
    | 'external_income'
    | 'external_expense'
    | 'internal_transfer'
    | 'adjustment'
    | 'fee'
    | 'interest'
    | 'dividend'
    | 'loan_repayment'
    | 'trade_cash';
  category_id: string | null;
  note: string | null;
  source_module:
    | 'manual'
    | 'scheduling'
    | 'goals'
    | 'emergency_fund'
    | 'loans'
    | 'investments'
    | 'securities'
    | 'migration';
  source_reference_id: string | null;
  idempotency_key_hash: string;
  posted_on: DatabaseDate;
  effective_at: DatabaseTimestamp;
  created_at: DatabaseTimestamp;
  actor_user_id: string;
  reverses_entry_id: string | null;
  replaces_entry_id: string | null;
}

export interface JournalLegsTable {
  id: string;
  entry_id: string;
  user_id: string;
  account_id: string | null;
  side: 'debit' | 'credit';
  amount: DatabaseDecimal;
  currency: string;
  created_at: DatabaseTimestamp;
}

export interface DatabaseSchema {
  'mymoneymap.idempotency_keys': IdempotencyKeysTable;
  'mymoneymap.users': UsersTable;
  'mymoneymap.email_verification_tokens': EmailVerificationTokensTable;
  'mymoneymap.passkeys': PasskeysTable;
  'mymoneymap.login_audit_events': LoginAuditEventsTable;
  'mymoneymap.ledger_accounts': LedgerAccountsTable;
  'mymoneymap.journal_entries': JournalEntriesTable;
  'mymoneymap.journal_legs': JournalLegsTable;
}
