/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import { ApplicationError } from '../platform/http/application-error';

export interface FeedbackRow {
  id: string;
  kind: 'bug' | 'idea';
  title: string;
  message: string;
  severity: 'low' | 'medium' | 'high' | null;
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  created_at: Date;
  updated_at: Date;
  responses: unknown;
}

@Injectable()
export class FeedbackRepository {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async listOwned(input: {
    userId: string;
    limit: number;
    cursor?: string;
    status?: string;
    kind?: string;
  }): Promise<{ items: ReturnType<typeof mapFeedback>[]; nextCursor: string | null }> {
    const cursor = decodeCursor(input.cursor);
    const result = await this.pool.query<FeedbackRow>(
      `SELECT f.id, f.kind, f.title, f.message, f.severity, f.status, f.created_at, f.updated_at,
              COALESCE(
                jsonb_agg(
                  jsonb_build_object(
                    'id', r.id,
                    'message', r.message,
                    'createdAt', r.created_at
                  ) ORDER BY r.created_at, r.id
                ) FILTER (WHERE r.id IS NOT NULL),
                '[]'::jsonb
              ) AS responses
         FROM mymoneymap.feedback f
         LEFT JOIN mymoneymap.feedback_responses r ON r.feedback_id = f.id
        WHERE f.user_id = $1
          AND ($2::varchar IS NULL OR f.status = $2)
          AND ($3::varchar IS NULL OR f.kind = $3)
          AND ($4::timestamptz IS NULL OR (f.created_at, f.id) < ($4, $5::uuid))
        GROUP BY f.id
        ORDER BY f.created_at DESC, f.id DESC
        LIMIT $6`,
      [
        input.userId,
        input.status ?? null,
        input.kind ?? null,
        cursor?.createdAt ?? null,
        cursor?.id ?? null,
        input.limit + 1,
      ],
    );
    return page(result.rows, input.limit);
  }

  async create(input: {
    userId: string;
    kind: 'bug' | 'idea';
    title: string;
    message: string;
    severity: 'low' | 'medium' | 'high' | null;
    now: Date;
  }): Promise<ReturnType<typeof mapFeedback>> {
    const result = await this.pool.query<FeedbackRow>(
      `INSERT INTO mymoneymap.feedback
         (id,user_id,kind,title,message,severity,status,created_at,updated_at)
       VALUES ($1,$2,$3,$4,$5,$6,'open',$7,$7)
       RETURNING id,kind,title,message,severity,status,created_at,updated_at,'[]'::jsonb AS responses`,
      [
        randomUUID(),
        input.userId,
        input.kind,
        input.title.trim(),
        input.message.trim(),
        input.severity,
        input.now,
      ],
    );
    return mapFeedback(result.rows[0]!);
  }

  async updateOwnedStatus(
    userId: string,
    id: string,
    status: 'open' | 'closed',
    now: Date,
  ): Promise<ReturnType<typeof mapFeedback>> {
    const result = await this.pool.query<FeedbackRow>(
      `UPDATE mymoneymap.feedback f
          SET status = $3, updated_at = $4
        WHERE f.id = $1 AND f.user_id = $2
        RETURNING f.id,f.kind,f.title,f.message,f.severity,f.status,f.created_at,f.updated_at,
                  (SELECT COALESCE(
                     jsonb_agg(jsonb_build_object(
                       'id',r.id,'message',r.message,'createdAt',r.created_at
                     ) ORDER BY r.created_at,r.id),
                     '[]'::jsonb
                   ) FROM mymoneymap.feedback_responses r WHERE r.feedback_id=f.id) AS responses`,
      [id, userId, status, now],
    );
    if (!result.rows[0]) throw notFound();
    return mapFeedback(result.rows[0]);
  }

  async deleteOwned(userId: string, id: string): Promise<void> {
    const result = await this.pool.query(
      'DELETE FROM mymoneymap.feedback WHERE id = $1 AND user_id = $2',
      [id, userId],
    );
    if (result.rowCount !== 1) throw notFound();
  }
}

function page(rows: FeedbackRow[], limit: number) {
  const hasMore = rows.length > limit;
  const items = rows.slice(0, limit).map(mapFeedback);
  const last = rows[limit - 1];
  return {
    items,
    nextCursor: hasMore && last ? encodeCursor(last.created_at, last.id) : null,
  };
}

function mapFeedback(row: FeedbackRow) {
  return {
    id: row.id,
    kind: row.kind,
    title: row.title,
    message: row.message,
    severity: row.severity,
    status: row.status,
    responses: Array.isArray(row.responses) ? row.responses : [],
    createdAt: row.created_at.toISOString(),
    updatedAt: row.updated_at.toISOString(),
  };
}

function encodeCursor(createdAt: Date, id: string): string {
  return Buffer.from(JSON.stringify({ createdAt: createdAt.toISOString(), id })).toString(
    'base64url',
  );
}

function decodeCursor(value?: string): { createdAt: string; id: string } | null {
  if (!value) return null;
  try {
    const parsed = JSON.parse(Buffer.from(value, 'base64url').toString('utf8')) as {
      createdAt?: unknown;
      id?: unknown;
    };
    if (
      typeof parsed.createdAt !== 'string' ||
      !Number.isFinite(Date.parse(parsed.createdAt)) ||
      typeof parsed.id !== 'string' ||
      !/^[0-9a-f-]{36}$/i.test(parsed.id)
    ) {
      throw new Error();
    }
    return { createdAt: parsed.createdAt, id: parsed.id };
  } catch {
    throw new ApplicationError(400, 'BAD_REQUEST', 'Pagination cursor is invalid');
  }
}

function notFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'Feedback was not found');
}
