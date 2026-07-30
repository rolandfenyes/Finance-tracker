import { Inject, Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash } from 'node:crypto';
import { ApplicationError } from '../platform/http/application-error';
import { CLOCK, type Clock } from '../platform/time/clock';
import { PRIVATE_OBJECT_STORAGE, type PrivateObjectStorage } from './private-object-storage';
import { EXPORT_DATASETS, PRIVACY_MANIFEST_VERSION } from './privacy-manifest';
import { type ExportRequestRow, PrivacyRepository } from './privacy.repository';

export interface PreparedArtifact {
  format: 'json' | 'csv';
  dataset: string;
  mediaType: string;
  body: Uint8Array;
}

export interface PrivacyExportStatus {
  id: string;
  manifestVersion: number;
  status: string;
  attemptCount: number;
  maxAttempts: number;
  errorCode: string | null;
  createdAt: string;
  startedAt: string | null;
  completedAt: string | null;
  expiresAt: string | null;
  artifacts: Array<Record<string, unknown>>;
}

@Injectable()
export class PrivacyExportBuilder {
  build(generatedAt: Date, datasets: Record<string, unknown[]>): PreparedArtifact[] {
    const normalized = Object.fromEntries(
      Object.entries(datasets)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, rows]) => [key, [...rows].sort(compareRows)]),
    );
    const artifacts: PreparedArtifact[] = [
      {
        format: 'json',
        dataset: 'complete_export',
        mediaType: 'application/json',
        body: Buffer.from(
          JSON.stringify(
            {
              schema: 'mymoneymap.account-export',
              manifestVersion: PRIVACY_MANIFEST_VERSION,
              generatedAt: generatedAt.toISOString(),
              datasets: normalized,
            },
            null,
            2,
          ),
          'utf8',
        ),
      },
    ];
    for (const definition of EXPORT_DATASETS.filter(({ csv }) => csv)) {
      const rows = normalized[definition.key] ?? [];
      artifacts.push({
        format: 'csv',
        dataset: definition.key,
        mediaType: 'text/csv; charset=utf-8',
        body: Buffer.from(toCsv(definition.columns, rows), 'utf8'),
      });
    }
    return artifacts;
  }
}

@Injectable()
export class PrivacyExportProcessor {
  private readonly logger = new Logger(PrivacyExportProcessor.name);

  constructor(
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PrivacyExportBuilder) private readonly builder: PrivacyExportBuilder,
    @Inject(PRIVATE_OBJECT_STORAGE) private readonly storage: PrivateObjectStorage,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async process(requestId: string, attempt: number): Promise<void> {
    const now = this.clock.now().toDate();
    const request = await this.repository.claimExport(requestId, attempt, now);
    if (!request) return;
    const uploaded: string[] = [];
    try {
      const expirySeconds = this.config.get<number>('PRIVACY_EXPORT_EXPIRY_SECONDS');
      if (expirySeconds === undefined) throw new Error('privacy_export_expiry_not_configured');
      const expiresAt = new Date(now.getTime() + expirySeconds * 1000);
      const datasets = await this.repository.exportData(request.user_id);
      const prepared = this.builder.build(now, datasets);
      const artifacts = [];
      for (const artifact of prepared) {
        const storageKey = exportObjectKey(request.user_id, request.id, artifact);
        await this.storage.put({
          key: storageKey,
          body: artifact.body,
          mediaType: artifact.mediaType,
          expiresAt,
        });
        uploaded.push(storageKey);
        artifacts.push({
          format: artifact.format,
          dataset: artifact.dataset,
          objectKey: storageKey,
          mediaType: artifact.mediaType,
          byteSize: artifact.body.byteLength,
          sha256: createHash('sha256').update(artifact.body).digest('hex'),
        });
      }
      await this.repository.completeExport({
        id: request.id,
        artifacts,
        now,
        expiresAt,
      });
      this.logger.log(
        {
          requestId,
          status: 'completed',
          artifactCount: artifacts.length,
          manifestVersion: request.manifest_version,
        },
        'Privacy export state changed',
      );
    } catch (error) {
      await Promise.allSettled(uploaded.map((key) => this.storage.delete(key)));
      const errorCode = exportErrorCode(error);
      await this.repository.failExport(request.id, attempt, errorCode, this.clock.now().toDate());
      this.logger.warn(
        {
          requestId,
          status: attempt >= 3 ? 'dead_letter' : 'retryable_failed',
          errorCode,
          attempt,
        },
        'Privacy export state changed',
      );
      throw error;
    }
  }
}

@Injectable()
export class PrivacyExportService {
  constructor(
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(PrivacyRepository) private readonly repository: PrivacyRepository,
    @Inject(PRIVATE_OBJECT_STORAGE) private readonly storage: PrivateObjectStorage,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async prepare(userId: string, idempotencyKey: string): Promise<ExportRequestRow> {
    if (!this.config.getOrThrow<boolean>('PRIVACY_EXPORTS_ENABLED')) {
      throw new ApplicationError(
        503,
        'SERVICE_UNAVAILABLE',
        'Private account exports are not enabled',
      );
    }
    const now = this.clock.now().toDate();
    const request = await this.repository.createExportRequest({
      userId,
      manifestVersion: PRIVACY_MANIFEST_VERSION,
      idempotencyKeyHash: hashIdempotencyKey(idempotencyKey),
      now,
    });
    await this.repository.recordExportRequested(userId, request.id, now);
    return request;
  }

  async status(userId: string, requestId: string): Promise<PrivacyExportStatus> {
    const request = await this.repository.ownedExportRequest(userId, requestId);
    if (!request) throw new ApplicationError(404, 'NOT_FOUND', 'Export request was not found');
    const result = {
      id: request.id,
      manifestVersion: request.manifest_version,
      status:
        request.status === 'completed' &&
        request.expires_at !== null &&
        request.expires_at <= this.clock.now().toDate()
          ? 'expired'
          : request.status,
      attemptCount: request.attempt_count,
      maxAttempts: request.max_attempts,
      errorCode: request.error_code,
      createdAt: request.created_at.toISOString(),
      startedAt: request.started_at?.toISOString() ?? null,
      completedAt: request.completed_at?.toISOString() ?? null,
      expiresAt: request.expires_at?.toISOString() ?? null,
      artifacts: [] as Array<Record<string, unknown>>,
    };
    if (result.status !== 'completed') return result;
    const signedSeconds = this.config.get<number>('PRIVACY_EXPORT_SIGNED_URL_SECONDS');
    if (signedSeconds === undefined) {
      throw new ApplicationError(
        503,
        'SERVICE_NOT_READY',
        'Signed export access is not configured',
      );
    }
    const artifacts = await this.repository.exportArtifacts(userId, requestId);
    result.artifacts = await Promise.all(
      artifacts.map(async (artifact) => ({
        id: artifact.id,
        format: artifact.format,
        dataset: artifact.dataset,
        mediaType: artifact.media_type,
        byteSize: artifact.byte_size,
        sha256: artifact.sha256,
        expiresAt: artifact.expires_at.toISOString(),
        downloadUrl: await this.storage.signedGetUrl(artifact.object_key, signedSeconds),
        downloadUrlExpiresInSeconds: signedSeconds,
      })),
    );
    await this.repository.recordExportAccessed(userId, requestId, this.clock.now().toDate());
    return result;
  }
}

export function hashIdempotencyKey(value: string): string {
  const normalized = value.trim();
  if (normalized.length < 8 || normalized.length > 200) {
    throw new ApplicationError(
      400,
      'BAD_REQUEST',
      'Idempotency-Key must contain between 8 and 200 characters',
    );
  }
  return createHash('sha256').update(normalized).digest('hex');
}

function exportObjectKey(userId: string, requestId: string, artifact: PreparedArtifact): string {
  const subject = createHash('sha256').update(userId).digest('hex');
  return `privacy-exports/${subject}/${requestId}/${artifact.dataset}.${artifact.format}`;
}

function compareRows(left: unknown, right: unknown): number {
  return JSON.stringify(left).localeCompare(JSON.stringify(right));
}

function toCsv(columns: readonly string[], rows: unknown[]): string {
  const header = columns.map(csvCell).join(',');
  const body = rows.map((row) => {
    const record = row as Record<string, unknown>;
    return columns.map((column) => csvCell(record[column])).join(',');
  });
  return `\uFEFF${[header, ...body].join('\r\n')}\r\n`;
}

function csvCell(value: unknown): string {
  let text: string;
  if (value === null || value === undefined) text = '';
  else if (typeof value === 'object') text = JSON.stringify(value);
  else if (
    typeof value === 'string' ||
    typeof value === 'number' ||
    typeof value === 'boolean' ||
    typeof value === 'bigint'
  )
    text = String(value);
  else text = '';
  return `"${text.replaceAll('"', '""')}"`;
}

function exportErrorCode(error: unknown): string {
  if (error instanceof Error && error.message.includes('disabled')) return 'storage_disabled';
  if (error instanceof Error && error.name === 'TimeoutError') return 'storage_timeout';
  return 'export_generation_failed';
}
