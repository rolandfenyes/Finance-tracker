import { randomUUID } from 'node:crypto';
import { migrateOneDown, migrateToLatest } from '../src/platform/database/migration-runner';
import { PasswordService } from '../src/identity/password.service';
import { IdentityRepository } from '../src/identity/identity.repository';
import { hash } from '../src/identity/identity.service';
import { withIsolatedPostgresDatabase } from './postgres-test-database';

describe('identity/access PostgreSQL invariants', () => {
  it('migrates up and rolls Step 04 back without disturbing prior tables', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM information_schema.tables
              WHERE table_schema = 'mymoneymap'
                AND table_name IN ('users','email_verification_tokens','passkeys','login_audit_events')`,
          )
        ).rows[0]?.count,
      ).toBe('4');

      await migrateOneDown(database);
      await migrateOneDown(database);
      await migrateOneDown(database);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM information_schema.tables
              WHERE table_schema = 'mymoneymap' AND table_name = 'idempotency_keys'`,
          )
        ).rows[0]?.count,
      ).toBe('1');
    });
  });

  it('enforces normalized unique email, roles, status, ownership and passkey counters', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const passwordHash = await new PasswordService().hash('synthetic-password');
      const userId = randomUUID();
      await pool.query(
        `INSERT INTO mymoneymap.users
          (id,email,password_hash,full_name,date_of_birth,created_at,updated_at)
         VALUES ($1,'one@example.test',$2,'User One','1990-01-01',now(),now())`,
        [userId, passwordHash],
      );
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.users
            (id,email,password_hash,full_name,date_of_birth,role,created_at,updated_at)
           VALUES ($1,'UPPER@example.test',$2,'User Two','1990-01-01','owner',now(),now())`,
          [randomUUID(), passwordHash],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.passkeys
            (id,user_id,credential_id,public_key,counter,device_type,backed_up,label,created_at)
           VALUES ($1,$2,'credential',decode('00','hex'),-1,'singleDevice',false,'Key',now())`,
          [randomUUID(), userId],
        ),
      ).rejects.toMatchObject({ code: '23514' });
      await expect(
        pool.query(
          `INSERT INTO mymoneymap.passkeys
            (id,user_id,credential_id,public_key,counter,device_type,backed_up,label,created_at)
           VALUES ($1,$2,'credential',decode('00','hex'),0,'singleDevice',false,'Key',now())`,
          [randomUUID(), randomUUID()],
        ),
      ).rejects.toMatchObject({ code: '23503' });
    });
  });

  it('consumes verification tokens once, rejects expiry, and serializes replacement', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const repository = new IdentityRepository(pool);
      const user = await repository.createUser({
        email: 'verify@example.test',
        passwordHash: 'synthetic-hash',
        fullName: 'Verify User',
        dateOfBirth: '1990-01-01',
        now: new Date('2026-07-29T10:00:00.000Z'),
      });
      const tokenHash = hash('synthetic-token');
      await repository.replaceVerificationToken({
        userId: user!.id,
        tokenHash,
        now: new Date('2026-07-29T10:00:00.000Z'),
        expiresAt: new Date('2026-07-29T11:00:00.000Z'),
      });

      await expect(
        repository.consumeVerificationToken(tokenHash, new Date('2026-07-29T10:30:00.000Z')),
      ).resolves.toMatchObject({ id: user!.id });
      await expect(
        repository.consumeVerificationToken(tokenHash, new Date('2026-07-29T10:31:00.000Z')),
      ).resolves.toBeNull();

      await repository.replaceVerificationToken({
        userId: user!.id,
        tokenHash: hash('expired-token'),
        now: new Date('2026-07-29T12:00:00.000Z'),
        expiresAt: new Date('2026-07-29T12:01:00.000Z'),
      });
      await expect(
        repository.consumeVerificationToken(
          hash('expired-token'),
          new Date('2026-07-29T12:02:00.000Z'),
        ),
      ).resolves.toBeNull();

      await Promise.all([
        repository.replaceVerificationToken({
          userId: user!.id,
          tokenHash: hash('concurrent-a'),
          now: new Date('2026-07-29T13:00:00.000Z'),
          expiresAt: new Date('2026-07-29T14:00:00.000Z'),
        }),
        repository.replaceVerificationToken({
          userId: user!.id,
          tokenHash: hash('concurrent-b'),
          now: new Date('2026-07-29T13:00:00.000Z'),
          expiresAt: new Date('2026-07-29T14:00:00.000Z'),
        }),
      ]);
      expect(
        (
          await pool.query<{ count: string }>(
            `SELECT count(*)::text AS count
               FROM mymoneymap.email_verification_tokens
              WHERE user_id = $1 AND consumed_at IS NULL`,
            [user!.id],
          )
        ).rows[0]?.count,
      ).toBe('1');
    });
  });

  it('isolates passkey deletion by owner and protects concurrent counter updates', async () => {
    await withIsolatedPostgresDatabase(async ({ database, pool }) => {
      await migrateToLatest(database);
      const repository = new IdentityRepository(pool);
      const now = new Date('2026-07-29T10:00:00.000Z');
      const users = await Promise.all(
        ['a@example.test', 'b@example.test'].map((email) =>
          repository.createUser({
            email,
            passwordHash: 'synthetic-hash',
            fullName: 'Synthetic User',
            dateOfBirth: '1990-01-01',
            now,
          }),
        ),
      );
      const passkeyId = await repository.addPasskey({
        userId: users[0]!.id,
        credentialId: 'synthetic-credential',
        publicKey: Uint8Array.from([1, 2, 3]),
        counter: 1,
        transports: [],
        deviceType: 'singleDevice',
        backedUp: false,
        label: 'Synthetic key',
        now,
      });
      await expect(repository.deleteOwnedPasskey(users[1]!.id, passkeyId)).resolves.toBe(false);
      const updates = await Promise.all([
        repository.updatePasskeyCounter(passkeyId, 1, 0, 1, now),
        repository.updatePasskeyCounter(passkeyId, 1, 0, 1, now),
      ]);
      expect(updates.sort()).toEqual([false, true]);
    });
  });
});
