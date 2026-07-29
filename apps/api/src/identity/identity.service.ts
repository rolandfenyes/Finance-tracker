import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash, randomBytes } from 'node:crypto';
import type { Request } from 'express';
import { ApplicationError } from '../platform/http/application-error';
import { IdentityRepository } from './identity.repository';
import { LoginRateLimiter } from './login-rate-limiter.service';
import { PasswordService } from './password.service';
import { SessionService } from './session.service';
import { VERIFICATION_NOTIFIER, type VerificationNotifier } from './verification-notifier';

@Injectable()
export class IdentityService {
  constructor(
    @Inject(IdentityRepository) private readonly repository: IdentityRepository,
    @Inject(PasswordService) private readonly passwords: PasswordService,
    @Inject(SessionService) private readonly sessions: SessionService,
    @Inject(LoginRateLimiter) private readonly rateLimiter: LoginRateLimiter,
    @Inject(ConfigService) private readonly config: ConfigService,
    @Inject(VERIFICATION_NOTIFIER) private readonly notifier: VerificationNotifier,
  ) {}

  async register(input: {
    email: string;
    password: string;
    fullName: string;
    dateOfBirth: string;
  }): Promise<void> {
    const now = new Date();
    const email = normalizeEmail(input.email);
    const passwordHash = await this.passwords.hash(input.password);
    const user = await this.repository.createUser({
      email,
      passwordHash,
      fullName: input.fullName.trim(),
      dateOfBirth: input.dateOfBirth,
      now,
    });
    if (!user) return;
    await this.issueVerification(user.id, user.email, user.fullName, now);
  }

  async requestVerification(emailInput: string): Promise<void> {
    const user = await this.repository.findUserByEmail(normalizeEmail(emailInput));
    if (!user || user.emailVerifiedAt || user.status !== 'active') return;
    const now = new Date();
    const lastSent = await this.repository.latestVerificationSentAt(user.id);
    const resendMs = this.config.getOrThrow<number>('EMAIL_VERIFICATION_RESEND_SECONDS') * 1000;
    if (lastSent && now.getTime() - lastSent.getTime() < resendMs) return;
    await this.issueVerification(user.id, user.email, user.fullName, now);
  }

  async verifyEmail(token: string, request: Request): Promise<void> {
    const user = await this.repository.consumeVerificationToken(hash(token), new Date());
    if (!user)
      throw new ApplicationError(400, 'BAD_REQUEST', 'Verification token is invalid or expired');
    if (request.session.principal?.userId === user.id) {
      request.session.principal.emailVerified = true;
      await save(request);
    }
  }

  async login(input: {
    email: string;
    password: string;
    remember: boolean;
    request: Request;
  }): Promise<void> {
    const email = normalizeEmail(input.email);
    const ip = input.request.ip ?? 'unknown';
    const allowed = await this.rateLimiter.consume(email, ip);
    if (!allowed) {
      await this.audit(null, email, ip, input.request, 'throttled', 'password');
      throw new ApplicationError(429, 'TOO_MANY_REQUESTS', 'Too many authentication attempts');
    }
    const user = await this.repository.findUserByEmail(email);
    const valid = user ? await this.passwords.verify(user.passwordHash, input.password) : false;
    if (!user || !valid || user.status !== 'active') {
      await this.audit(user?.id ?? null, email, ip, input.request, 'failure', 'password');
      throw invalidCredentials();
    }
    if (await this.passwords.needsRehash(user.passwordHash)) {
      await this.repository.updatePassword(
        user.id,
        await this.passwords.hash(input.password),
        new Date(),
      );
    }
    await this.sessions.establish(
      input.request,
      { userId: user.id, role: user.role, emailVerified: user.emailVerifiedAt !== null },
      input.remember,
    );
    await this.rateLimiter.clear(email);
    await this.audit(user.id, email, ip, input.request, 'success', 'password');
  }

  async changePassword(
    userId: string,
    currentPassword: string,
    newPassword: string,
  ): Promise<void> {
    const user = await this.repository.findUserById(userId);
    if (!user || !(await this.passwords.verify(user.passwordHash, currentPassword))) {
      throw invalidCredentials();
    }
    const newHash = await this.passwords.hash(newPassword);
    await this.sessions.revokeAllForUser(userId);
    await this.repository.updatePassword(userId, newHash, new Date());
  }

  private async issueVerification(
    userId: string,
    email: string,
    fullName: string,
    now: Date,
  ): Promise<void> {
    const token = randomBytes(32).toString('base64url');
    const expiresAt = new Date(
      now.getTime() + this.config.getOrThrow<number>('EMAIL_VERIFICATION_TTL_SECONDS') * 1000,
    );
    await this.repository.replaceVerificationToken({
      userId,
      tokenHash: hash(token),
      now,
      expiresAt,
    });
    await this.notifier.sendVerification({ email, fullName, token });
  }

  private audit(
    userId: string | null,
    email: string,
    ip: string,
    request: Request,
    outcome: 'success' | 'failure' | 'throttled',
    method: 'password' | 'passkey',
  ): Promise<void> {
    return this.repository.recordLoginAudit({
      userId,
      emailHash: hash(email),
      ipHash: hash(ip),
      userAgentHash: hash(request.get('user-agent') ?? ''),
      outcome,
      method,
      now: new Date(),
    });
  }
}

export function normalizeEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function hash(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}

function invalidCredentials(): ApplicationError {
  return new ApplicationError(401, 'UNAUTHORIZED', 'Invalid authentication credentials');
}

function save(request: Request): Promise<void> {
  return new Promise((resolve, reject) =>
    request.session.save((error) =>
      error ? reject(error instanceof Error ? error : new Error('Session save failed')) : resolve(),
    ),
  );
}
