/* eslint-disable @typescript-eslint/explicit-function-return-type,@typescript-eslint/no-unsafe-return */
import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Pool } from 'pg';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import type { EmailClassification, EmailLocale } from './email-template.catalog';

@Injectable()
export class NotificationsRepository {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async preference(userId: string) {
    const result = await this.pool.query<{ educational_enabled: boolean }>(
      `SELECT educational_enabled FROM mymoneymap.user_email_preferences WHERE user_id=$1`,
      [userId],
    );
    return { educationalEnabled: result.rows[0]?.educational_enabled ?? true };
  }

  async setPreference(userId: string, educationalEnabled: boolean, now: Date) {
    await this.pool.query(
      `INSERT INTO mymoneymap.user_email_preferences(user_id,educational_enabled,updated_at)
       VALUES($1,$2,$3) ON CONFLICT(user_id) DO UPDATE
       SET educational_enabled=EXCLUDED.educational_enabled,updated_at=EXCLUDED.updated_at`,
      [userId, educationalEnabled, now],
    );
    return { educationalEnabled };
  }

  async userForEmail(email: string) {
    const result = await this.pool.query<{
      id: string;
      email: string;
      full_name: string;
      desired_language: string;
    }>(
      `SELECT id,email,full_name,desired_language FROM mymoneymap.users
       WHERE lower(email)=lower($1) AND status='active'`,
      [email],
    );
    return result.rows[0] ?? null;
  }

  async userForId(userId: string) {
    const result = await this.pool.query<{
      id: string;
      email: string;
      full_name: string;
      desired_language: string;
    }>(
      `SELECT id,email,full_name,desired_language FROM mymoneymap.users
       WHERE id=$1 AND status='active' AND email_verified_at IS NOT NULL`,
      [userId],
    );
    return result.rows[0] ?? null;
  }

  async verifiedRecipients() {
    const result = await this.pool.query<{ id: string }>(
      `SELECT id FROM mymoneymap.users
       WHERE status='active' AND role<>'admin' AND email_verified_at IS NOT NULL
       ORDER BY id`,
    );
    return result.rows;
  }

  async feedbackRecipient(): Promise<string | null> {
    const result = await this.pool.query<{ recipient: string | null }>(
      `SELECT COALESCE(NULLIF(btrim(support_email),''),NULLIF(btrim(contact_email),'')) AS recipient
       FROM mymoneymap.system_settings WHERE id=1`,
    );
    return result.rows[0]?.recipient ?? null;
  }

  async createDelivery(input: {
    eventKey: string;
    correlationId: string;
    userId: string | null;
    recipientEmail: string;
    templateCode: string;
    locale: EmailLocale;
    classification: EmailClassification;
    data: Record<string, string>;
    provenance: Record<string, string>;
    status: string;
    now: Date;
  }) {
    const id = randomUUID();
    const result = await this.pool.query<{
      id: string;
      status: string;
      queue_job_id: string | null;
    }>(
      `INSERT INTO mymoneymap.email_deliveries
       (id,event_key,correlation_id,user_id,recipient_email,template_code,template_version,locale,
        classification,template_data,provenance,status,attempt_count,max_attempts,created_at)
       VALUES($1,$2,$3,$4,$5,$6,1,$7,$8,$9,$10,$11,0,3,$12)
       ON CONFLICT(event_key) DO UPDATE SET event_key=EXCLUDED.event_key
       RETURNING id,status,queue_job_id`,
      [
        id,
        input.eventKey,
        input.correlationId,
        input.userId,
        input.recipientEmail,
        input.templateCode,
        input.locale,
        input.classification,
        JSON.stringify(input.data),
        JSON.stringify(input.provenance),
        input.status,
        input.now,
      ],
    );
    const row = result.rows[0];
    if (!row) throw new Error('email_delivery_insert_failed');
    return row;
  }

  async markQueued(id: string, queueJobId: string, now: Date): Promise<void> {
    await this.pool.query(
      `UPDATE mymoneymap.email_deliveries SET status='queued',queue_job_id=$2,queued_at=$3
       WHERE id=$1 AND status IN ('queued','disabled')`,
      [id, queueJobId, now],
    );
  }

  async delivery(id: string) {
    const result = await this.pool.query<{
      id: string;
      correlation_id: string;
      recipient_email: string;
      template_code: string;
      locale: EmailLocale;
      template_data: Record<string, string>;
      status: string;
      max_attempts: number;
    }>(`SELECT * FROM mymoneymap.email_deliveries WHERE id=$1`, [id]);
    return result.rows[0] ?? null;
  }

  async updateAttempt(
    id: string,
    input: {
      status: string;
      attempt: number;
      errorCode?: string | null;
      providerMessageId?: string;
      now: Date;
    },
  ): Promise<void> {
    await this.pool.query(
      `UPDATE mymoneymap.email_deliveries
       SET status=$2,attempt_count=$3,error_code=$4,
           provider_message_id=COALESCE($5,provider_message_id),
           started_at=COALESCE(started_at,$6),
           delivered_at=CASE WHEN $2='delivered' THEN $6 ELSE delivered_at END,
           failed_at=CASE WHEN $2 IN ('dead_letter','suppressed') THEN $6 ELSE failed_at END
       WHERE id=$1`,
      [
        id,
        input.status,
        input.attempt,
        input.errorCode ?? null,
        input.providerMessageId ?? null,
        input.now,
      ],
    );
  }

  async isSuppressed(email: string): Promise<boolean> {
    const hash = createHash('sha256').update(email.trim().toLowerCase()).digest('hex');
    const result = await this.pool.query(
      `SELECT 1 FROM mymoneymap.email_suppressions WHERE email_hash=$1`,
      [hash],
    );
    return (result.rowCount ?? 0) > 0;
  }

  async templates() {
    const result = await this.pool.query(
      `SELECT code,version,locale,name,subject,data_contract,active,last_tested_at,updated_at
       FROM mymoneymap.email_templates ORDER BY code,locale,version DESC`,
    );
    return result.rows;
  }

  async channel() {
    const result = await this.pool.query(
      `SELECT enabled,provider,from_address,reply_to_address,updated_at
       FROM mymoneymap.email_channel_settings WHERE id=1`,
    );
    return result.rows[0] ?? { enabled: false, provider: 'disabled' };
  }

  async updateChannel(input: {
    enabled: boolean;
    provider: string;
    fromAddress?: string;
    replyToAddress?: string;
    actorId: string;
    now: Date;
  }) {
    const result = await this.pool.query(
      `INSERT INTO mymoneymap.email_channel_settings
       (id,enabled,provider,from_address,reply_to_address,updated_by,created_at,updated_at)
       VALUES(1,$1,$2,$3,$4,$5,$6,$6)
       ON CONFLICT(id) DO UPDATE SET enabled=EXCLUDED.enabled,provider=EXCLUDED.provider,
       from_address=EXCLUDED.from_address,reply_to_address=EXCLUDED.reply_to_address,
       updated_by=EXCLUDED.updated_by,updated_at=EXCLUDED.updated_at
       RETURNING enabled,provider,from_address,reply_to_address,updated_at`,
      [
        input.enabled,
        input.provider,
        input.fromAddress ?? null,
        input.replyToAddress ?? null,
        input.actorId,
        input.now,
      ],
    );
    return result.rows[0];
  }
}
