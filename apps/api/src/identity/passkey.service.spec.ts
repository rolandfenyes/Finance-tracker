import {
  generateAuthenticationOptions,
  verifyAuthenticationResponse,
} from '@simplewebauthn/server';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import { PasskeyService } from './passkey.service';

jest.mock('@simplewebauthn/server', () => ({
  generateAuthenticationOptions: jest.fn(),
  generateRegistrationOptions: jest.fn(),
  verifyAuthenticationResponse: jest.fn(),
  verifyRegistrationResponse: jest.fn(),
}));

const generateAuthentication = jest.mocked(generateAuthenticationOptions);
const verifyAuthentication = jest.mocked(verifyAuthenticationResponse);

describe('PasskeyService security contract', () => {
  const repository = {
    findPasskeyByCredentialId: jest.fn(),
    findUserById: jest.fn(),
    updatePasskeyCounter: jest.fn(),
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
    await service.authenticate({ id: 'credential-id' }, false, request);

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
      service.authenticate({ id: 'credential-id' }, false, request),
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
