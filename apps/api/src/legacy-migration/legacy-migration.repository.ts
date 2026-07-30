import { createHash } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import type { LegacyMigrationPlan } from './legacy-migration.types';

export interface PersistedMigrationBatch {
  id: string;
  status: 'completed' | 'blocked';
  reused: boolean;
  sourceRowCount: number;
  plannedRowCount: number;
  quarantineRowCount: number;
}

export class LegacyMigrationRepository {
  constructor(private readonly pool: Pool) {}

  async persistRehearsal(
    plan: LegacyMigrationPlan,
    mode: 'rehearsal' | 'cutover',
    now: Date,
  ): Promise<PersistedMigrationBatch> {
    const client = await this.pool.connect();
    try {
      await client.query('BEGIN');
      const batchId = deterministicUuid(
        `batch:${plan.transformerVersion}:${plan.sourceDataFingerprint}:${mode}`,
      );
      const existing = await client.query<{
        id: string;
        status: 'completed' | 'blocked';
        source_row_count: string;
        planned_row_count: string;
        quarantine_row_count: string;
      }>(
        `SELECT id,status,source_row_count,planned_row_count,quarantine_row_count
           FROM mymoneymap.legacy_migration_batches
          WHERE transformer_version=$1 AND source_data_fingerprint=$2 AND mode=$3
          FOR UPDATE`,
        [plan.transformerVersion, plan.sourceDataFingerprint, mode],
      );
      if (existing.rows[0]?.status === 'completed' || existing.rows[0]?.status === 'blocked') {
        await client.query('COMMIT');
        return {
          id: existing.rows[0].id,
          status: existing.rows[0].status,
          reused: true,
          sourceRowCount: Number(existing.rows[0].source_row_count),
          plannedRowCount: Number(existing.rows[0].planned_row_count),
          quarantineRowCount: Number(existing.rows[0].quarantine_row_count),
        };
      }

      await client.query(
        `INSERT INTO mymoneymap.legacy_migration_batches
          (id,transformer_version,source_schema_version,source_schema_fingerprint,
           source_data_fingerprint,mode,status,checkpoint,source_row_count,
           planned_row_count,quarantine_row_count,error_code,created_at,started_at)
         VALUES($1,$2,$3,$4,$5,$6,'running',$7,$8,$9,$10,NULL,$11,$11)
         ON CONFLICT(transformer_version,source_data_fingerprint,mode)
         DO UPDATE SET checkpoint=EXCLUDED.checkpoint,started_at=COALESCE(
           mymoneymap.legacy_migration_batches.started_at,EXCLUDED.started_at
         )`,
        [
          batchId,
          plan.transformerVersion,
          plan.sourceSchemaVersion,
          plan.sourceSchemaFingerprint,
          plan.sourceDataFingerprint,
          mode,
          JSON.stringify({
            phase: 'persisting_reports',
            transformerVersion: plan.transformerVersion,
          }),
          plan.sourceRowCount,
          plan.planned.length,
          plan.quarantined.length,
          now,
        ],
      );

      for (const row of plan.planned) {
        await insertRowLedger(client, {
          id: deterministicUuid(
            `${batchId}:planned:${row.sourceTable}:${row.sourceKeyHash}:${row.targetTable}`,
          ),
          batchId,
          sourceTable: row.sourceTable,
          sourceKeyHash: row.sourceKeyHash,
          domain: row.domain,
          targetTable: row.targetTable,
          targetId: row.targetId,
          outcome: 'planned',
          reasonCode: null,
          now,
        });
      }
      for (const row of plan.quarantined) {
        await insertRowLedger(client, {
          id: deterministicUuid(
            `${batchId}:quarantined:${row.sourceTable}:${row.sourceKeyHash}:${row.domain}`,
          ),
          batchId,
          sourceTable: row.sourceTable,
          sourceKeyHash: row.sourceKeyHash,
          domain: row.domain,
          targetTable: null,
          targetId: null,
          outcome: 'quarantined',
          reasonCode: row.reasonCode,
          now,
        });
        await client.query(
          `INSERT INTO mymoneymap.legacy_migration_quarantine
            (id,batch_id,source_table,source_key_hash,user_key_hash,domain,reason_code,
             detail_codes,created_at)
           VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)
           ON CONFLICT(batch_id,source_table,source_key_hash,reason_code) DO NOTHING`,
          [
            deterministicUuid(
              `${batchId}:quarantine:${row.sourceTable}:${row.sourceKeyHash}:${row.reasonCode}`,
            ),
            batchId,
            row.sourceTable,
            row.sourceKeyHash,
            row.userKeyHash,
            row.domain,
            row.reasonCode,
            row.detailCodes,
            now,
          ],
        );
      }
      for (const row of plan.skipped) {
        await insertRowLedger(client, {
          id: deterministicUuid(
            `${batchId}:skipped:${row.sourceTable}:${row.sourceKeyHash}:${row.domain}`,
          ),
          batchId,
          sourceTable: row.sourceTable,
          sourceKeyHash: row.sourceKeyHash,
          domain: row.domain,
          targetTable: null,
          targetId: null,
          outcome: 'skipped',
          reasonCode: row.reasonCode,
          now,
        });
      }
      for (const result of plan.reconciliation) {
        await client.query(
          `INSERT INTO mymoneymap.legacy_migration_reconciliation
            (id,batch_id,user_key_hash,domain,currency,source_count,planned_count,
             quarantine_count,source_amount,planned_amount,difference,status,
             explanation_codes,created_at)
           VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14)
           ON CONFLICT(batch_id,user_key_hash,domain,currency)
           DO UPDATE SET
             source_count=EXCLUDED.source_count,
             planned_count=EXCLUDED.planned_count,
             quarantine_count=EXCLUDED.quarantine_count,
             source_amount=EXCLUDED.source_amount,
             planned_amount=EXCLUDED.planned_amount,
             difference=EXCLUDED.difference,
             status=EXCLUDED.status,
             explanation_codes=EXCLUDED.explanation_codes`,
          [
            deterministicUuid(
              `${batchId}:reconciliation:${result.userKeyHash}:${result.domain}:${result.currency}`,
            ),
            batchId,
            result.userKeyHash,
            result.domain,
            result.currency,
            result.sourceCount,
            result.plannedCount,
            result.quarantineCount,
            result.sourceAmount,
            result.plannedAmount,
            result.difference,
            result.status,
            result.explanationCodes,
            now,
          ],
        );
      }

      const status = plan.blockingCodes.length > 0 ? 'blocked' : 'completed';
      await client.query(
        `UPDATE mymoneymap.legacy_migration_batches
            SET status=$2::varchar,
                checkpoint=$3::jsonb,
                error_code=$4::varchar,
                completed_at=CASE WHEN $2::varchar='completed' THEN $5::timestamptz ELSE NULL END
          WHERE id=$1`,
        [
          batchId,
          status,
          JSON.stringify({ phase: status, transformerVersion: plan.transformerVersion }),
          status === 'blocked' ? 'RECONCILIATION_OR_SCHEMA_BLOCKED' : null,
          now,
        ],
      );
      await client.query('COMMIT');
      return {
        id: batchId,
        status,
        reused: false,
        sourceRowCount: plan.sourceRowCount,
        plannedRowCount: plan.planned.length,
        quarantineRowCount: plan.quarantined.length,
      };
    } catch (error) {
      await client.query('ROLLBACK').catch(() => undefined);
      throw error;
    } finally {
      client.release();
    }
  }
}

async function insertRowLedger(
  client: PoolClient,
  input: {
    id: string;
    batchId: string;
    sourceTable: string;
    sourceKeyHash: string;
    domain: string;
    targetTable: string | null;
    targetId: string | null;
    outcome: 'planned' | 'quarantined' | 'skipped';
    reasonCode: string | null;
    now: Date;
  },
): Promise<void> {
  await client.query(
    `INSERT INTO mymoneymap.legacy_migration_row_ledger
      (id,batch_id,source_table,source_key_hash,target_domain,target_table,target_id,
       outcome,reason_code,created_at)
     VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
     ON CONFLICT(id) DO NOTHING`,
    [
      input.id,
      input.batchId,
      input.sourceTable,
      input.sourceKeyHash,
      input.domain,
      input.targetTable,
      input.targetId,
      input.outcome,
      input.reasonCode,
      input.now,
    ],
  );
}

function deterministicUuid(seed: string): string {
  const digest = createHash('sha256').update(seed).digest('hex');
  return `${digest.slice(0, 8)}-${digest.slice(8, 12)}-4${digest.slice(13, 16)}-8${digest.slice(17, 20)}-${digest.slice(20, 32)}`;
}
