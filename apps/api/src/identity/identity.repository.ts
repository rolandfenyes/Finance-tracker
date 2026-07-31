import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import type { IdentityUser, UserRole, UserStatus } from './identity.types';

interface UserRow {
  id: string;
  email: string;
  password_hash: string;
  full_name: string;
  date_of_birth: string;
  role: UserRole;
  status: UserStatus;
  email_verified_at: Date | null;
  created_at: Date;
}

export interface PasskeyRecord {
  id: string;
  userId: string;
  credentialId: string;
  publicKey: Uint8Array;
  counter: number;
  revision: number;
  transports: string[];
  deviceType: string;
  backedUp: boolean;
  label: string;
  createdAt: Date;
  lastUsedAt: Date | null;
}

@Injectable()
export class IdentityRepository {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async createUser(input: {
    email: string;
    passwordHash: string;
    fullName: string;
    dateOfBirth: string;
    now: Date;
  }): Promise<IdentityUser | null> {
    const result = await this.pool.query<UserRow>(
      `INSERT INTO mymoneymap.users
         (id, email, password_hash, full_name, date_of_birth, created_at, updated_at)
       VALUES ($1, $2, $3, $4, $5, $6, $6)
       ON CONFLICT (email) DO NOTHING
       RETURNING *`,
      [randomUUID(), input.email, input.passwordHash, input.fullName, input.dateOfBirth, input.now],
    );
    return result.rows[0] ? mapUser(result.rows[0]) : null;
  }

  async findUserByEmail(email: string): Promise<IdentityUser | null> {
    const result = await this.pool.query<UserRow>(
      'SELECT * FROM mymoneymap.users WHERE email = $1 LIMIT 1',
      [email],
    );
    return result.rows[0] ? mapUser(result.rows[0]) : null;
  }

  async findUserById(id: string): Promise<IdentityUser | null> {
    const result = await this.pool.query<UserRow>(
      'SELECT * FROM mymoneymap.users WHERE id = $1 LIMIT 1',
      [id],
    );
    return result.rows[0] ? mapUser(result.rows[0]) : null;
  }

  async replaceVerificationToken(input: {
    userId: string;
    tokenHash: string;
    now: Date;
    expiresAt: Date;
  }): Promise<void> {
    await this.transaction(async (client) => {
      await client.query('SELECT id FROM mymoneymap.users WHERE id = $1 FOR UPDATE', [
        input.userId,
      ]);
      await client.query(
        `UPDATE mymoneymap.email_verification_tokens
            SET consumed_at = $2
          WHERE user_id = $1 AND consumed_at IS NULL`,
        [input.userId, input.now],
      );
      await client.query(
        `INSERT INTO mymoneymap.email_verification_tokens
           (id, user_id, token_hash, expires_at, created_at)
         VALUES ($1, $2, $3, $4, $5)`,
        [randomUUID(), input.userId, input.tokenHash, input.expiresAt, input.now],
      );
    });
  }

  async latestVerificationSentAt(userId: string): Promise<Date | null> {
    const result = await this.pool.query<{ created_at: Date }>(
      `SELECT created_at
         FROM mymoneymap.email_verification_tokens
        WHERE user_id = $1
        ORDER BY created_at DESC
        LIMIT 1`,
      [userId],
    );
    return result.rows[0]?.created_at ?? null;
  }

  async consumeVerificationToken(tokenHash: string, now: Date): Promise<IdentityUser | null> {
    return this.transaction(async (client) => {
      const token = await client.query<{ id: string; user_id: string }>(
        `UPDATE mymoneymap.email_verification_tokens
            SET consumed_at = $2
          WHERE token_hash = $1
            AND consumed_at IS NULL
            AND expires_at > $2
        RETURNING id, user_id`,
        [tokenHash, now],
      );
      const userId = token.rows[0]?.user_id;
      if (!userId) return null;
      const user = await client.query<UserRow>(
        `UPDATE mymoneymap.users
            SET email_verified_at = COALESCE(email_verified_at, $2), updated_at = $2
          WHERE id = $1
        RETURNING *`,
        [userId, now],
      );
      return user.rows[0] ? mapUser(user.rows[0]) : null;
    });
  }

  async updatePassword(userId: string, passwordHash: string, now: Date): Promise<void> {
    await this.pool.query(
      'UPDATE mymoneymap.users SET password_hash = $2, updated_at = $3 WHERE id = $1',
      [userId, passwordHash, now],
    );
  }

  async recordLoginAudit(input: {
    userId: string | null;
    emailHash: string;
    ipHash: string;
    userAgentHash: string;
    outcome: 'success' | 'failure' | 'throttled';
    method: 'password' | 'passkey';
    now: Date;
  }): Promise<void> {
    await this.pool.query(
      `INSERT INTO mymoneymap.login_audit_events
         (id, user_id, email_hash, ip_hash, user_agent_hash, outcome, method, created_at)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8)`,
      [
        randomUUID(),
        input.userId,
        input.emailHash,
        input.ipHash,
        input.userAgentHash,
        input.outcome,
        input.method,
        input.now,
      ],
    );
  }

  async listPasskeys(userId: string): Promise<PasskeyRecord[]> {
    const result = await this.pool.query<{
      id: string;
      user_id: string;
      credential_id: string;
      public_key: Buffer;
      counter: string;
      revision: string;
      transports: string[];
      device_type: string;
      backed_up: boolean;
      label: string;
      created_at: Date;
      last_used_at: Date | null;
    }>(
      `SELECT id, user_id, credential_id, public_key, counter::text, revision::text, transports,
              device_type, backed_up, label, created_at, last_used_at
         FROM mymoneymap.passkeys WHERE user_id = $1
         ORDER BY created_at ASC, id ASC`,
      [userId],
    );
    return result.rows.map(mapPasskey);
  }

  async findPasskeyByCredentialId(credentialId: string): Promise<PasskeyRecord | null> {
    const result = await this.pool.query<{
      id: string;
      user_id: string;
      credential_id: string;
      public_key: Buffer;
      counter: string;
      revision: string;
      transports: string[];
      device_type: string;
      backed_up: boolean;
      label: string;
      created_at: Date;
      last_used_at: Date | null;
    }>(
      `SELECT id, user_id, credential_id, public_key, counter::text, revision::text, transports,
              device_type, backed_up, label, created_at, last_used_at
         FROM mymoneymap.passkeys WHERE credential_id = $1 LIMIT 1`,
      [credentialId],
    );
    return result.rows[0] ? mapPasskey(result.rows[0]) : null;
  }

  async addPasskey(input: {
    userId: string;
    credentialId: string;
    publicKey: Uint8Array;
    counter: number;
    transports: string[];
    deviceType: string;
    backedUp: boolean;
    label: string;
    now: Date;
  }): Promise<string> {
    const id = randomUUID();
    await this.transaction(async (client) => {
      await client.query(
        `INSERT INTO mymoneymap.passkeys
           (id, user_id, credential_id, public_key, counter, transports, device_type,
            backed_up, label, created_at)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)`,
        [
          id,
          input.userId,
          input.credentialId,
          Buffer.from(input.publicKey),
          String(input.counter),
          input.transports,
          input.deviceType,
          input.backedUp,
          input.label,
          input.now,
        ],
      );
      await insertPasskeyAudit(client, {
        userId: input.userId,
        action: 'passkey.registered',
        passkeyId: id,
        now: input.now,
      });
    });
    return id;
  }

  async updatePasskeyCounter(
    id: string,
    expectedCounter: number,
    expectedRevision: number,
    newCounter: number,
    now: Date,
  ): Promise<boolean> {
    const result = await this.pool.query(
      `UPDATE mymoneymap.passkeys
          SET counter = $4, revision = revision + 1, last_used_at = $5
        WHERE id = $1 AND counter = $2 AND revision = $3`,
      [id, String(expectedCounter), String(expectedRevision), String(newCounter), now],
    );
    return result.rowCount === 1;
  }

  async deleteOwnedPasskey(userId: string, passkeyId: string, now: Date): Promise<boolean> {
    return this.transaction(async (client) => {
      const result = await client.query(
        'DELETE FROM mymoneymap.passkeys WHERE id = $1 AND user_id = $2 RETURNING id',
        [passkeyId, userId],
      );
      if (result.rowCount !== 1) return false;
      await insertPasskeyAudit(client, {
        userId,
        action: 'passkey.deleted',
        passkeyId,
        now,
      });
      return true;
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

function mapUser(row: UserRow): IdentityUser {
  return {
    id: row.id,
    email: row.email,
    passwordHash: row.password_hash,
    fullName: row.full_name,
    dateOfBirth: row.date_of_birth,
    role: row.role,
    status: row.status,
    emailVerifiedAt: row.email_verified_at,
    createdAt: row.created_at,
  };
}

function mapPasskey(row: {
  id: string;
  user_id: string;
  credential_id: string;
  public_key: Buffer;
  counter: string;
  revision: string;
  transports: string[];
  device_type: string;
  backed_up: boolean;
  label: string;
  created_at: Date;
  last_used_at: Date | null;
}): PasskeyRecord {
  return {
    id: row.id,
    userId: row.user_id,
    credentialId: row.credential_id,
    publicKey: new Uint8Array(row.public_key),
    counter: Number(row.counter),
    revision: Number(row.revision),
    transports: row.transports,
    deviceType: row.device_type,
    backedUp: row.backed_up,
    label: row.label,
    createdAt: row.created_at,
    lastUsedAt: row.last_used_at,
  };
}

function insertPasskeyAudit(
  client: PoolClient,
  input: {
    userId: string;
    action: 'passkey.registered' | 'passkey.deleted';
    passkeyId: string;
    now: Date;
  },
): Promise<unknown> {
  return client.query(
    `INSERT INTO mymoneymap.security_audit_events
       (id,actor_user_id,subject_user_id,subject_hash,action,target_type,target_id,details,created_at)
     VALUES ($1,$2,$2,$3,$4,'passkey',$5,'{}'::jsonb,$6)`,
    [
      randomUUID(),
      input.userId,
      createHash('sha256').update(input.userId).digest('hex'),
      input.action,
      input.passkeyId,
      input.now,
    ],
  );
}
