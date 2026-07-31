import {
  generateAuthenticationOptions,
  generateRegistrationOptions,
  verifyAuthenticationResponse,
  verifyRegistrationResponse,
} from '@simplewebauthn/server';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import { PasskeyService } from './passkey.service';
import type {
  PasskeyAuthenticationCredentialDto,
  PasskeyRegistrationCredentialDto,
} from './webauthn.dto';

jest.mock('@simplewebauthn/server', () => ({
  generateAuthenticationOptions: jest.fn(),
  generateRegistrationOptions: jest.fn(),
  verifyAuthenticationResponse: jest.fn(),
  verifyRegistrationResponse: jest.fn(),
}));

const generateAuthentication = jest.mocked(generateAuthenticationOptions);
const generateRegistration = jest.mocked(generateRegistrationOptions);
const verifyAuthentication = jest.mocked(verifyAuthenticationResponse);
const verifyRegistration = jest.mocked(verifyRegistrationResponse);

describe('PasskeyService security contract', () => {
  const repository = {
    findPasskeyByCredentialId: jest.fn(),
    findUserById: jest.fn(),
    updatePasskeyCounter: jest.fn(),
    listPasskeys: jest.fn(),
    addPasskey: jest.fn(),
    deleteOwnedPasskey: jest.fn(),
    recordLoginAudit: jest.fn().mockResolvedValue(undefined),
  };
  const sessions = { establish: jest.fn() };
  const config = new ConfigService({
    WEBAUTHN_RP_ID: 'wallet.example.test',
    WEBAUTHN_RP_NAME: 'MyMoneyMap',
    WEBAUTHN_EXPECTED_ORIGINS: ['https://app.example.test'],
    WEBAUTHN_CHALLENGE_TTL_SECONDS: 300,
  });
  const service = new PasskeyService(config, repository as never, sessions as never);

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('returns maintained-library registration options and a server-owned registration id', async () => {
    repository.findUserById.mockResolvedValue({
      id: 'user-id',
      email: 'owner@example.test',
      fullName: 'Passkey Owner',
      role: 'free',
      status: 'active',
      emailVerifiedAt: new Date(),
    });
    repository.listPasskeys.mockResolvedValue([]);
    generateRegistration.mockResolvedValue({
      rp: { id: 'wallet.example.test', name: 'MyMoneyMap' },
      user: { id: 'dXNlci1pZA', name: 'owner@example.test', displayName: 'Passkey Owner' },
      challenge: 'synthetic-registration-challenge',
      pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
    });
    verifyRegistration.mockResolvedValue({
      verified: true,
      registrationInfo: {
        fmt: 'none',
        aaguid: '00000000-0000-0000-0000-000000000000',
        credentialType: 'public-key',
        attestationObject: Uint8Array.from([4, 5, 6]),
        credential: {
          id: 'registered-credential',
          publicKey: Uint8Array.from([1, 2, 3]),
          counter: 0,
          transports: ['internal'],
        },
        credentialDeviceType: 'singleDevice',
        credentialBackedUp: false,
        origin: 'https://app.example.test',
        rpID: 'wallet.example.test',
        userVerified: true,
      },
    });
    repository.addPasskey.mockResolvedValue('018f5f20-896e-4f2e-8d8b-66467d6c1670');
    const request = fakeRequest();

    await expect(service.registrationOptions('user-id', request)).resolves.toMatchObject({
      challenge: 'synthetic-registration-challenge',
      rp: { id: 'wallet.example.test' },
    });
    await expect(
      service.register('user-id', 'Laptop', registrationCredential(), request),
    ).resolves.toEqual({ id: '018f5f20-896e-4f2e-8d8b-66467d6c1670' });

    expect(verifyRegistration).toHaveBeenCalledWith(
      expect.objectContaining({
        expectedChallenge: 'synthetic-registration-challenge',
        expectedOrigin: ['https://app.example.test'],
        expectedRPID: 'wallet.example.test',
        requireUserVerification: true,
      }),
    );
    expect(repository.addPasskey).toHaveBeenCalledWith(
      expect.objectContaining({ userId: 'user-id', label: 'Laptop' }),
    );
  });

  it('lists only safe passkey metadata supplied by the owned repository boundary', async () => {
    repository.listPasskeys.mockResolvedValue([
      {
        id: '018f5f20-896e-4f2e-8d8b-66467d6c1670',
        userId: 'user-id',
        credentialId: 'must-not-leave-service',
        publicKey: Uint8Array.from([1, 2]),
        counter: 2,
        revision: 1,
        transports: ['internal'],
        deviceType: 'singleDevice',
        backedUp: false,
        label: 'Laptop',
        createdAt: new Date('2026-07-31T10:00:00.000Z'),
        lastUsedAt: null,
      },
    ]);

    const response = await service.list('user-id');

    expect(repository.listPasskeys).toHaveBeenCalledWith('user-id');
    expect(response).toEqual({
      items: [
        {
          id: '018f5f20-896e-4f2e-8d8b-66467d6c1670',
          label: 'Laptop',
          deviceType: 'singleDevice',
          backedUp: false,
          transports: ['internal'],
          createdAt: '2026-07-31T10:00:00.000Z',
          lastUsedAt: null,
        },
      ],
    });
    expect(JSON.stringify(response)).not.toContain('must-not-leave-service');
    expect(JSON.stringify(response)).not.toContain('publicKey');
  });

  it('passes only explicitly configured RP/origin values to maintained verification', async () => {
    generateAuthentication.mockResolvedValue({
      challenge: 'synthetic-challenge',
      timeout: 60_000,
      rpId: 'wallet.example.test',
      userVerification: 'required',
    });
    repository.findPasskeyByCredentialId.mockResolvedValue({
      id: 'passkey-id',
      userId: 'user-id',
      credentialId: 'credential-id',
      publicKey: Uint8Array.from([1, 2]),
      counter: 1,
      revision: 0,
      transports: [],
      deviceType: 'singleDevice',
      backedUp: false,
    });
    repository.findUserById.mockResolvedValue({
      id: 'user-id',
      role: 'free',
      status: 'active',
      emailVerifiedAt: new Date(),
    });
    repository.updatePasskeyCounter.mockResolvedValue(true);
    verifyAuthentication.mockResolvedValue({
      verified: true,
      authenticationInfo: {
        credentialID: 'credential-id',
        newCounter: 2,
        userVerified: true,
        credentialDeviceType: 'singleDevice',
        credentialBackedUp: false,
        origin: 'https://app.example.test',
        rpID: 'wallet.example.test',
      },
    });
    const request = fakeRequest();
    await service.authenticationOptions(request);
    await service.authenticate(authenticationCredential('credential-id'), false, request);

    expect(verifyAuthentication).toHaveBeenCalledWith(
      expect.objectContaining({
        expectedOrigin: ['https://app.example.test'],
        expectedRPID: 'wallet.example.test',
        expectedChallenge: 'synthetic-challenge',
        requireUserVerification: true,
      }),
    );
    expect(repository.updatePasskeyCounter).toHaveBeenCalledWith(
      'passkey-id',
      1,
      0,
      2,
      expect.any(Date),
    );
  });

  it('rejects expired and single-use challenges before credential state changes', async () => {
    const request = fakeRequest();
    request.session.webauthn = {
      challenge: 'expired',
      expiresAt: new Date(Date.now() - 1).toISOString(),
      flow: 'authentication',
    };
    await expect(
      service.authenticate(authenticationCredential('credential-id'), false, request),
    ).rejects.toMatchObject({ code: 'UNAUTHORIZED' });
    expect(verifyAuthentication).not.toHaveBeenCalled();
    expect(repository.updatePasskeyCounter).not.toHaveBeenCalled();
    expect(request.session.webauthn).toBeUndefined();
  });
});

function fakeRequest(): Request {
  return {
    ip: '127.0.0.1',
    get: () => 'synthetic-agent',
    session: {
      cookie: {} as never,
      save: (callback: (error?: unknown) => void) => callback(),
    },
  } as unknown as Request;
}

function authenticationCredential(id: string): PasskeyAuthenticationCredentialDto {
  return {
    id,
    rawId: id,
    type: 'public-key' as const,
    authenticatorAttachment: 'platform' as const,
    response: {
      clientDataJSON: 'Y2xpZW50',
      authenticatorData: 'YXV0aGVudGljYXRvcg',
      signature: 'c2lnbmF0dXJl',
      userHandle: 'dXNlcg',
    },
    clientExtensionResults: {},
  };
}

function registrationCredential(): PasskeyRegistrationCredentialDto {
  return {
    id: 'cmVnaXN0ZXJlZC1jcmVkZW50aWFs',
    rawId: 'cmVnaXN0ZXJlZC1jcmVkZW50aWFs',
    type: 'public-key' as const,
    authenticatorAttachment: 'platform' as const,
    response: {
      clientDataJSON: 'Y2xpZW50',
      attestationObject: 'YXR0ZXN0YXRpb24',
      transports: ['internal' as const],
    },
    clientExtensionResults: { credProps: { rk: true } },
  };
}
