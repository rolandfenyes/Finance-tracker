export const LEGACY_TRANSFORMER_VERSION = 'legacy-postgresql-v1';

export type LegacyDomain =
  | 'identity'
  | 'settings'
  | 'currency'
  | 'ledger'
  | 'budgeting'
  | 'recurrence'
  | 'goals'
  | 'emergency_reserve'
  | 'loans'
  | 'investments'
  | 'securities'
  | 'feedback'
  | 'billing'
  | 'notifications'
  | 'administration'
  | 'removed';

export type LegacyDisposition =
  | 'map'
  | 'derive'
  | 'deduplicate'
  | 'quarantine'
  | 'discard_security_secret'
  | 'discard_derived'
  | 'discard_unsupported';

export interface LegacyRelationMapping {
  sourceTable: string;
  domain: LegacyDomain;
  disposition: LegacyDisposition;
  targetTables: readonly string[];
  requiredColumns: readonly string[];
  optionalColumns?: readonly string[];
  amountColumn?: string;
  currencyColumn?: string;
  ownerColumn?: string;
  rationaleCode: string;
}

export interface LegacySchemaColumn {
  table: string;
  column: string;
  dataType: string;
  nullable: boolean;
}

export interface LegacySchemaSnapshot {
  version: 'recorded-035' | 'configured-drift' | 'recorded-036' | 'unsupported';
  appliedMigrations: readonly string[];
  columns: readonly LegacySchemaColumn[];
  fingerprint: string;
  driftCodes: readonly string[];
  blockingCodes: readonly string[];
}

export type LegacyRow = Readonly<Record<string, unknown>>;

export interface LegacySourceSnapshot {
  schema: LegacySchemaSnapshot;
  rows: Readonly<Record<string, readonly LegacyRow[]>>;
  rowCount: number;
  dataFingerprint: string;
}

export type QuarantineReason =
  | 'AMBIGUOUS_DUPLICATE_MOVEMENT'
  | 'DEFAULT_OR_UNAPPROVED_ADMIN'
  | 'HARDCODED_OR_EMBEDDED_SECRET'
  | 'INVALID_DATE'
  | 'INVALID_DECIMAL'
  | 'INVALID_ENUM'
  | 'INVALID_RELATION'
  | 'INVALID_CURRENCY'
  | 'ORPHAN_OWNER'
  | 'SCHEMA_DRIFT'
  | 'UNRECONCILED_DERIVED_BALANCE'
  | 'UNTRACKED_INVESTMENT_SECURITIES_LINK'
  | 'UNSUPPORTED_LEGACY_CHANNEL'
  | 'UNSUPPORTED_LEGACY_ROLE';

export interface PlannedTargetRow {
  sourceTable: string;
  sourceKeyHash: string;
  domain: LegacyDomain;
  targetTable: string;
  targetId: string;
  values: Readonly<Record<string, unknown>>;
  reconciliation?: {
    userKeyHash: string;
    currency: string;
    amount: string;
  };
}

export interface QuarantinedLegacyRow {
  sourceTable: string;
  sourceKeyHash: string;
  userKeyHash: string | null;
  domain: LegacyDomain;
  reasonCode: QuarantineReason;
  detailCodes: readonly string[];
  reconciliation?: {
    currency: string;
    amount: string;
  };
}

export interface SkippedLegacyRow {
  sourceTable: string;
  sourceKeyHash: string;
  domain: LegacyDomain;
  reasonCode: string;
}

export interface LegacyReconciliation {
  userKeyHash: string;
  domain: LegacyDomain;
  currency: string;
  sourceCount: number;
  plannedCount: number;
  quarantineCount: number;
  sourceAmount: string;
  plannedAmount: string;
  difference: string;
  status: 'exact' | 'explained' | 'blocked';
  explanationCodes: readonly string[];
}

export interface LegacyMigrationPlan {
  transformerVersion: string;
  sourceSchemaVersion: LegacySchemaSnapshot['version'];
  sourceSchemaFingerprint: string;
  sourceDataFingerprint: string;
  sourceRowCount: number;
  planned: readonly PlannedTargetRow[];
  quarantined: readonly QuarantinedLegacyRow[];
  skipped: readonly SkippedLegacyRow[];
  reconciliation: readonly LegacyReconciliation[];
  blockingCodes: readonly string[];
}
