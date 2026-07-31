import {
  generateAuthenticationOptions,
  generateRegistrationOptions,
  verifyAuthenticationResponse,
  verifyRegistrationResponse,
  type AuthenticatorTransportFuture,
} from '@simplewebauthn/server';
import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash } from 'node:crypto';
import type { Request } from 'express';
import { ApplicationError } from '../platform/http/application-error';
import { IdentityRepository } from './identity.repository';
import type { IdentityUser } from './identity.types';
import { SessionService } from './session.service';
import {
  PasskeyAuthenticationCredentialDto,
  PasskeyAuthenticationOptionsResponseDto,
  PasskeyListResponseDto,
  PasskeyRegistrationCredentialDto,
  PasskeyRegistrationOptionsResponseDto,
  PasskeyRegistrationResponseDto,
} from './webauthn.dto';

@Injectable()
export class PasskeyService {
  private readonly rpId: string;
  private readonly rpName: string;
  private readonly origins: string[];
  private readonly challengeSeconds: number;

  constructor(
    @Inject(ConfigService) config: ConfigService,
    @Inject(IdentityRepository) private readonly repository: IdentityRepository,
    @Inject(SessionService) private readonly sessions: SessionService,
  ) {
    this.rpId = config.getOrThrow('WEBAUTHN_RP_ID');
    this.rpName = config.getOrThrow('WEBAUTHN_RP_NAME');
    this.origins = config.getOrThrow('WEBAUTHN_EXPECTED_ORIGINS');
    this.challengeSeconds = config.getOrThrow('WEBAUTHN_CHALLENGE_TTL_SECONDS');
  }

  async registrationOptions(
    userId: string,
    request: Request,
  ): Promise<PasskeyRegistrationOptionsResponseDto> {
    const user = await this.requireActiveUser(userId);
    const credentials = await this.repository.listPasskeys(userId);
    const options = await generateRegistrationOptions({
      rpName: this.rpName,
      rpID: this.rpId,
      userID: new TextEncoder().encode(user.id),
      userName: user.email,
      userDisplayName: user.fullName,
      attestationType: 'none',
      authenticatorSelection: { residentKey: 'preferred', userVerification: 'required' },
      excludeCredentials: credentials.map((credential) => ({
        id: credential.credentialId,
        transports: credential.transports as AuthenticatorTransportFuture[],
      })),
    });
    this.rememberChallenge(request, options.challenge, 'registration', userId);
    return options;
  }

  async register(
    userId: string,
    label: string,
    response: PasskeyRegistrationCredentialDto,
    request: Request,
  ): Promise<PasskeyRegistrationResponseDto> {
    const challenge = this.takeChallenge(request, 'registration', userId);
    let verification;
    try {
      verification = await verifyRegistrationResponse({
        response,
        expectedChallenge: challenge,
        expectedOrigin: this.origins,
        expectedRPID: this.rpId,
        requireUserVerification: true,
      });
    } catch {
      throw invalidPasskey();
    }
    if (!verification.verified) throw invalidPasskey();
    const info = verification.registrationInfo;
    let id: string;
    try {
      id = await this.repository.addPasskey({
        userId,
        credentialId: info.credential.id,
        publicKey: info.credential.publicKey,
        counter: info.credential.counter,
        transports: info.credential.transports ?? [],
        deviceType: info.credentialDeviceType,
        backedUp: info.credentialBackedUp,
        label: label.trim(),
        now: new Date(),
      });
    } catch (error) {
      if ((error as { code?: unknown }).code === '23505') {
        throw new ApplicationError(409, 'CONFLICT', 'Passkey is already registered');
      }
      throw error;
    }
    await save(request);
    return { id };
  }

  async authenticationOptions(request: Request): Promise<PasskeyAuthenticationOptionsResponseDto> {
    const options = await generateAuthenticationOptions({
      rpID: this.rpId,
      userVerification: 'required',
    });
    this.rememberChallenge(request, options.challenge, 'authentication');
    return options;
  }

  async authenticate(
    response: PasskeyAuthenticationCredentialDto,
    remember: boolean,
    request: Request,
  ): Promise<void> {
    try {
      await this.authenticateVerified(response, remember, request);
    } catch (error) {
      await this.auditPasskey(null, response, request, 'failure');
      throw error;
    }
  }

  private async authenticateVerified(
    response: PasskeyAuthenticationCredentialDto,
    remember: boolean,
    request: Request,
  ): Promise<void> {
    const challenge = this.takeChallenge(request, 'authentication');
    const credentialId = typeof response.id === 'string' ? response.id : '';
    const passkey = credentialId
      ? await this.repository.findPasskeyByCredentialId(credentialId)
      : null;
    if (!passkey) throw invalidPasskey();
    const user = await this.requireActiveUser(passkey.userId);
    let verification;
    try {
      verification = await verifyAuthenticationResponse({
        response,
        expectedChallenge: challenge,
        expectedOrigin: this.origins,
        expectedRPID: this.rpId,
        credential: {
          id: passkey.credentialId,
          publicKey: Uint8Array.from(passkey.publicKey),
          counter: passkey.counter,
          transports: passkey.transports as AuthenticatorTransportFuture[],
        },
        requireUserVerification: true,
      });
    } catch {
      throw invalidPasskey();
    }
    if (!verification.verified) throw invalidPasskey();
    const updated = await this.repository.updatePasskeyCounter(
      passkey.id,
      passkey.counter,
      passkey.revision,
      verification.authenticationInfo.newCounter,
      new Date(),
    );
    if (!updated) throw invalidPasskey();
    await this.sessions.establish(
      request,
      { userId: user.id, role: user.role, emailVerified: user.emailVerifiedAt !== null },
      remember,
    );
    await this.auditPasskey(user.id, { email: user.email }, request, 'success');
  }

  async list(userId: string): Promise<PasskeyListResponseDto> {
    const items = await this.repository.listPasskeys(userId);
    return {
      items: items.map((passkey) => ({
        id: passkey.id,
        label: passkey.label,
        deviceType: passkey.deviceType as 'singleDevice' | 'multiDevice',
        backedUp: passkey.backedUp,
        transports: passkey.transports as PasskeyListResponseDto['items'][number]['transports'],
        createdAt: passkey.createdAt.toISOString(),
        lastUsedAt: passkey.lastUsedAt?.toISOString() ?? null,
      })),
    };
  }

  async delete(userId: string, passkeyId: string): Promise<void> {
    if (!(await this.repository.deleteOwnedPasskey(userId, passkeyId, new Date()))) {
      throw new ApplicationError(404, 'NOT_FOUND', 'Passkey not found');
    }
  }

  private rememberChallenge(
    request: Request,
    challenge: string,
    flow: 'authentication' | 'registration',
    userId?: string,
  ): void {
    request.session.webauthn = {
      challenge,
      flow,
      userId,
      expiresAt: new Date(Date.now() + this.challengeSeconds * 1000).toISOString(),
    };
  }

  private takeChallenge(
    request: Request,
    flow: 'authentication' | 'registration',
    userId?: string,
  ): string {
    const state = request.session.webauthn;
    delete request.session.webauthn;
    if (
      !state ||
      state.flow !== flow ||
      state.userId !== userId ||
      Date.parse(state.expiresAt) <= Date.now()
    ) {
      throw invalidPasskey();
    }
    return state.challenge;
  }

  private async requireActiveUser(userId: string): Promise<IdentityUser> {
    const user = await this.repository.findUserById(userId);
    if (!user || user.status !== 'active') throw invalidPasskey();
    return user;
  }

  private auditPasskey(
    userId: string | null,
    subject: { email?: string; id?: string },
    request: Request,
    outcome: 'success' | 'failure',
  ): Promise<void> {
    const value =
      typeof subject.email === 'string'
        ? subject.email
        : typeof subject.id === 'string'
          ? subject.id
          : 'unknown';
    return this.repository.recordLoginAudit({
      userId,
      emailHash: digest(value),
      ipHash: digest(request.ip ?? 'unknown'),
      userAgentHash: digest(request.get('user-agent') ?? ''),
      outcome,
      method: 'passkey',
      now: new Date(),
    });
  }
}

function invalidPasskey(): ApplicationError {
  return new ApplicationError(401, 'UNAUTHORIZED', 'Passkey authentication failed');
}

function save(request: Request): Promise<void> {
  return new Promise((resolve, reject) =>
    request.session.save((error) =>
      error ? reject(error instanceof Error ? error : new Error('Session save failed')) : resolve(),
    ),
  );
}

function digest(value: string): string {
  return createHash('sha256').update(value).digest('hex');
}
