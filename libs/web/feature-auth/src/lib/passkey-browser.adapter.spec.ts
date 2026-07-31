import type { PasskeyAuthenticationOptionsResponseDto } from '@mymoneymap/generated-api-client/models/passkey-authentication-options-response-dto';
import type { PasskeyRegistrationOptionsResponseDto } from '@mymoneymap/generated-api-client/models/passkey-registration-options-response-dto';
import {
  PasskeyBrowserAdapter,
  PasskeyCancelledError,
  PasskeyUnsupportedError,
} from './passkey-browser.adapter';

class SyntheticAttestationResponse {
  readonly clientDataJSON = Uint8Array.from([1, 2, 3]).buffer;
  readonly attestationObject = Uint8Array.from([4, 5, 6]).buffer;
  getTransports(): AuthenticatorTransport[] {
    return ['internal'];
  }
}

class SyntheticAssertionResponse {
  readonly clientDataJSON = Uint8Array.from([1, 2, 3]).buffer;
  readonly authenticatorData = Uint8Array.from([4, 5, 6]).buffer;
  readonly signature = Uint8Array.from([7, 8, 9]).buffer;
  readonly userHandle = Uint8Array.from([10, 11]).buffer;
}

class SyntheticCredential {
  readonly id = 'credential-id';
  readonly rawId = Uint8Array.from([12, 13, 14]).buffer;
  readonly type = 'public-key';
  readonly authenticatorAttachment = 'platform';
  constructor(readonly response: SyntheticAttestationResponse | SyntheticAssertionResponse) {}
  getClientExtensionResults(): AuthenticationExtensionsClientOutputs {
    return {};
  }
}

describe('PasskeyBrowserAdapter', () => {
  const adapter = new PasskeyBrowserAdapter();

  afterEach(() => vi.unstubAllGlobals());

  it('reports unsupported browsers without calling a provider', async () => {
    vi.stubGlobal('PublicKeyCredential', undefined);

    expect(adapter.supported()).toBe(false);
    await expect(adapter.get(authenticationOptions())).rejects.toBeInstanceOf(
      PasskeyUnsupportedError,
    );
  });

  it('decodes registration options and serializes the browser credential as base64url', async () => {
    installWebAuthnGlobals();
    const create = vi.fn(({ publicKey }: CredentialCreationOptions) => {
      expect(bytes(publicKey?.challenge)).toEqual(Uint8Array.from([1, 2, 3]));
      expect(bytes(publicKey?.user.id)).toEqual(Uint8Array.from([4, 5, 6]));
      return Promise.resolve(new SyntheticCredential(new SyntheticAttestationResponse()));
    });
    Object.defineProperty(navigator, 'credentials', {
      configurable: true,
      value: { create, get: vi.fn() },
    });

    const result = await adapter.create(registrationOptions());

    expect(result).toEqual({
      authenticatorAttachment: 'platform',
      clientExtensionResults: {},
      id: 'credential-id',
      rawId: 'DA0O',
      response: {
        attestationObject: 'BAUG',
        clientDataJSON: 'AQID',
        transports: ['internal'],
      },
      type: 'public-key',
    });
  });

  it('serializes assertions and normalizes browser cancellation', async () => {
    installWebAuthnGlobals();
    const get = vi
      .fn()
      .mockResolvedValueOnce(new SyntheticCredential(new SyntheticAssertionResponse()))
      .mockRejectedValueOnce(new DOMException('Synthetic cancellation', 'NotAllowedError'));
    Object.defineProperty(navigator, 'credentials', {
      configurable: true,
      value: { create: vi.fn(), get },
    });

    await expect(adapter.get(authenticationOptions())).resolves.toMatchObject({
      rawId: 'DA0O',
      response: {
        authenticatorData: 'BAUG',
        clientDataJSON: 'AQID',
        signature: 'BwgJ',
        userHandle: 'Cgs',
      },
    });
    await expect(adapter.get(authenticationOptions())).rejects.toBeInstanceOf(
      PasskeyCancelledError,
    );
  });

  it('reports browser origin failures as a safe error rather than cancellation', async () => {
    installWebAuthnGlobals();
    const originFailure = new DOMException('Relying-party origin mismatch', 'SecurityError');
    Object.defineProperty(navigator, 'credentials', {
      configurable: true,
      value: { create: vi.fn(), get: vi.fn().mockRejectedValue(originFailure) },
    });

    await expect(adapter.get(authenticationOptions())).rejects.toMatchObject({
      message: 'PASSKEY_BROWSER_ERROR',
    });
  });
});

function installWebAuthnGlobals(): void {
  vi.stubGlobal('PublicKeyCredential', SyntheticCredential);
  vi.stubGlobal('AuthenticatorAttestationResponse', SyntheticAttestationResponse);
  vi.stubGlobal('AuthenticatorAssertionResponse', SyntheticAssertionResponse);
}

function bytes(value: BufferSource | undefined): Uint8Array {
  if (!value) return new Uint8Array();
  return value instanceof ArrayBuffer
    ? new Uint8Array(value)
    : new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
}

function registrationOptions(): PasskeyRegistrationOptionsResponseDto {
  return {
    challenge: 'AQID',
    pubKeyCredParams: [{ alg: -7, type: 'public-key' }],
    rp: { id: 'localhost', name: 'MyMoneyMap' },
    user: { displayName: 'Synthetic User', id: 'BAUG', name: 'synthetic@example.test' },
  };
}

function authenticationOptions(): PasskeyAuthenticationOptionsResponseDto {
  return { challenge: 'AQID', rpId: 'localhost', userVerification: 'preferred' };
}
