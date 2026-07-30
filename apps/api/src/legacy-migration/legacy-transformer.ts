import { createHash } from 'node:crypto';
import { createPublicKey } from 'node:crypto';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { CurrencyCode } from '../platform/decimal/currency-code';
import { LEGACY_MAPPING_BY_TABLE, LEGACY_RELATION_MAPPINGS } from './legacy-schema.manifest';
import {
  LEGACY_TRANSFORMER_VERSION,
  type LegacyDomain,
  type LegacyMigrationPlan,
  type LegacyReconciliation,
  type LegacyRow,
  type LegacySourceSnapshot,
  type PlannedTargetRow,
  type QuarantinedLegacyRow,
  type QuarantineReason,
  type SkippedLegacyRow,
} from './legacy-migration.types';
import { canonicalJson, sourceKeyHash } from './legacy-source-extractor';

interface TransformationContext {
  acceptedUserIds: ReadonlySet<string>;
  source: LegacySourceSnapshot;
  planned: PlannedTargetRow[];
  quarantined: QuarantinedLegacyRow[];
  skipped: SkippedLegacyRow[];
}

export class LegacyTransformer {
  transform(source: LegacySourceSnapshot): LegacyMigrationPlan {
    if (source.schema.blockingCodes.length > 0) {
      return emptyBlockedPlan(source);
    }

    const planned: PlannedTargetRow[] = [];
    const quarantined: QuarantinedLegacyRow[] = [];
    const skipped: SkippedLegacyRow[] = [];
    const acceptedUserIds = this.transformUsers(source, planned, quarantined);
    const context: TransformationContext = {
      acceptedUserIds,
      source,
      planned,
      quarantined,
      skipped,
    };

    for (const mapping of LEGACY_RELATION_MAPPINGS) {
      if (mapping.sourceTable === 'users') {
        continue;
      }
      for (const row of source.rows[mapping.sourceTable] ?? []) {
        this.transformRow(mapping.sourceTable, row, context);
      }
    }
    this.checkDerivedBalances(context);

    const reconciliation = reconcile(source, planned, quarantined, skipped, acceptedUserIds);
    const blockingCodes = [
      ...source.schema.blockingCodes,
      ...reconciliation
        .filter(({ status }) => status === 'blocked')
        .map(({ domain, currency }) => `RECONCILIATION_${domain.toUpperCase()}_${currency}`),
    ];

    return {
      transformerVersion: LEGACY_TRANSFORMER_VERSION,
      sourceSchemaVersion: source.schema.version,
      sourceSchemaFingerprint: source.schema.fingerprint,
      sourceDataFingerprint: source.dataFingerprint,
      sourceRowCount: source.rowCount,
      planned: sortPlanned(planned),
      quarantined: sortQuarantine(quarantined),
      skipped: sortSkipped(skipped),
      reconciliation,
      blockingCodes: [...new Set(blockingCodes)].sort(),
    };
  }

  private transformUsers(
    source: LegacySourceSnapshot,
    planned: PlannedTargetRow[],
    quarantined: QuarantinedLegacyRow[],
  ): ReadonlySet<string> {
    const accepted = new Set<string>();
    for (const row of source.rows.users ?? []) {
      const sourceId = stringValue(row.id);
      const keyHash = sourceKeyHash('users', row);
      const userHash = hash(`legacy-user:${sourceId}`);
      const role = stringValue(row.role ?? 'free');
      if (role === 'admin') {
        quarantined.push({
          sourceTable: 'users',
          sourceKeyHash: keyHash,
          userKeyHash: userHash,
          domain: 'identity',
          reasonCode: 'DEFAULT_OR_UNAPPROVED_ADMIN',
          detailCodes: ['OWNER_APPROVED_RECOVERY_REQUIRED'],
        });
        continue;
      }
      if (role !== 'free' && role !== 'premium') {
        quarantined.push({
          sourceTable: 'users',
          sourceKeyHash: keyHash,
          userKeyHash: userHash,
          domain: 'identity',
          reasonCode: 'UNSUPPORTED_LEGACY_ROLE',
          detailCodes: ['FIXED_ROLES_ONLY'],
        });
        continue;
      }
      const fullName = nullableString(row.full_name);
      const dateOfBirth = nullableDate(row.date_of_birth);
      if (!fullName || !dateOfBirth) {
        quarantined.push({
          sourceTable: 'users',
          sourceKeyHash: keyHash,
          userKeyHash: userHash,
          domain: 'identity',
          reasonCode: 'INVALID_RELATION',
          detailCodes: [
            ...(fullName ? [] : ['MISSING_FULL_NAME']),
            ...(dateOfBirth ? [] : ['MISSING_DATE_OF_BIRTH']),
          ],
        });
        continue;
      }
      accepted.add(sourceId);
      planned.push({
        sourceTable: 'users',
        sourceKeyHash: keyHash,
        domain: 'identity',
        targetTable: 'users',
        targetId: targetId('users', sourceId),
        values: {
          id: targetId('users', sourceId),
          email: stringValue(row.email).trim().toLowerCase(),
          password_hash: stringValue(row.password_hash),
          full_name: fullName,
          date_of_birth: dateOfBirth,
          role,
          status: row.status === 'active' || row.status === undefined ? 'active' : 'inactive',
          email_verified_at: nullableInstant(row.email_verified_at),
          created_at: instant(row.created_at),
          updated_at: instant(row.created_at),
          theme: nullableString(row.theme) ?? 'verdant-horizon',
          desired_language: supportedLocale(row.desired_language),
          onboard_step: integerValue(row.onboard_step ?? 0),
          needs_tutorial: booleanValue(row.needs_tutorial ?? true),
          tutorial_seen: !booleanValue(row.needs_tutorial ?? true),
        },
      });
    }
    return accepted;
  }

  private transformRow(table: string, row: LegacyRow, context: TransformationContext): void {
    const mapping = LEGACY_MAPPING_BY_TABLE.get(table);
    if (!mapping) {
      return;
    }
    const sourceHash = sourceKeyHash(table, row);
    const ownerId = mapping.ownerColumn ? nullableString(row[mapping.ownerColumn]) : null;
    if (ownerId && !context.acceptedUserIds.has(ownerId)) {
      quarantine(context, table, row, 'ORPHAN_OWNER', ['OWNER_NOT_MIGRATABLE']);
      return;
    }

    if (
      mapping.disposition === 'discard_security_secret' ||
      mapping.disposition === 'discard_derived' ||
      mapping.disposition === 'discard_unsupported'
    ) {
      context.skipped.push({
        sourceTable: table,
        sourceKeyHash: sourceHash,
        domain: mapping.domain,
        reasonCode: mapping.rationaleCode,
      });
      return;
    }
    if (mapping.disposition === 'quarantine') {
      quarantine(context, table, row, reasonForQuarantineTable(table), [mapping.rationaleCode]);
      return;
    }
    if (mapping.disposition === 'deduplicate') {
      this.transformDuplicate(table, row, context);
      return;
    }

    try {
      switch (table) {
        case 'transactions':
          transformTransaction(row, context);
          return;
        case 'goal_contributions':
          transformGoalContribution(row, context);
          return;
        case 'emergency_fund_tx':
          transformEmergencyMovement(row, context);
          return;
        case 'user_passkeys':
          transformPasskey(row, context);
          return;
        case 'notification_channels':
          transformNotificationChannel(row, context);
          return;
        case 'investments':
          transformInvestment(row, context);
          return;
        case 'investment_transactions':
          transformInvestmentMovement(row, context);
          return;
        default:
          transformDirect(table, row, context);
      }
    } catch (error) {
      quarantine(context, table, row, classifyValidationError(error), [validationDetail(error)]);
    }
  }

  private transformDuplicate(table: string, row: LegacyRow, context: TransformationContext): void {
    const authoritativeTable =
      table === 'goal_transactions' ? 'goal_contributions' : 'emergency_fund_tx';
    const duplicate = (context.source.rows[authoritativeTable] ?? []).some((candidate) =>
      sameMovement(table, row, candidate),
    );
    if (duplicate) {
      context.skipped.push({
        sourceTable: table,
        sourceKeyHash: sourceKeyHash(table, row),
        domain: table === 'goal_transactions' ? 'goals' : 'emergency_reserve',
        reasonCode: 'MATCHED_DUPLICATE_MOVEMENT',
      });
      return;
    }
    quarantine(context, table, row, 'AMBIGUOUS_DUPLICATE_MOVEMENT', ['NO_AUTHORITATIVE_MATCH']);
  }

  private checkDerivedBalances(context: TransformationContext): void {
    for (const investment of context.source.rows.investments ?? []) {
      const ownerId = nullableString(investment.user_id);
      if (!ownerId || !context.acceptedUserIds.has(ownerId)) {
        continue;
      }
      const investmentId = stringValue(investment.id);
      const movements = (context.source.rows.investment_transactions ?? []).filter(
        (row) => stringValue(row.investment_id) === investmentId,
      );
      const derived = movements.reduce(
        (sum, row) => sum.add(decimal(row.amount)),
        ExactDecimal.create('0'),
      );
      if (!derived.equals(decimal(investment.balance))) {
        quarantine(context, 'investments', investment, 'UNRECONCILED_DERIVED_BALANCE', [
          'INVESTMENT_BALANCE_DIFFERS_FROM_MOVEMENTS',
        ]);
      }
      if (investment.stock_id !== null && investment.stock_id !== undefined) {
        quarantine(context, 'investments', investment, 'UNTRACKED_INVESTMENT_SECURITIES_LINK', [
          'CONFIGURED_SCHEMA_STOCK_ID',
        ]);
      }
      if (
        investment.units !== null &&
        investment.units !== undefined &&
        !decimal(investment.units).isZero()
      ) {
        quarantine(context, 'investments', investment, 'UNTRACKED_INVESTMENT_SECURITIES_LINK', [
          'CONFIGURED_SCHEMA_UNITS',
        ]);
      }
    }
  }
}

function transformTransaction(row: LegacyRow, context: TransformationContext): void {
  const ownerId = stringValue(row.user_id);
  const sourceHash = sourceKeyHash('transactions', row);
  if (
    row.source === 'ef' ||
    (row.ef_tx_id !== null && row.ef_tx_id !== undefined) ||
    (row.source_ref_id !== null && row.source_ref_id !== undefined)
  ) {
    const reference = String(row.ef_tx_id ?? row.source_ref_id);
    const matched = (context.source.rows.emergency_fund_tx ?? []).some(
      (movement) => stringValue(movement.id) === reference,
    );
    if (matched) {
      context.skipped.push({
        sourceTable: 'transactions',
        sourceKeyHash: sourceHash,
        domain: 'ledger',
        reasonCode: 'MATCHED_EMERGENCY_TRANSFER_LEG',
      });
    } else {
      quarantine(context, 'transactions', row, 'AMBIGUOUS_DUPLICATE_MOVEMENT', [
        'MISSING_EMERGENCY_SOURCE_REFERENCE',
      ]);
    }
    return;
  }
  const kind = stringValue(row.kind);
  if (kind !== 'income' && kind !== 'spending') {
    throw new Error('INVALID_ENUM_TRANSACTION_KIND');
  }
  const amount = positiveDecimal(row.amount);
  const currencyCode = currency(row.currency);
  const targetEntryId = targetId('journal_entries', `transactions:${String(row.id)}`);
  const accountId = targetId('ledger_accounts', `cash:${ownerId}`);
  const common = {
    entry_id: targetEntryId,
    user_id: targetId('users', ownerId),
    amount,
    currency: currencyCode,
    created_at: instant(row.created_at),
  };
  context.planned.push(
    {
      sourceTable: 'transactions',
      sourceKeyHash: sourceHash,
      domain: 'ledger',
      targetTable: 'journal_entries',
      targetId: targetEntryId,
      values: {
        id: targetEntryId,
        user_id: targetId('users', ownerId),
        economic_type: kind === 'income' ? 'external_income' : 'external_expense',
        category_id:
          row.category_id === null ? null : targetId('categories', stringValue(row.category_id)),
        note: nullableString(row.note),
        source_module: 'migration',
        source_reference_id: targetId('legacy_source', `transactions:${String(row.id)}`),
        idempotency_key_hash: hash(`legacy:transactions:${String(row.id)}`),
        posted_on: date(row.occurred_on),
        effective_at: `${date(row.occurred_on)}T12:00:00.000Z`,
        created_at: instant(row.created_at),
        actor_user_id: targetId('users', ownerId),
        reverses_entry_id: null,
        replaces_entry_id: null,
      },
      reconciliation: {
        userKeyHash: hash(`legacy-user:${ownerId}`),
        currency: currencyCode,
        amount,
      },
    },
    plannedLeg('transactions', sourceHash, `${String(row.id)}:owned`, {
      ...common,
      id: targetId('journal_legs', `transactions:${String(row.id)}:owned`),
      account_id: accountId,
      side: kind === 'income' ? 'debit' : 'credit',
    }),
    plannedLeg('transactions', sourceHash, `${String(row.id)}:external`, {
      ...common,
      id: targetId('journal_legs', `transactions:${String(row.id)}:external`),
      account_id: null,
      side: kind === 'income' ? 'credit' : 'debit',
    }),
  );
}

function transformGoalContribution(row: LegacyRow, context: TransformationContext): void {
  transformInternalTransfer({
    table: 'goal_contributions',
    domain: 'goals',
    sourceModule: 'goals',
    ownerId: stringValue(row.user_id),
    moduleId: stringValue(row.goal_id),
    moduleAccountKind: 'goal',
    direction: decimal(row.amount).isNegative() ? 'withdrawal' : 'contribution',
    amount: absolutePositiveDecimal(row.amount),
    currency: currency(row.currency),
    occurredOn: date(row.occurred_on),
    createdAt: instant(row.created_at),
    note: nullableString(row.note),
    movementTable: 'goal_contributions',
    context,
  });
}

function transformEmergencyMovement(row: LegacyRow, context: TransformationContext): void {
  const kind = stringValue(row.kind);
  if (kind !== 'add' && kind !== 'withdraw') {
    throw new Error('INVALID_ENUM_EMERGENCY_KIND');
  }
  transformInternalTransfer({
    table: 'emergency_fund_tx',
    domain: 'emergency_reserve',
    sourceModule: 'emergency_fund',
    ownerId: stringValue(row.user_id),
    moduleId: stringValue(row.user_id),
    moduleAccountKind: 'emergency_reserve',
    direction: kind === 'add' ? 'contribution' : 'withdrawal',
    amount: positiveDecimal(row.amount_native),
    currency: currency(row.currency_native),
    occurredOn: date(row.occurred_on),
    createdAt: `${date(row.occurred_on)}T12:00:00.000Z`,
    note: nullableString(row.note),
    movementTable: 'emergency_reserve_movements',
    context,
  });
}

function transformInvestmentMovement(row: LegacyRow, context: TransformationContext): void {
  const investment = (context.source.rows.investments ?? []).find(
    (candidate) => stringValue(candidate.id) === stringValue(row.investment_id),
  );
  if (!investment) {
    throw new Error('INVALID_RELATION_INVESTMENT');
  }
  const signed = decimal(row.amount);
  if (signed.isZero()) {
    throw new Error('INVALID_DECIMAL_ZERO_AMOUNT');
  }
  transformInternalTransfer({
    table: 'investment_transactions',
    domain: 'investments',
    sourceModule: 'investments',
    ownerId: stringValue(row.user_id),
    moduleId: stringValue(row.investment_id),
    moduleAccountKind: 'investment',
    direction: signed.isNegative() ? 'withdrawal' : 'deposit',
    amount: signed.isNegative()
      ? signed.multiply(ExactDecimal.create('-1')).toString()
      : signed.toString(),
    currency: currency(investment.currency),
    occurredOn: date(row.created_at),
    createdAt: instant(row.created_at),
    note: nullableString(row.note),
    movementTable: 'investment_movements',
    context,
  });
}

function transformInternalTransfer(input: {
  table: string;
  domain: 'goals' | 'emergency_reserve' | 'investments';
  sourceModule: 'goals' | 'emergency_fund' | 'investments';
  ownerId: string;
  moduleId: string;
  moduleAccountKind: 'goal' | 'emergency_reserve' | 'investment';
  direction: string;
  amount: string;
  currency: string;
  occurredOn: string;
  createdAt: string;
  note: string | null;
  movementTable: string;
  context: TransformationContext;
}): void {
  const row = (input.context.source.rows[input.table] ?? []).find(
    (candidate) => stringValue(candidate.id) === input.moduleId,
  );
  const sourceRow =
    row ??
    (input.context.source.rows[input.table] ?? []).find(
      (candidate) =>
        stringValue(candidate.user_id) === input.ownerId &&
        date(candidate.occurred_on ?? candidate.created_at) === input.occurredOn &&
        absolutePositiveDecimal(candidate.amount ?? candidate.amount_native) === input.amount,
    );
  if (!sourceRow) {
    throw new Error('INVALID_RELATION_SOURCE_ROW');
  }
  const sourceHash = sourceKeyHash(input.table, sourceRow);
  const sourceId = stringValue(sourceRow.id);
  const targetUserId = targetId('users', input.ownerId);
  const entryId = targetId('journal_entries', `${input.table}:${sourceId}`);
  const moduleAccountId = targetId(
    'ledger_accounts',
    `${input.moduleAccountKind}:${input.ownerId}:${input.moduleId}`,
  );
  const cashAccountId = targetId('ledger_accounts', `cash:${input.ownerId}`);
  const intoModule = input.direction === 'contribution' || input.direction === 'deposit';
  const common = {
    entry_id: entryId,
    user_id: targetUserId,
    amount: input.amount,
    currency: input.currency,
    created_at: input.createdAt,
  };
  input.context.planned.push(
    {
      sourceTable: input.table,
      sourceKeyHash: sourceHash,
      domain: input.domain,
      targetTable: 'journal_entries',
      targetId: entryId,
      values: {
        id: entryId,
        user_id: targetUserId,
        economic_type: 'internal_transfer',
        category_id: null,
        note: input.note,
        source_module: input.sourceModule,
        source_reference_id: targetId('legacy_source', `${input.table}:${sourceId}`),
        idempotency_key_hash: hash(`legacy:${input.table}:${sourceId}`),
        posted_on: input.occurredOn,
        effective_at: `${input.occurredOn}T12:00:00.000Z`,
        created_at: input.createdAt,
        actor_user_id: targetUserId,
        reverses_entry_id: null,
        replaces_entry_id: null,
      },
      reconciliation: {
        userKeyHash: hash(`legacy-user:${input.ownerId}`),
        currency: input.currency,
        amount: input.amount,
      },
    },
    plannedLeg(input.table, sourceHash, `${sourceId}:cash`, {
      ...common,
      id: targetId('journal_legs', `${input.table}:${sourceId}:cash`),
      account_id: cashAccountId,
      side: intoModule ? 'credit' : 'debit',
    }),
    plannedLeg(input.table, sourceHash, `${sourceId}:module`, {
      ...common,
      id: targetId('journal_legs', `${input.table}:${sourceId}:module`),
      account_id: moduleAccountId,
      side: intoModule ? 'debit' : 'credit',
    }),
    {
      sourceTable: input.table,
      sourceKeyHash: sourceHash,
      domain: input.domain,
      targetTable: input.movementTable,
      targetId: targetId(input.movementTable, sourceId),
      values: {
        id: targetId(input.movementTable, sourceId),
        user_id: targetUserId,
        [`${input.domain === 'goals' ? 'goal' : input.domain === 'investments' ? 'investment' : 'holding'}_id`]:
          input.domain === 'emergency_reserve'
            ? cashAccountId
            : targetId(input.domain === 'goals' ? 'goals' : 'investments', input.moduleId),
        journal_entry_id: entryId,
        direction: input.direction,
        amount: input.amount,
        currency: input.currency,
        occurred_on: input.occurredOn,
        note: input.note,
        reversed_by_journal_entry_id: null,
        created_at: input.createdAt,
      },
    },
  );
}

function transformPasskey(row: LegacyRow, context: TransformationContext): void {
  const sourceHash = sourceKeyHash('user_passkeys', row);
  let publicKey: string;
  try {
    publicKey = createPublicKey(stringValue(row.public_key_pem))
      .export({ type: 'spki', format: 'der' })
      .toString('base64');
  } catch {
    quarantine(context, 'user_passkeys', row, 'INVALID_RELATION', [
      'INCOMPATIBLE_PASSKEY_PUBLIC_KEY',
    ]);
    return;
  }
  const sourceId = stringValue(row.id);
  context.planned.push({
    sourceTable: 'user_passkeys',
    sourceKeyHash: sourceHash,
    domain: 'identity',
    targetTable: 'passkeys',
    targetId: targetId('passkeys', sourceId),
    values: {
      id: targetId('passkeys', sourceId),
      user_id: targetId('users', stringValue(row.user_id)),
      credential_id: stringValue(row.credential_id),
      public_key_base64: publicKey,
      counter: nonNegativeIntegerString(row.sign_count),
      transports: [],
      device_type: 'unknown',
      backed_up: false,
      label: nullableString(row.label) ?? 'Legacy passkey',
      created_at: instant(row.created_at),
      last_used_at: nullableInstant(row.last_used),
      revision: '0',
    },
  });
}

function transformNotificationChannel(row: LegacyRow, context: TransformationContext): void {
  if (row.channel !== 'email') {
    quarantine(context, 'notification_channels', row, 'UNSUPPORTED_LEGACY_CHANNEL', [
      'EMAIL_ONLY_V1',
    ]);
    return;
  }
  const config = objectValue(row.config);
  if (Object.keys(config).some((key) => /secret|token|password|api.?key/i.test(key))) {
    quarantine(context, 'notification_channels', row, 'HARDCODED_OR_EMBEDDED_SECRET', [
      'PROVIDER_CONFIGURATION_REENTRY_REQUIRED',
    ]);
    return;
  }
  context.planned.push({
    sourceTable: 'notification_channels',
    sourceKeyHash: sourceKeyHash('notification_channels', row),
    domain: 'notifications',
    targetTable: 'email_channel_settings',
    targetId: targetId('email_channel_settings', 'singleton'),
    values: {
      id: targetId('email_channel_settings', 'singleton'),
      enabled: false,
      provider: 'disabled',
      from_address: null,
      reply_to_address: null,
      updated_by: null,
      created_at: instant(row.created_at),
      updated_at: instant(row.updated_at),
    },
  });
}

function transformInvestment(row: LegacyRow, context: TransformationContext): void {
  const type = stringValue(row.type);
  if (!['savings', 'etf', 'stock'].includes(type)) {
    throw new Error('INVALID_ENUM_INVESTMENT_TYPE');
  }
  const sourceId = stringValue(row.id);
  const ownerId = stringValue(row.user_id);
  context.planned.push({
    sourceTable: 'investments',
    sourceKeyHash: sourceKeyHash('investments', row),
    domain: 'investments',
    targetTable: 'investments',
    targetId: targetId('investments', sourceId),
    values: {
      id: targetId('investments', sourceId),
      user_id: targetId('users', ownerId),
      type,
      name: stringValue(row.name),
      provider: nullableString(row.provider),
      identifier: nullableString(row.identifier),
      notes: nullableString(row.notes),
      currency: currency(row.currency),
      scenario_annual_rate:
        row.interest_rate === null ? null : decimal(row.interest_rate).toString(),
      scenario_frequency: scenarioFrequency(row.interest_frequency),
      scenario_version: 'nominal_compound_scenario_v1',
      account_id: targetId('ledger_accounts', `investment:${ownerId}:${sourceId}`),
      created_at: instant(row.created_at),
      updated_at: instant(row.updated_at),
    },
  });
}

function transformDirect(table: string, row: LegacyRow, context: TransformationContext): void {
  const mapping = LEGACY_MAPPING_BY_TABLE.get(table);
  if (!mapping || mapping.targetTables.length === 0) {
    return;
  }
  const sourceId = directSourceId(table, row);
  const ownerId = mapping.ownerColumn ? nullableString(row[mapping.ownerColumn]) : null;
  const targetTable = mapping.targetTables[0]!;
  const targetValues = directValues(table, row, ownerId);
  if (targetValues.id !== undefined) {
    targetValues.id = targetId(targetTable, sourceId);
  }
  const planned: PlannedTargetRow = {
    sourceTable: table,
    sourceKeyHash: sourceKeyHash(table, row),
    domain: mapping.domain,
    targetTable,
    targetId: targetId(targetTable, sourceId),
    values: targetValues,
  };
  if (mapping.amountColumn && mapping.currencyColumn && ownerId) {
    const amountValue = row[mapping.amountColumn];
    const currencyValue = row[mapping.currencyColumn];
    if (amountValue !== null && currencyValue !== null) {
      planned.reconciliation = {
        userKeyHash: hash(`legacy-user:${ownerId}`),
        currency: currency(currencyValue),
        amount: absoluteDecimal(amountValue),
      };
    }
  }
  context.planned.push(planned);
}

function directValues(
  table: string,
  row: LegacyRow,
  ownerId: string | null,
): Record<string, unknown> {
  const values: Record<string, unknown> = { ...row };
  delete values.current_amount;
  delete values.total;
  delete values.balance;
  delete values.email_verification_token;
  delete values.email_verification_sent_at;
  delete values.full_name_search;
  delete values.deactivated_at;
  if (ownerId) {
    values.user_id = targetId('users', ownerId);
  }
  for (const relation of [
    ['category_id', 'categories'],
    ['cashflow_rule_id', 'budget_rules'],
    ['goal_id', 'goals'],
    ['loan_id', 'loans'],
    ['investment_id', 'investments'],
    ['stock_id', 'securities_instruments'],
    ['feedback_id', 'feedback'],
    ['admin_id', 'users'],
    ['subscription_id', 'user_subscriptions'],
    ['invoice_id', 'user_invoices'],
  ] as const) {
    const [column, targetTable] = relation;
    if (values[column] !== null && values[column] !== undefined) {
      values[column] = targetId(targetTable, stringValue(values[column]));
    }
  }
  if (table === 'currencies') {
    return {
      code: currency(row.code),
      name: stringValue(row.name),
      minor_unit: currency(row.code) === 'HUF' ? 0 : 2,
      rounding_mode: 'HALF_EVEN',
      active: true,
    };
  }
  if (table === 'user_currencies') {
    return {
      user_id: targetId('users', stringValue(row.user_id)),
      code: currency(row.code),
      is_main: booleanValue(row.is_main),
      created_at: '1970-01-01T00:00:00.000Z',
    };
  }
  if (table === 'fx_rates') {
    return {
      id: targetId(
        'fx_quotes',
        `${String(row.rate_date)}:${String(row.base_code)}:${String(row.code)}`,
      ),
      provider: 'legacy_import',
      base_code: currency(row.base_code),
      quote_code: currency(row.code),
      rate: positiveDecimal(row.rate),
      observed_on: date(row.rate_date),
      observed_at: `${date(row.rate_date)}T12:00:00.000Z`,
      fetched_at: `${date(row.rate_date)}T12:00:00.000Z`,
      quality: 'legacy_imported',
      status: 'available',
    };
  }
  if (table === 'goals') {
    const status =
      row.status === 'done' ? 'completed' : row.status === 'paused' ? 'paused' : 'active';
    return {
      id: targetId('goals', stringValue(row.id)),
      user_id: targetId('users', stringValue(row.user_id)),
      title: stringValue(row.title),
      target_amount: positiveDecimal(row.target_amount),
      currency: currency(row.currency),
      deadline: nullableDate(row.deadline),
      priority: integerValue(row.priority),
      status,
      category_id:
        row.category_id === null || row.category_id === undefined
          ? null
          : targetId('categories', stringValue(row.category_id)),
      archived_at: nullableInstant(row.archived_at),
      created_at: nullableInstant(row.archived_at) ?? '1970-01-01T00:00:00.000Z',
      updated_at: nullableInstant(row.archived_at) ?? '1970-01-01T00:00:00.000Z',
    };
  }
  return values;
}

function reconcile(
  source: LegacySourceSnapshot,
  planned: readonly PlannedTargetRow[],
  quarantined: readonly QuarantinedLegacyRow[],
  skipped: readonly SkippedLegacyRow[],
  acceptedUserIds: ReadonlySet<string>,
): readonly LegacyReconciliation[] {
  interface Bucket {
    sourceCount: number;
    plannedCount: number;
    quarantineCount: number;
    sourceAmount: ExactDecimal;
    plannedAmount: ExactDecimal;
    explanations: Set<string>;
  }
  const buckets = new Map<string, Bucket>();
  const skippedSourceKeys = new Set(
    skipped.map((row) => `${row.sourceTable}:${row.sourceKeyHash}`),
  );
  const bucket = (userHash: string, domain: LegacyDomain, code: string): Bucket => {
    const key = `${userHash}\0${domain}\0${code}`;
    const current = buckets.get(key) ?? {
      sourceCount: 0,
      plannedCount: 0,
      quarantineCount: 0,
      sourceAmount: ExactDecimal.create('0'),
      plannedAmount: ExactDecimal.create('0'),
      explanations: new Set<string>(),
    };
    buckets.set(key, current);
    return current;
  };

  for (const mapping of LEGACY_RELATION_MAPPINGS) {
    if (!mapping.amountColumn || !mapping.currencyColumn || !mapping.ownerColumn) {
      continue;
    }
    for (const row of source.rows[mapping.sourceTable] ?? []) {
      if (
        mapping.disposition !== 'map' ||
        skippedSourceKeys.has(`${mapping.sourceTable}:${sourceKeyHash(mapping.sourceTable, row)}`)
      ) {
        continue;
      }
      const ownerId = nullableString(row[mapping.ownerColumn]);
      const currencyValue = nullableString(row[mapping.currencyColumn]);
      const amountValue = row[mapping.amountColumn];
      if (
        !ownerId ||
        !acceptedUserIds.has(ownerId) ||
        !currencyValue ||
        amountValue === null ||
        amountValue === undefined
      ) {
        continue;
      }
      try {
        const current = bucket(
          hash(`legacy-user:${ownerId}`),
          mapping.domain,
          currency(currencyValue),
        );
        current.sourceCount += 1;
        current.sourceAmount = current.sourceAmount.add(
          ExactDecimal.create(absoluteDecimal(amountValue)),
        );
      } catch {
        // Invalid values are represented by the quarantine outcome and do not
        // enter an exact decimal bucket.
      }
    }
  }
  for (const row of planned) {
    if (!row.reconciliation) {
      continue;
    }
    const current = bucket(row.reconciliation.userKeyHash, row.domain, row.reconciliation.currency);
    current.plannedCount += 1;
    current.plannedAmount = current.plannedAmount.add(
      ExactDecimal.create(row.reconciliation.amount),
    );
  }
  for (const row of quarantined) {
    if (!row.userKeyHash || !row.reconciliation) {
      continue;
    }
    const current = bucket(row.userKeyHash, row.domain, row.reconciliation.currency);
    current.quarantineCount += 1;
    current.explanations.add(row.reasonCode);
  }
  for (const row of skipped) {
    if (
      row.reasonCode === 'MATCHED_DUPLICATE_MOVEMENT' ||
      row.reasonCode === 'MATCHED_EMERGENCY_TRANSFER_LEG'
    ) {
      for (const current of buckets.values()) {
        current.explanations.add(row.reasonCode);
      }
    }
  }

  return [...buckets.entries()]
    .map(([key, current]): LegacyReconciliation => {
      const [userKeyHash, domain, code] = key.split('\0') as [string, LegacyDomain, string];
      const difference = current.sourceAmount.subtract(current.plannedAmount);
      const hasQuarantine = current.quarantineCount > 0;
      const explanations = [...current.explanations].sort();
      return {
        userKeyHash,
        domain,
        currency: code,
        sourceCount: current.sourceCount,
        plannedCount: current.plannedCount,
        quarantineCount: current.quarantineCount,
        sourceAmount: current.sourceAmount.toString(),
        plannedAmount: current.plannedAmount.toString(),
        difference: difference.toString(),
        status:
          difference.isZero() && !hasQuarantine
            ? explanations.length > 0
              ? 'explained'
              : 'exact'
            : 'blocked',
        explanationCodes:
          difference.isZero() && !hasQuarantine
            ? explanations
            : [
                ...new Set([
                  ...explanations,
                  hasQuarantine ? 'QUARANTINED_ROWS' : 'AMOUNT_DIFFERENCE',
                ]),
              ].sort(),
      };
    })
    .sort((left, right) =>
      `${left.userKeyHash}:${left.domain}:${left.currency}`.localeCompare(
        `${right.userKeyHash}:${right.domain}:${right.currency}`,
      ),
    );
}

function quarantine(
  context: TransformationContext,
  table: string,
  row: LegacyRow,
  reasonCode: QuarantineReason,
  detailCodes: readonly string[],
): void {
  const mapping = LEGACY_MAPPING_BY_TABLE.get(table);
  const ownerId = mapping?.ownerColumn ? nullableString(row[mapping.ownerColumn]) : null;
  const currencyValue =
    mapping?.currencyColumn && row[mapping.currencyColumn] !== null
      ? nullableString(row[mapping.currencyColumn])
      : null;
  const amountValue = mapping?.amountColumn ? row[mapping.amountColumn] : null;
  let reconciliation: QuarantinedLegacyRow['reconciliation'];
  try {
    if (currencyValue && amountValue !== null && amountValue !== undefined) {
      reconciliation = {
        currency: currency(currencyValue),
        amount: absoluteDecimal(amountValue),
      };
    }
  } catch {
    reconciliation = undefined;
  }
  const candidate: QuarantinedLegacyRow = {
    sourceTable: table,
    sourceKeyHash: sourceKeyHash(table, row),
    userKeyHash: ownerId ? hash(`legacy-user:${ownerId}`) : null,
    domain: mapping?.domain ?? 'removed',
    reasonCode,
    detailCodes: [...new Set(detailCodes)].sort(),
    reconciliation,
  };
  const existingIndex = context.quarantined.findIndex(
    (existing) =>
      existing.sourceTable === candidate.sourceTable &&
      existing.sourceKeyHash === candidate.sourceKeyHash &&
      existing.reasonCode === candidate.reasonCode,
  );
  if (existingIndex === -1) {
    context.quarantined.push(candidate);
  } else {
    const existing = context.quarantined[existingIndex]!;
    context.quarantined[existingIndex] = {
      ...existing,
      detailCodes: [...new Set([...existing.detailCodes, ...candidate.detailCodes])].sort(),
      reconciliation: existing.reconciliation ?? candidate.reconciliation,
    };
  }
}

function plannedLeg(
  sourceTable: string,
  sourceHash: string,
  idSeed: string,
  values: Readonly<Record<string, unknown>>,
): PlannedTargetRow {
  return {
    sourceTable,
    sourceKeyHash: sourceHash,
    domain: sourceTable === 'transactions' ? 'ledger' : 'ledger',
    targetTable: 'journal_legs',
    targetId: targetId('journal_legs', idSeed),
    values,
  };
}

function sameMovement(table: string, row: LegacyRow, candidate: LegacyRow): boolean {
  const candidateAmount =
    table === 'emergency_transactions' ? candidate.amount_native : candidate.amount;
  const candidateCurrency =
    table === 'emergency_transactions' ? candidate.currency_native : candidate.currency;
  const kindMatches =
    table !== 'emergency_transactions' ||
    (row.kind === 'deposit' ? candidate.kind === 'add' : candidate.kind === 'withdraw');
  return (
    stringValue(row.user_id) === stringValue(candidate.user_id) &&
    (table !== 'goal_transactions' ||
      stringValue(row.goal_id) === stringValue(candidate.goal_id)) &&
    date(row.occurred_on) === date(candidate.occurred_on) &&
    decimal(row.amount).equals(decimal(candidateAmount)) &&
    (candidateCurrency === undefined ||
      row.currency === undefined ||
      nullableString(row.currency) === nullableString(candidateCurrency)) &&
    kindMatches
  );
}

function emptyBlockedPlan(source: LegacySourceSnapshot): LegacyMigrationPlan {
  return {
    transformerVersion: LEGACY_TRANSFORMER_VERSION,
    sourceSchemaVersion: source.schema.version,
    sourceSchemaFingerprint: source.schema.fingerprint,
    sourceDataFingerprint: source.dataFingerprint,
    sourceRowCount: source.rowCount,
    planned: [],
    quarantined: [],
    skipped: [],
    reconciliation: [],
    blockingCodes: [...source.schema.blockingCodes],
  };
}

function directSourceId(table: string, row: LegacyRow): string {
  if (row.id !== null && row.id !== undefined) return stringValue(row.id);
  if (table === 'user_currencies') {
    return `${stringValue(row.user_id)}:${stringValue(row.code)}`;
  }
  if (table === 'fx_rates') {
    return `${stringValue(row.rate_date)}:${stringValue(row.base_code)}:${stringValue(row.code)}`;
  }
  if (row.user_id !== null && row.user_id !== undefined) return stringValue(row.user_id);
  return sourceKeyHash(table, row);
}

function reasonForQuarantineTable(table: string): QuarantineReason {
  return table === 'baby_steps' ? 'SCHEMA_DRIFT' : 'INVALID_RELATION';
}

function classifyValidationError(error: unknown): QuarantineReason {
  const message = validationDetail(error);
  if (message.includes('DECIMAL')) return 'INVALID_DECIMAL';
  if (message.includes('CURRENCY')) return 'INVALID_CURRENCY';
  if (message.includes('DATE') || message.includes('INSTANT')) return 'INVALID_DATE';
  if (message.includes('ENUM')) return 'INVALID_ENUM';
  return 'INVALID_RELATION';
}

function validationDetail(error: unknown): string {
  const value = error instanceof Error ? error.message : 'INVALID_SOURCE_VALUE';
  return value
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 80);
}

function targetId(namespace: string, sourceId: string): string {
  const digest = createHash('sha256').update(`${namespace}\0${sourceId}`).digest('hex');
  return `${digest.slice(0, 8)}-${digest.slice(8, 12)}-4${digest.slice(13, 16)}-8${digest.slice(17, 20)}-${digest.slice(20, 32)}`;
}

function hash(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}

function decimal(value: unknown): ExactDecimal {
  return ExactDecimal.create(stringValue(value));
}

function positiveDecimal(value: unknown): string {
  const parsed = decimal(value);
  if (!parsed.isPositive()) {
    throw new Error('INVALID_DECIMAL_POSITIVE_AMOUNT_REQUIRED');
  }
  return parsed.toString();
}

function absolutePositiveDecimal(value: unknown): string {
  const parsed = decimal(value);
  if (parsed.isZero()) {
    throw new Error('INVALID_DECIMAL_ZERO_AMOUNT');
  }
  return parsed.isNegative()
    ? parsed.multiply(ExactDecimal.create('-1')).toString()
    : parsed.toString();
}

function absoluteDecimal(value: unknown): string {
  const parsed = decimal(value);
  return parsed.isNegative()
    ? parsed.multiply(ExactDecimal.create('-1')).toString()
    : parsed.toString();
}

function currency(value: unknown): string {
  return CurrencyCode.create(stringValue(value)).toString();
}

function stringValue(value: unknown): string {
  if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'bigint') {
    throw new Error('INVALID_RELATION_REQUIRED_STRING');
  }
  const result = String(value).trim();
  if (!result) {
    throw new Error('INVALID_RELATION_EMPTY_STRING');
  }
  return result;
}

function nullableString(value: unknown): string | null {
  if (value === null || value === undefined) return null;
  return stringValue(value);
}

function integerValue(value: unknown): number {
  const result = Number(value);
  if (!Number.isSafeInteger(result)) {
    throw new Error('INVALID_RELATION_INTEGER');
  }
  return result;
}

function nonNegativeIntegerString(value: unknown): string {
  const result = stringValue(value);
  if (!/^\d+$/.test(result)) {
    throw new Error('INVALID_RELATION_NON_NEGATIVE_INTEGER');
  }
  return result;
}

function booleanValue(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (value === 1 || value === '1' || value === 'true') return true;
  if (value === 0 || value === '0' || value === 'false') return false;
  throw new Error('INVALID_RELATION_BOOLEAN');
}

function date(value: unknown): string {
  const parsed = value instanceof Date ? value : new Date(stringValue(value));
  if (Number.isNaN(parsed.getTime())) {
    throw new Error('INVALID_DATE');
  }
  return parsed.toISOString().slice(0, 10);
}

function nullableDate(value: unknown): string | null {
  return value === null || value === undefined ? null : date(value);
}

function instant(value: unknown): string {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toISOString();
  }
  const parsed = new Date(stringValue(value));
  if (Number.isNaN(parsed.getTime())) {
    throw new Error('INVALID_INSTANT');
  }
  return parsed.toISOString();
}

function nullableInstant(value: unknown): string | null {
  return value === null || value === undefined ? null : instant(value);
}

function supportedLocale(value: unknown): string {
  return value === 'es' || value === 'hu' ? value : 'en';
}

function scenarioFrequency(value: unknown): string {
  return value === 'daily' || value === 'weekly' || value === 'annual' ? value : 'monthly';
}

function objectValue(value: unknown): Record<string, unknown> {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    throw new Error('INVALID_RELATION_OBJECT');
  }
  return value as Record<string, unknown>;
}

function sortPlanned(rows: PlannedTargetRow[]): readonly PlannedTargetRow[] {
  return rows.sort((left, right) =>
    `${left.sourceTable}:${left.sourceKeyHash}:${left.targetTable}:${left.targetId}`.localeCompare(
      `${right.sourceTable}:${right.sourceKeyHash}:${right.targetTable}:${right.targetId}`,
    ),
  );
}

function sortQuarantine(rows: QuarantinedLegacyRow[]): readonly QuarantinedLegacyRow[] {
  return rows.sort((left, right) =>
    `${left.sourceTable}:${left.sourceKeyHash}:${left.reasonCode}`.localeCompare(
      `${right.sourceTable}:${right.sourceKeyHash}:${right.reasonCode}`,
    ),
  );
}

function sortSkipped(rows: SkippedLegacyRow[]): readonly SkippedLegacyRow[] {
  return rows.sort((left, right) =>
    `${left.sourceTable}:${left.sourceKeyHash}:${left.reasonCode}`.localeCompare(
      `${right.sourceTable}:${right.sourceKeyHash}:${right.reasonCode}`,
    ),
  );
}

export function migrationPlanFingerprint(plan: LegacyMigrationPlan): string {
  return createHash('sha256')
    .update(
      canonicalJson({
        ...plan,
        planned: plan.planned.map((row) => ({
          sourceTable: row.sourceTable,
          sourceKeyHash: row.sourceKeyHash,
          domain: row.domain,
          targetTable: row.targetTable,
          targetId: row.targetId,
          reconciliation: row.reconciliation,
        })),
      }),
    )
    .digest('hex');
}
