import { Injectable } from '@angular/core';
import type { PasskeyAuthenticationCredentialDto } from '@mymoneymap/generated-api-client/models/passkey-authentication-credential-dto';
import type { PasskeyAuthenticationOptionsResponseDto } from '@mymoneymap/generated-api-client/models/passkey-authentication-options-response-dto';
import type { PasskeyRegistrationCredentialDto } from '@mymoneymap/generated-api-client/models/passkey-registration-credential-dto';
import type { PasskeyRegistrationOptionsResponseDto } from '@mymoneymap/generated-api-client/models/passkey-registration-options-response-dto';

export class PasskeyUnsupportedError extends Error {
  constructor() {
    super('PASSKEY_UNSUPPORTED');
    this.name = 'PasskeyUnsupportedError';
  }
}

export class PasskeyCancelledError extends Error {
  constructor() {
    super('PASSKEY_CANCELLED');
    this.name = 'PasskeyCancelledError';
  }
}

@Injectable({ providedIn: 'root' })
export class PasskeyBrowserAdapter {
  supported(): boolean {
    return (
      typeof window !== 'undefined' &&
      typeof window.PublicKeyCredential !== 'undefined' &&
      typeof navigator.credentials?.create === 'function' &&
      typeof navigator.credentials?.get === 'function'
    );
  }

  async create(
    options: PasskeyRegistrationOptionsResponseDto,
  ): Promise<PasskeyRegistrationCredentialDto> {
    this.assertSupported();
    try {
      const credential = await navigator.credentials.create({
        publicKey: {
          ...options,
          challenge: decodeBase64Url(options.challenge),
          user: { ...options.user, id: decodeBase64Url(options.user.id) },
          excludeCredentials: options.excludeCredentials?.map((item) => ({
            type: item.type,
            id: decodeBase64Url(item.id),
            transports: item.transports?.filter(isAuthenticatorTransport),
          })),
        },
      });
      if (!(credential instanceof PublicKeyCredential)) throw new PasskeyCancelledError();
      if (!(credential.response instanceof AuthenticatorAttestationResponse)) {
        throw new PasskeyCancelledError();
      }
      return {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: 'public-key',
        authenticatorAttachment: normalizeAttachment(credential.authenticatorAttachment),
        response: {
          clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
          attestationObject: encodeBase64Url(credential.response.attestationObject),
          transports: credential.response.getTransports?.().filter(isCredentialTransport),
        },
        clientExtensionResults: serializeExtensions(credential.getClientExtensionResults()),
      };
    } catch (error: unknown) {
      throw normalizeWebAuthnError(error);
    }
  }

  async get(
    options: PasskeyAuthenticationOptionsResponseDto,
  ): Promise<PasskeyAuthenticationCredentialDto> {
    this.assertSupported();
    try {
      const credential = await navigator.credentials.get({
        publicKey: {
          ...options,
          challenge: decodeBase64Url(options.challenge),
          allowCredentials: options.allowCredentials?.map((item) => ({
            type: item.type,
            id: decodeBase64Url(item.id),
            transports: item.transports?.filter(isAuthenticatorTransport),
          })),
        },
      });
      if (!(credential instanceof PublicKeyCredential)) throw new PasskeyCancelledError();
      if (!(credential.response instanceof AuthenticatorAssertionResponse)) {
        throw new PasskeyCancelledError();
      }
      return {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: 'public-key',
        authenticatorAttachment: normalizeAttachment(credential.authenticatorAttachment),
        response: {
          clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
          authenticatorData: encodeBase64Url(credential.response.authenticatorData),
          signature: encodeBase64Url(credential.response.signature),
          userHandle: credential.response.userHandle
            ? encodeBase64Url(credential.response.userHandle)
            : undefined,
        },
        clientExtensionResults: serializeExtensions(credential.getClientExtensionResults()),
      };
    } catch (error: unknown) {
      throw normalizeWebAuthnError(error);
    }
  }

  private assertSupported(): void {
    if (!this.supported()) throw new PasskeyUnsupportedError();
  }
}

function isAuthenticatorTransport(value: string): value is AuthenticatorTransport {
  return ['ble', 'hybrid', 'internal', 'nfc', 'usb'].includes(value);
}

function isCredentialTransport(
  value: string,
): value is 'ble' | 'cable' | 'hybrid' | 'internal' | 'nfc' | 'smart-card' | 'usb' {
  return ['ble', 'cable', 'hybrid', 'internal', 'nfc', 'smart-card', 'usb'].includes(value);
}

function normalizeAttachment(value: string | null): 'cross-platform' | 'platform' | undefined {
  return value === 'cross-platform' || value === 'platform' ? value : undefined;
}

function decodeBase64Url(value: string): ArrayBuffer {
  const padding = '='.repeat((4 - (value.length % 4)) % 4);
  const bytes = Uint8Array.from(
    atob(value.replace(/-/g, '+').replace(/_/g, '/') + padding),
    (char) => char.charCodeAt(0),
  );
  return bytes.buffer;
}

function encodeBase64Url(value: ArrayBuffer): string {
  const bytes = new Uint8Array(value);
  let binary = '';
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/u, '');
}

function serializeExtensions(value: AuthenticationExtensionsClientOutputs): {
  appid?: boolean;
  credProps?: { rk?: boolean };
  hmacCreateSecret?: boolean;
} {
  const extension = value as AuthenticationExtensionsClientOutputs & {
    credProps?: { rk?: boolean };
    hmacCreateSecret?: boolean;
  };
  return {
    appid: extension.appid,
    credProps: extension.credProps,
    hmacCreateSecret: extension.hmacCreateSecret,
  };
}

function normalizeWebAuthnError(error: unknown): Error {
  if (error instanceof PasskeyUnsupportedError || error instanceof PasskeyCancelledError) {
    return error;
  }
  if (
    error instanceof DOMException &&
    (error.name === 'NotAllowedError' || error.name === 'AbortError')
  ) {
    return new PasskeyCancelledError();
  }
  return error instanceof Error ? error : new Error('PASSKEY_BROWSER_ERROR');
}
