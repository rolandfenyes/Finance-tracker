/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { randomBytes, randomUUID } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import { hash, normalizeEmail } from '../identity/identity.service';
import { SessionService } from '../identity/session.service';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import {
  ENCRYPTED_SETTING_PORT,
  type EncryptedSettingPort,
} from '../platform/security/encrypted-setting.port';
import { REDACTED_SECRET, SecretValue } from '../platform/security/secret-value';
import { ApplicationError } from '../platform/http/application-error';
import type {
  AdminFeedbackQueryDto,
  AdminUsersQueryDto,
  EmailChangeRequestDto,
  PutIntegrationDto,
  UpdateAdminFeedbackDto,
  UpdateSystemSettingsDto,
} from './administration.dto';
import { RECOVERY_NOTIFIER, type RecoveryNotifier } from './recovery-notifier';

interface UserRow {
  id: string;
  email: string;
  full_name: string;
  role: 'free' | 'premium' | 'admin';
  status: 'active' | 'inactive';
  email_verified_at: Date | null;
  created_at: Date;
}

interface FeedbackAdminRow {
  id: string;
  user_id: string;
  email: string;
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
export class AdministrationService {
  constructor(
    @Inject(POSTGRES_POOL) private readonly pool: Pool,
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(SessionService) private readonly sessions: SessionService,
    @Inject(ENCRYPTED_SETTING_PORT) private readonly encryptedSettings: EncryptedSettingPort,
    @Inject(RECOVERY_NOTIFIER) private readonly notifier: RecoveryNotifier,
  ) {}

  async dashboard() {
    const [counts, recent] = await Promise.all([
      this.pool.query<{
        users: string;
        journal_entries: string;
        goals: string;
        loans: string;
      }>(
        `SELECT
           (SELECT count(*) FROM mymoneymap.users)::text AS users,
           (SELECT count(*) FROM mymoneymap.journal_entries)::text AS journal_entries,
           (SELECT count(*) FROM mymoneymap.goals)::text AS goals,
           (SELECT count(*) FROM mymoneymap.loans)::text AS loans`,
      ),
      this.pool.query<UserRow>(
        `SELECT id,email,full_name,role,status,email_verified_at,created_at
           FROM mymoneymap.users
          ORDER BY created_at DESC,id DESC LIMIT 5`,
      ),
    ]);
    const row = counts.rows[0]!;
    return {
      metrics: {
        users: Number(row.users),
        postedJournalEntries: Number(row.journal_entries),
        goals: Number(row.goals),
        loans: Number(row.loans),
      },
      definitions: {
        users: 'All persisted user accounts, regardless of status.',
        postedJournalEntries: 'Immutable journal entries currently persisted.',
        goals: 'All persisted goals, including archived goals.',
        loans: 'All persisted loans, including completed or archived loans.',
      },
      recentUsers: recent.rows.map(mapAdminUser),
    };
  }

  async analytics() {
    const [summary, registrations] = await Promise.all([
      this.pool.query<{
        total: string;
        active: string;
        inactive: string;
        free: string;
        premium: string;
        admin: string;
        verified: string;
      }>(
        `SELECT
           count(*)::text AS total,
           count(*) FILTER (WHERE status='active')::text AS active,
           count(*) FILTER (WHERE status='inactive')::text AS inactive,
           count(*) FILTER (WHERE role='free')::text AS free,
           count(*) FILTER (WHERE role='premium')::text AS premium,
           count(*) FILTER (WHERE role='admin')::text AS admin,
           count(*) FILTER (WHERE email_verified_at IS NOT NULL)::text AS verified
         FROM mymoneymap.users`,
      ),
      this.pool.query<{ day: string; count: string }>(
        `SELECT d.day::date::text AS day, count(u.id)::text AS count
           FROM generate_series(
             current_date - interval '29 days',
             current_date,
             interval '1 day'
           ) d(day)
           LEFT JOIN mymoneymap.users u ON u.created_at::date = d.day::date
          GROUP BY d.day
          ORDER BY d.day`,
      ),
    ]);
    const row = summary.rows[0]!;
    return {
      users: Object.fromEntries(Object.entries(row).map(([key, value]) => [key, Number(value)])),
      registrationsByUtcDay: registrations.rows.map((item) => ({
        day: item.day,
        count: Number(item.count),
      })),
      definitions: {
        total: 'All persisted user accounts.',
        active: 'Accounts whose current status is active.',
        inactive: 'Accounts whose current status is inactive.',
        free: 'Accounts whose current fixed role is free.',
        premium: 'Accounts whose current fixed role is premium.',
        admin: 'Accounts whose current fixed role is admin.',
        verified: 'Accounts with a non-null email verification timestamp.',
        registrationsByUtcDay:
          'Accounts created on each UTC calendar day in the trailing 30-day window.',
      },
    };
  }

  async listUsers(query: AdminUsersQueryDto) {
    const cursor = decodeCursor(query.cursor);
    const result = await this.pool.query<UserRow>(
      `SELECT id,email,full_name,role,status,email_verified_at,created_at
         FROM mymoneymap.users
        WHERE ($1::varchar IS NULL OR email ILIKE '%' || $1 || '%' OR full_name ILIKE '%' || $1 || '%')
          AND ($2::varchar IS NULL OR role=$2)
          AND ($3::varchar IS NULL OR status=$3)
          AND (
            $4::boolean IS NULL
            OR ($4 AND email_verified_at IS NOT NULL)
            OR (NOT $4 AND email_verified_at IS NULL)
          )
          AND ($5::timestamptz IS NULL OR (created_at,id) < ($5,$6::uuid))
        ORDER BY created_at DESC,id DESC LIMIT $7`,
      [
        query.q?.trim() ?? null,
        query.role ?? null,
        query.status ?? null,
        query.verified === undefined ? null : query.verified === 'true',
        cursor?.createdAt ?? null,
        cursor?.id ?? null,
        query.limit + 1,
      ],
    );
    return page(result.rows, query.limit, mapAdminUser);
  }

  async userDetail(id: string) {
    const [user, feedback, login] = await Promise.all([
      this.pool.query<UserRow>(
        `SELECT id,email,full_name,role,status,email_verified_at,created_at
           FROM mymoneymap.users WHERE id=$1`,
        [id],
      ),
      this.pool.query<{ status: string; count: string }>(
        `SELECT status,count(*)::text AS count
           FROM mymoneymap.feedback WHERE user_id=$1 GROUP BY status`,
        [id],
      ),
      this.pool.query<{ outcome: string; method: string; created_at: Date }>(
        `SELECT outcome,method,created_at
           FROM mymoneymap.login_audit_events
          WHERE user_id=$1 ORDER BY created_at DESC,id DESC LIMIT 20`,
        [id],
      ),
    ]);
    if (!user.rows[0]) throw userNotFound();
    return {
      ...mapAdminUser(user.rows[0]),
      feedbackByStatus: Object.fromEntries(
        feedback.rows.map((row) => [row.status, Number(row.count)]),
      ),
      loginActivity: login.rows.map((row) => ({
        outcome: row.outcome,
        method: row.method,
        createdAt: row.created_at.toISOString(),
      })),
    };
  }

  async updateRole(actorUserId: string, userId: string, role: 'free' | 'premium' | 'admin') {
    return this.transaction(async (client) => {
      const before = await lockUser(client, userId);
      const result = await client.query<UserRow>(
        `UPDATE mymoneymap.users SET role=$2,updated_at=now()
          WHERE id=$1
          RETURNING id,email,full_name,role,status,email_verified_at,created_at`,
        [userId, role],
      );
      await audit(client, actorUserId, 'user.role_updated', 'user', userId, {
        from: before.role,
        to: role,
      });
      return mapAdminUser(result.rows[0]!);
    });
  }

  async updateStatus(actorUserId: string, userId: string, status: 'active' | 'inactive') {
    if (actorUserId === userId && status === 'inactive') {
      throw new ApplicationError(409, 'CONFLICT', 'Administrators cannot deactivate themselves');
    }
    const user = await this.transaction(async (client) => {
      const before = await lockUser(client, userId);
      const result = await client.query<UserRow>(
        `UPDATE mymoneymap.users SET status=$2,updated_at=now()
          WHERE id=$1
          RETURNING id,email,full_name,role,status,email_verified_at,created_at`,
        [userId, status],
      );
      await audit(client, actorUserId, 'user.status_updated', 'user', userId, {
        from: before.status,
        to: status,
      });
      return mapAdminUser(result.rows[0]!);
    });
    if (status === 'inactive') await this.sessions.revokeAllForUser(userId);
    return user;
  }

  async requestPasswordReset(actorUserId: string, userId: string) {
    const issued = await this.createRecovery(actorUserId, userId, 'password_reset', null);
    await this.notifier.sendPasswordReset(issued);
    return acceptedRecovery(issued.expiresAt);
  }

  async requestVerification(actorUserId: string, userId: string) {
    const token = randomBytes(32).toString('base64url');
    const now = new Date();
    const expiresAt = new Date(
      now.getTime() + this.config.getOrThrow<number>('EMAIL_VERIFICATION_TTL_SECONDS') * 1000,
    );
    const user = await this.transaction(async (client) => {
      const target = await lockUser(client, userId);
      if (target.email_verified_at) {
        throw new ApplicationError(409, 'CONFLICT', 'Email is already verified');
      }
      await client.query(
        `UPDATE mymoneymap.email_verification_tokens SET consumed_at=$2
          WHERE user_id=$1 AND consumed_at IS NULL`,
        [userId, now],
      );
      await client.query(
        `INSERT INTO mymoneymap.email_verification_tokens
           (id,user_id,token_hash,expires_at,created_at)
         VALUES ($1,$2,$3,$4,$5)`,
        [randomUUID(), userId, hash(token), expiresAt, now],
      );
      await audit(client, actorUserId, 'user.email_verification_requested', 'user', userId, {});
      return target;
    });
    await this.notifier.sendEmailVerification({
      email: user.email,
      fullName: user.full_name,
      token,
    });
    return acceptedRecovery(expiresAt);
  }

  async requestEmailChange(actorUserId: string, userId: string, dto: EmailChangeRequestDto) {
    const pendingEmail = normalizeEmail(dto.email);
    const issued = await this.createRecovery(actorUserId, userId, 'email_change', pendingEmail);
    await this.notifier.sendEmailChange({ ...issued, pendingEmail });
    return acceptedRecovery(issued.expiresAt);
  }

  async listFeedback(query: AdminFeedbackQueryDto) {
    const cursor = decodeCursor(query.cursor);
    const result = await this.pool.query<FeedbackAdminRow>(
      `SELECT f.id,f.user_id,u.email,f.kind,f.title,f.message,f.severity,f.status,
              f.created_at,f.updated_at,
              COALESCE(
                jsonb_agg(jsonb_build_object(
                  'id',r.id,'adminId',r.admin_id,'message',r.message,'createdAt',r.created_at
                ) ORDER BY r.created_at,r.id) FILTER (WHERE r.id IS NOT NULL),
                '[]'::jsonb
              ) AS responses
         FROM mymoneymap.feedback f
         JOIN mymoneymap.users u ON u.id=f.user_id
         LEFT JOIN mymoneymap.feedback_responses r ON r.feedback_id=f.id
        WHERE ($1::varchar IS NULL OR f.title ILIKE '%'||$1||'%' OR f.message ILIKE '%'||$1||'%')
          AND ($2::varchar IS NULL OR f.kind=$2)
          AND ($3::varchar IS NULL OR f.severity=$3)
          AND ($4::varchar IS NULL OR f.status=$4)
          AND ($5::timestamptz IS NULL OR (f.created_at,f.id) < ($5,$6::uuid))
        GROUP BY f.id,u.email
        ORDER BY f.created_at DESC,f.id DESC LIMIT $7`,
      [
        query.q?.trim() ?? null,
        query.kind ?? null,
        query.severity ?? null,
        query.status ?? null,
        cursor?.createdAt ?? null,
        cursor?.id ?? null,
        query.limit + 1,
      ],
    );
    return page(result.rows, query.limit, mapAdminFeedback);
  }

  async updateFeedback(actorUserId: string, id: string, dto: UpdateAdminFeedbackDto) {
    if (Object.keys(dto).length === 0) {
      throw new ApplicationError(400, 'BAD_REQUEST', 'At least one feedback field is required');
    }
    return this.transaction(async (client) => {
      const existing = await client.query<FeedbackAdminRow>(
        `SELECT f.id,f.user_id,u.email,f.kind,f.title,f.message,f.severity,f.status,
                f.created_at,f.updated_at,'[]'::jsonb AS responses
           FROM mymoneymap.feedback f JOIN mymoneymap.users u ON u.id=f.user_id
          WHERE f.id=$1 FOR UPDATE OF f`,
        [id],
      );
      if (!existing.rows[0]) throw feedbackNotFound();
      const result = await client.query<FeedbackAdminRow>(
        `UPDATE mymoneymap.feedback f SET
           kind=CASE WHEN $2 THEN $3 ELSE f.kind END,
           severity=CASE WHEN $4 THEN $5 ELSE f.severity END,
           status=CASE WHEN $6 THEN $7 ELSE f.status END,
           title=CASE WHEN $8 THEN $9 ELSE f.title END,
           message=CASE WHEN $10 THEN $11 ELSE f.message END,
           updated_at=now()
         FROM mymoneymap.users u
         WHERE f.id=$1 AND u.id=f.user_id
         RETURNING f.id,f.user_id,u.email,f.kind,f.title,f.message,f.severity,f.status,
                   f.created_at,f.updated_at,'[]'::jsonb AS responses`,
        [
          id,
          dto.kind !== undefined,
          dto.kind ?? null,
          dto.severity !== undefined,
          dto.severity ?? null,
          dto.status !== undefined,
          dto.status ?? null,
          dto.title !== undefined,
          dto.title?.trim() ?? null,
          dto.message !== undefined,
          dto.message?.trim() ?? null,
        ],
      );
      await audit(client, actorUserId, 'feedback.updated', 'feedback', id, {
        fields: Object.keys(dto).sort(),
      });
      return mapAdminFeedback(result.rows[0]!);
    });
  }

  async respondToFeedback(actorUserId: string, id: string, message: string) {
    return this.transaction(async (client) => {
      const feedback = await client.query<{ id: string }>(
        'SELECT id FROM mymoneymap.feedback WHERE id=$1 FOR UPDATE',
        [id],
      );
      if (!feedback.rows[0]) throw feedbackNotFound();
      const responseId = randomUUID();
      const now = new Date();
      await client.query(
        `INSERT INTO mymoneymap.feedback_responses
           (id,feedback_id,admin_id,message,created_at,updated_at)
         VALUES ($1,$2,$3,$4,$5,$5)`,
        [responseId, id, actorUserId, message.trim(), now],
      );
      await audit(client, actorUserId, 'feedback.responded', 'feedback', id, {
        responseId,
      });
      return {
        id: responseId,
        feedbackId: id,
        message: message.trim(),
        createdAt: now.toISOString(),
      };
    });
  }

  async system() {
    const [settings, integrations] = await Promise.all([
      this.pool.query('SELECT * FROM mymoneymap.system_settings WHERE id=1'),
      this.pool.query<{
        id: string;
        name: string;
        service: string;
        status: string;
        metadata: unknown;
        last_used_at: Date | null;
        created_at: Date;
        updated_at: Date;
      }>(
        `SELECT id,name,service,status,metadata,last_used_at,created_at,updated_at
           FROM mymoneymap.api_integrations ORDER BY service`,
      ),
    ]);
    return {
      settings: mapSettings(settings.rows[0] as Record<string, unknown>),
      canonicalUrl: this.config.getOrThrow<string>('APP_BASE_URL'),
      integrations: integrations.rows.map(mapIntegration),
    };
  }

  async updateSystem(actorUserId: string, dto: UpdateSystemSettingsDto) {
    if (Object.keys(dto).length === 0) {
      throw new ApplicationError(400, 'BAD_REQUEST', 'At least one system setting is required');
    }
    return this.transaction(async (client) => {
      const result = await client.query(
        `UPDATE mymoneymap.system_settings SET
           site_name=CASE WHEN $1 THEN $2 ELSE site_name END,
           primary_url=CASE WHEN $3 THEN $4 ELSE primary_url END,
           support_email=CASE WHEN $5 THEN $6 ELSE support_email END,
           contact_email=CASE WHEN $7 THEN $8 ELSE contact_email END,
           logo_url=CASE WHEN $9 THEN $10 ELSE logo_url END,
           favicon_url=CASE WHEN $11 THEN $12 ELSE favicon_url END,
           maintenance_mode=CASE WHEN $13 THEN $14 ELSE maintenance_mode END,
           maintenance_message=CASE WHEN $15 THEN $16 ELSE maintenance_message END,
           updated_at=now()
         WHERE id=1 RETURNING *`,
        [
          dto.siteName !== undefined,
          dto.siteName?.trim() ?? null,
          dto.primaryUrl !== undefined,
          dto.primaryUrl ?? null,
          dto.supportEmail !== undefined,
          dto.supportEmail ? normalizeEmail(dto.supportEmail) : null,
          dto.contactEmail !== undefined,
          dto.contactEmail ? normalizeEmail(dto.contactEmail) : null,
          dto.logoUrl !== undefined,
          dto.logoUrl ?? null,
          dto.faviconUrl !== undefined,
          dto.faviconUrl ?? null,
          dto.maintenanceMode !== undefined,
          dto.maintenanceMode ?? false,
          dto.maintenanceMessage !== undefined,
          dto.maintenanceMessage?.trim() ?? null,
        ],
      );
      await audit(client, actorUserId, 'system.settings_updated', 'system_settings', '1', {
        fields: Object.keys(dto).sort(),
      });
      return {
        ...mapSettings(result.rows[0] as Record<string, unknown>),
        canonicalUrl: this.config.getOrThrow<string>('APP_BASE_URL'),
      };
    });
  }

  async putIntegration(actorUserId: string, service: string, dto: PutIntegrationDto) {
    assertSafeMetadata(dto.metadata);
    const encrypted = await this.encryptedSettings.encrypt(SecretValue.create(dto.secret));
    const ciphertext = encrypted.useCiphertext((value) => value);
    return this.transaction(async (client) => {
      const result = await client.query<{
        id: string;
        name: string;
        service: string;
        status: string;
        metadata: unknown;
        last_used_at: Date | null;
        created_at: Date;
        updated_at: Date;
      }>(
        `INSERT INTO mymoneymap.api_integrations
           (id,name,service,api_key_encrypted,status,metadata,created_at,updated_at)
         VALUES ($1,$2,$3,$4,$5,$6,now(),now())
         ON CONFLICT (service) DO UPDATE SET
           name=EXCLUDED.name,
           api_key_encrypted=EXCLUDED.api_key_encrypted,
           status=EXCLUDED.status,
           metadata=EXCLUDED.metadata,
           updated_at=now()
         RETURNING id,name,service,status,metadata,last_used_at,created_at,updated_at`,
        [randomUUID(), dto.name.trim(), service, ciphertext, dto.status, dto.metadata],
      );
      await audit(client, actorUserId, 'integration.upserted', 'integration', service, {
        status: dto.status,
      });
      return mapIntegration(result.rows[0]!);
    });
  }

  async deleteIntegration(actorUserId: string, service: string): Promise<void> {
    await this.transaction(async (client) => {
      const result = await client.query(
        'DELETE FROM mymoneymap.api_integrations WHERE service=$1',
        [service],
      );
      if (result.rowCount !== 1) {
        throw new ApplicationError(404, 'NOT_FOUND', 'Integration was not found');
      }
      await audit(client, actorUserId, 'integration.deleted', 'integration', service, {});
    });
  }

  private async createRecovery(
    actorUserId: string,
    userId: string,
    kind: 'password_reset' | 'email_change',
    pendingEmail: string | null,
  ) {
    const token = randomBytes(32).toString('base64url');
    const now = new Date();
    const expiresAt = new Date(
      now.getTime() + this.config.getOrThrow<number>('ACCOUNT_RECOVERY_TTL_SECONDS') * 1000,
    );
    const user = await this.transaction(async (client) => {
      const target = await lockUser(client, userId);
      if (target.status !== 'active') {
        throw new ApplicationError(409, 'CONFLICT', 'Recovery requires an active account');
      }
      if (pendingEmail) {
        const duplicate = await client.query(
          'SELECT id FROM mymoneymap.users WHERE email=$1 AND id<>$2',
          [pendingEmail, userId],
        );
        if (duplicate.rows[0]) {
          throw new ApplicationError(409, 'CONFLICT', 'Email address is already in use');
        }
      }
      await client.query(
        `UPDATE mymoneymap.account_recovery_requests SET consumed_at=$3
          WHERE user_id=$1 AND kind=$2 AND consumed_at IS NULL`,
        [userId, kind, now],
      );
      await client.query(
        `INSERT INTO mymoneymap.account_recovery_requests
           (id,user_id,requested_by_admin_id,kind,token_hash,pending_email,expires_at,created_at)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8)`,
        [randomUUID(), userId, actorUserId, kind, hash(token), pendingEmail, expiresAt, now],
      );
      await audit(
        client,
        actorUserId,
        kind === 'password_reset' ? 'user.password_reset_requested' : 'user.email_change_requested',
        'user',
        userId,
        {},
      );
      return target;
    });
    return { email: user.email, fullName: user.full_name, token, expiresAt };
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

async function lockUser(client: PoolClient, id: string): Promise<UserRow> {
  const result = await client.query<UserRow>(
    `SELECT id,email,full_name,role,status,email_verified_at,created_at
       FROM mymoneymap.users WHERE id=$1 FOR UPDATE`,
    [id],
  );
  if (!result.rows[0]) throw userNotFound();
  return result.rows[0];
}

function audit(
  client: PoolClient,
  actorUserId: string,
  action: string,
  targetType: string,
  targetId: string | null,
  details: Record<string, unknown>,
): Promise<unknown> {
  return client.query(
    `INSERT INTO mymoneymap.privileged_audit_events
       (id,actor_user_id,action,target_type,target_id,details,created_at)
     VALUES ($1,$2,$3,$4,$5,$6,now())`,
    [randomUUID(), actorUserId, action, targetType, targetId, details],
  );
}

function mapAdminUser(row: UserRow) {
  return {
    id: row.id,
    emailMasked: maskEmail(row.email),
    role: row.role,
    status: row.status,
    emailVerified: row.email_verified_at !== null,
    createdAt: row.created_at.toISOString(),
  };
}

function mapAdminFeedback(row: FeedbackAdminRow) {
  return {
    id: row.id,
    user: { id: row.user_id, emailMasked: maskEmail(row.email) },
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

function mapSettings(row: Record<string, unknown>) {
  return {
    siteName: row.site_name,
    primaryUrl: row.primary_url,
    supportEmail: row.support_email,
    contactEmail: row.contact_email,
    logoUrl: row.logo_url,
    faviconUrl: row.favicon_url,
    maintenanceMode: row.maintenance_mode,
    maintenanceMessage: row.maintenance_message,
    updatedAt: (row.updated_at as Date).toISOString(),
  };
}

function mapIntegration(row: {
  id: string;
  name: string;
  service: string;
  status: string;
  metadata: unknown;
  last_used_at: Date | null;
  created_at: Date;
  updated_at: Date;
}) {
  return {
    id: row.id,
    name: row.name,
    service: row.service,
    status: row.status,
    metadata: row.metadata,
    configured: true,
    secret: REDACTED_SECRET,
    lastUsedAt: row.last_used_at?.toISOString() ?? null,
    createdAt: row.created_at.toISOString(),
    updatedAt: row.updated_at.toISOString(),
  };
}

function page<T extends { id: string; created_at: Date }, R>(
  rows: T[],
  limit: number,
  mapper: (row: T) => R,
) {
  const hasMore = rows.length > limit;
  const included = rows.slice(0, limit);
  const last = included.at(-1);
  return {
    items: included.map(mapper),
    nextCursor: hasMore && last ? encodeCursor(last.created_at, last.id) : null,
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

function maskEmail(email: string): string {
  const separator = email.lastIndexOf('@');
  if (separator < 1) return '[REDACTED]';
  const local = email.slice(0, separator);
  return `${local[0]}${'*'.repeat(Math.min(6, Math.max(2, local.length - 1)))}${email.slice(separator)}`;
}

function acceptedRecovery(expiresAt: Date) {
  return { status: 'accepted', expiresAt: expiresAt.toISOString() };
}

function assertSafeMetadata(metadata: Record<string, unknown>): void {
  for (const [key, value] of Object.entries(metadata)) {
    if (/(secret|token|password|credential|api.?key)/i.test(key)) {
      throw new ApplicationError(400, 'BAD_REQUEST', 'Secret values must use the write-only field');
    }
    if (value !== null && !['string', 'boolean'].includes(typeof value)) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        'Integration metadata must be flat and non-secret',
      );
    }
  }
}

function userNotFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'User was not found');
}

function feedbackNotFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'Feedback was not found');
}
