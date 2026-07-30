/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import { ACCOUNT_DELETION_ORDER, EXPORT_DATASETS } from './privacy-manifest';

export interface ExportRequestRow {
  id: string;
  user_id: string;
  manifest_version: number;
  status: string;
  queue_job_id: string | null;
  attempt_count: number;
  max_attempts: number;
  error_code: string | null;
  created_at: Date;
  started_at: Date | null;
  completed_at: Date | null;
  expires_at: Date | null;
}

export interface DeletionRequestRow {
  id: string;
  user_id: string | null;
  status: string;
  attempt_count: number;
  max_attempts: number;
  created_at: Date;
}

@Injectable()
export class PrivacyRepository {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async userForReauthentication(userId: string) {
    const result = await this.pool.query<{
      id: string;
      email: string;
      password_hash: string;
      role: string;
    }>('SELECT id,email,password_hash,role FROM mymoneymap.users WHERE id=$1', [userId]);
    return result.rows[0] ?? null;
  }

  async createExportRequest(input: {
    userId: string;
    manifestVersion: number;
    idempotencyKeyHash: string;
    now: Date;
  }): Promise<ExportRequestRow> {
    const result = await this.pool.query<ExportRequestRow>(
      `INSERT INTO mymoneymap.privacy_export_requests
       (id,user_id,manifest_version,idempotency_key_hash,status,attempt_count,max_attempts,created_at)
       VALUES($1,$2,$3,$4,'queued',0,3,$5)
       ON CONFLICT(user_id,idempotency_key_hash)
       DO UPDATE SET idempotency_key_hash=EXCLUDED.idempotency_key_hash
       RETURNING *`,
      [randomUUID(), input.userId, input.manifestVersion, input.idempotencyKeyHash, input.now],
    );
    return required(result.rows[0], 'privacy_export_request_insert_failed');
  }

  async markExportQueued(id: string, queueJobId: string): Promise<void> {
    await this.pool.query(
      `UPDATE mymoneymap.privacy_export_requests
       SET queue_job_id=$2 WHERE id=$1 AND status='queued'`,
      [id, queueJobId],
    );
  }

  async exportRequest(id: string): Promise<ExportRequestRow | null> {
    const result = await this.pool.query<ExportRequestRow>(
      'SELECT * FROM mymoneymap.privacy_export_requests WHERE id=$1',
      [id],
    );
    return result.rows[0] ?? null;
  }

  async ownedExportRequest(userId: string, id: string): Promise<ExportRequestRow | null> {
    const result = await this.pool.query<ExportRequestRow>(
      `SELECT * FROM mymoneymap.privacy_export_requests WHERE id=$1 AND user_id=$2`,
      [id, userId],
    );
    return result.rows[0] ?? null;
  }

  async claimExport(id: string, attempt: number, now: Date): Promise<ExportRequestRow | null> {
    const result = await this.pool.query<ExportRequestRow>(
      `UPDATE mymoneymap.privacy_export_requests
       SET status='running',attempt_count=$2,started_at=COALESCE(started_at,$3),error_code=NULL
       WHERE id=$1 AND status IN ('queued','retryable_failed')
       RETURNING *`,
      [id, attempt, now],
    );
    return result.rows[0] ?? null;
  }

  async exportData(userId: string): Promise<Record<string, unknown[]>> {
    const datasets: Record<string, unknown[]> = {};
    for (const dataset of EXPORT_DATASETS) {
      const columns = dataset.columns.map(quoteIdentifier).join(',');
      const query = `SELECT ${columns} FROM mymoneymap.${quoteIdentifier(dataset.table)}
                     WHERE ${dataset.ownerWhere}`;
      const result = await this.pool.query(query, [userId]);
      datasets[dataset.key] = result.rows.map(serializeRow);
    }
    return datasets;
  }

  async completeExport(input: {
    id: string;
    artifacts: Array<{
      format: 'json' | 'csv';
      dataset: string;
      objectKey: string;
      mediaType: string;
      byteSize: number;
      sha256: string;
    }>;
    now: Date;
    expiresAt: Date;
  }): Promise<void> {
    await this.transaction(async (client) => {
      const request = await client.query<{ user_id: string }>(
        `SELECT user_id FROM mymoneymap.privacy_export_requests
         WHERE id=$1 AND status='running' FOR UPDATE`,
        [input.id],
      );
      const userId = required(request.rows[0], 'privacy_export_request_not_running').user_id;
      for (const artifact of input.artifacts) {
        await client.query(
          `INSERT INTO mymoneymap.privacy_export_artifacts
           (id,export_request_id,user_id,format,dataset,object_key,media_type,byte_size,sha256,
            created_at,expires_at)
           VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)`,
          [
            randomUUID(),
            input.id,
            userId,
            artifact.format,
            artifact.dataset,
            artifact.objectKey,
            artifact.mediaType,
            artifact.byteSize,
            artifact.sha256,
            input.now,
            input.expiresAt,
          ],
        );
      }
      await client.query(
        `UPDATE mymoneymap.privacy_export_requests
         SET status='completed',completed_at=$2,expires_at=$3,error_code=NULL WHERE id=$1`,
        [input.id, input.now, input.expiresAt],
      );
      await insertAudit(client, {
        actorUserId: userId,
        subjectUserId: userId,
        subjectHash: subjectHash(userId),
        action: 'privacy.export_completed',
        targetType: 'privacy_export',
        targetId: input.id,
        details: { artifactCount: input.artifacts.length },
        now: input.now,
      });
    });
  }

  async failExport(id: string, attempt: number, errorCode: string, now: Date): Promise<void> {
    const terminal = attempt >= 3;
    await this.transaction(async (client) => {
      const request = await client.query<{ user_id: string }>(
        `UPDATE mymoneymap.privacy_export_requests
         SET status=$2,attempt_count=$3,error_code=$4
         WHERE id=$1 RETURNING user_id`,
        [id, terminal ? 'dead_letter' : 'retryable_failed', attempt, errorCode],
      );
      const userId = request.rows[0]?.user_id;
      if (terminal && userId) {
        await insertAudit(client, {
          actorUserId: userId,
          subjectUserId: userId,
          subjectHash: subjectHash(userId),
          action: 'privacy.export_failed',
          targetType: 'privacy_export',
          targetId: id,
          details: { errorCode, attempts: attempt },
          now,
        });
      }
    });
  }

  async exportArtifacts(userId: string, requestId: string) {
    const result = await this.pool.query<{
      id: string;
      format: 'json' | 'csv';
      dataset: string;
      object_key: string;
      media_type: string;
      byte_size: string;
      sha256: string;
      expires_at: Date;
    }>(
      `SELECT id,format,dataset,object_key,media_type,byte_size::text,sha256,expires_at
       FROM mymoneymap.privacy_export_artifacts
       WHERE user_id=$1 AND export_request_id=$2 ORDER BY dataset,format`,
      [userId, requestId],
    );
    return result.rows;
  }

  async recordExportRequested(userId: string, requestId: string, now: Date): Promise<void> {
    await this.pool.query(
      `${auditInsertSql}
       ON CONFLICT DO NOTHING`,
      auditValues({
        actorUserId: userId,
        subjectUserId: userId,
        subjectHash: subjectHash(userId),
        action: 'privacy.export_requested',
        targetType: 'privacy_export',
        targetId: requestId,
        details: {},
        now,
      }),
    );
  }

  async recordExportAccessed(userId: string, requestId: string, now: Date): Promise<void> {
    await this.pool.query(
      auditInsertSql,
      auditValues({
        actorUserId: userId,
        subjectUserId: userId,
        subjectHash: subjectHash(userId),
        action: 'privacy.export_accessed',
        targetType: 'privacy_export',
        targetId: requestId,
        details: {},
        now,
      }),
    );
  }

  async createDeletionRequest(input: {
    userId: string;
    idempotencyKeyHash: string;
    now: Date;
  }): Promise<DeletionRequestRow> {
    return this.transaction(async (client) => {
      const hash = subjectHash(input.userId);
      const result = await client.query<DeletionRequestRow>(
        `INSERT INTO mymoneymap.privacy_deletion_requests
         (id,user_id,subject_hash,idempotency_key_hash,status,attempt_count,max_attempts,created_at)
         VALUES($1,$2,$3,$4,'queued',0,3,$5)
         ON CONFLICT(subject_hash,idempotency_key_hash)
         DO UPDATE SET idempotency_key_hash=EXCLUDED.idempotency_key_hash
         RETURNING id,user_id,status,attempt_count,max_attempts,created_at`,
        [randomUUID(), input.userId, hash, input.idempotencyKeyHash, input.now],
      );
      const request = required(result.rows[0], 'privacy_deletion_request_insert_failed');
      const existingAudit = await client.query(
        `SELECT 1 FROM mymoneymap.security_audit_events
         WHERE action='privacy.deletion_requested' AND target_id=$1`,
        [request.id],
      );
      if ((existingAudit.rowCount ?? 0) === 0) {
        await insertAudit(client, {
          actorUserId: input.userId,
          subjectUserId: input.userId,
          subjectHash: hash,
          action: 'privacy.deletion_requested',
          targetType: 'privacy_deletion',
          targetId: request.id,
          details: {},
          now: input.now,
        });
      }
      return request;
    });
  }

  async markDeletionQueued(id: string, queueJobId: string): Promise<void> {
    await this.pool.query(
      `UPDATE mymoneymap.privacy_deletion_requests
       SET queue_job_id=$2 WHERE id=$1 AND status='queued'`,
      [id, queueJobId],
    );
  }

  async deletionRequest(id: string) {
    const result = await this.pool.query<{
      id: string;
      user_id: string | null;
      subject_hash: string;
      status: string;
      attempt_count: number;
      max_attempts: number;
    }>('SELECT * FROM mymoneymap.privacy_deletion_requests WHERE id=$1', [id]);
    return result.rows[0] ?? null;
  }

  async claimDeletion(id: string, attempt: number, now: Date) {
    const result = await this.pool.query<{
      id: string;
      user_id: string | null;
      subject_hash: string;
      status: string;
    }>(
      `UPDATE mymoneymap.privacy_deletion_requests
       SET status='running',attempt_count=$2,started_at=COALESCE(started_at,$3),error_code=NULL
       WHERE id=$1 AND status IN ('queued','retryable_failed') AND user_id IS NOT NULL
       RETURNING id,user_id,subject_hash,status`,
      [id, attempt, now],
    );
    return result.rows[0] ?? null;
  }

  async cleanupInventory(userId: string) {
    const [exports, exportJobs, emailJobs, securitiesJobs, user] = await Promise.all([
      this.pool.query<{ object_key: string }>(
        'SELECT object_key FROM mymoneymap.privacy_export_artifacts WHERE user_id=$1',
        [userId],
      ),
      this.pool.query<{ queue_job_id: string }>(
        `SELECT queue_job_id FROM mymoneymap.privacy_export_requests
         WHERE user_id=$1 AND queue_job_id IS NOT NULL`,
        [userId],
      ),
      this.pool.query<{ queue_job_id: string }>(
        `SELECT queue_job_id FROM mymoneymap.email_deliveries
         WHERE user_id=$1 AND queue_job_id IS NOT NULL`,
        [userId],
      ),
      this.pool.query<{ queue_job_id: string }>(
        `SELECT queue_job_id FROM mymoneymap.securities_refresh_jobs
         WHERE user_id=$1 AND queue_job_id IS NOT NULL`,
        [userId],
      ),
      this.pool.query<{ email: string }>('SELECT email FROM mymoneymap.users WHERE id=$1', [
        userId,
      ]),
    ]);
    return {
      objectKeys: exports.rows.map(({ object_key }) => object_key),
      exportQueueJobIds: exportJobs.rows.map(({ queue_job_id }) => queue_job_id),
      emailQueueJobIds: emailJobs.rows.map(({ queue_job_id }) => queue_job_id),
      securitiesQueueJobIds: securitiesJobs.rows.map(({ queue_job_id }) => queue_job_id),
      email: required(user.rows[0], 'privacy_deletion_user_missing').email,
    };
  }

  async deleteAccountDataAndComplete(
    userId: string,
    requestId: string,
    subjectHashValue: string,
    now: Date,
  ): Promise<void> {
    await this.transaction(async (client) => {
      const locked = await client.query(
        `SELECT id FROM mymoneymap.users WHERE id=$1 AND role<>'admin' FOR UPDATE`,
        [userId],
      );
      required(locked.rows[0], 'privacy_deletion_user_missing');
      for (const table of ACCOUNT_DELETION_ORDER) {
        const ownerColumn = table === 'idempotency_keys' ? 'scope_id' : 'user_id';
        await client.query(
          `DELETE FROM mymoneymap.${quoteIdentifier(table)}
           WHERE ${quoteIdentifier(ownerColumn)}=$1`,
          [userId],
        );
      }
      await client.query('DELETE FROM mymoneymap.users WHERE id=$1', [userId]);
      await client.query(
        `UPDATE mymoneymap.privacy_deletion_requests
         SET status='completed',completed_at=$2,error_code=NULL WHERE id=$1`,
        [requestId, now],
      );
      await insertAudit(client, {
        actorUserId: null,
        subjectUserId: null,
        subjectHash: subjectHashValue,
        action: 'privacy.deletion_completed',
        targetType: 'privacy_deletion',
        targetId: requestId,
        details: {},
        now,
      });
    });
  }

  async failDeletion(
    id: string,
    subjectHashValue: string,
    attempt: number,
    errorCode: string,
    now: Date,
  ): Promise<void> {
    const terminal = attempt >= 3;
    await this.transaction(async (client) => {
      await client.query(
        `UPDATE mymoneymap.privacy_deletion_requests
         SET status=$2,attempt_count=$3,error_code=$4 WHERE id=$1`,
        [id, terminal ? 'dead_letter' : 'retryable_failed', attempt, errorCode],
      );
      if (terminal) {
        await insertAudit(client, {
          actorUserId: null,
          subjectUserId: null,
          subjectHash: subjectHashValue,
          action: 'privacy.deletion_failed',
          targetType: 'privacy_deletion',
          targetId: id,
          details: { errorCode, attempts: attempt },
          now,
        });
      }
    });
  }

  private async transaction<T>(work: (client: PoolClient) => Promise<T>): Promise<T> {
    const client = await this.pool.connect();
    try {
      await client.query('BEGIN');
      const result = await work(client);
      await client.query('COMMIT');
      return result;
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }
}

interface AuditInput {
  actorUserId: string | null;
  subjectUserId: string | null;
  subjectHash: string;
  action: string;
  targetType: string;
  targetId: string | null;
  details: Record<string, unknown>;
  now: Date;
}

const auditInsertSql = `INSERT INTO mymoneymap.security_audit_events
  (id,actor_user_id,subject_user_id,subject_hash,action,target_type,target_id,details,created_at)
  VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)`;

function insertAudit(client: PoolClient, input: AuditInput): Promise<unknown> {
  return client.query(auditInsertSql, auditValues(input));
}

function auditValues(input: AuditInput): unknown[] {
  return [
    randomUUID(),
    input.actorUserId,
    input.subjectUserId,
    input.subjectHash,
    input.action,
    input.targetType,
    input.targetId,
    JSON.stringify(input.details),
    input.now,
  ];
}

function subjectHash(userId: string): string {
  return createHash('sha256').update(userId).digest('hex');
}

function quoteIdentifier(value: string): string {
  if (!/^[a-z][a-z0-9_]*$/.test(value)) throw new Error('unsafe_privacy_manifest_identifier');
  return `"${value}"`;
}

function serializeRow(row: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(row).map(([key, value]) => [
      key,
      value instanceof Date ? value.toISOString() : value,
    ]),
  );
}

function required<T>(value: T | undefined, code: string): T {
  if (value === undefined) throw new Error(code);
  return value;
}
