import { readFileSync } from 'node:fs';

interface Operation {
  security?: Array<Record<string, unknown>>;
  requestBody?: {
    content?: Record<string, { schema?: Schema }>;
  };
  responses?: Record<string, { content?: Record<string, { schema?: Schema }> }>;
  parameters?: Array<{ name?: string; schema?: Schema }>;
}

interface Schema {
  $ref?: string;
  type?: string;
  format?: string;
  properties?: Record<string, Schema>;
  required?: string[];
}

interface Contract {
  paths: Record<string, Record<string, Operation>>;
  components: { schemas: Record<string, Schema> };
}

describe('CG-001 OpenAPI contract', () => {
  const contract = JSON.parse(readFileSync('apps/api/openapi/openapi.json', 'utf8')) as Contract;

  it('publishes typed option, registration, and owned-list responses', () => {
    expect(responseSchema('post', '/api/v1/auth/passkeys/registration-options', '201')).toEqual({
      $ref: '#/components/schemas/PasskeyRegistrationOptionsResponseDto',
    });
    expect(responseSchema('post', '/api/v1/auth/passkeys', '201')).toEqual({
      $ref: '#/components/schemas/PasskeyRegistrationResponseDto',
    });
    expect(responseSchema('post', '/api/v1/auth/passkey-sessions/options', '201')).toEqual({
      $ref: '#/components/schemas/PasskeyAuthenticationOptionsResponseDto',
    });
    expect(responseSchema('get', '/api/v1/auth/passkeys', '200')).toEqual({
      $ref: '#/components/schemas/PasskeyListResponseDto',
    });
  });

  it('publishes complete nested WebAuthn registration and authentication credentials', () => {
    expect(requestSchema('post', '/api/v1/auth/passkeys')).toEqual({
      $ref: '#/components/schemas/PasskeyLabelDto',
    });
    expect(requestSchema('post', '/api/v1/auth/passkey-sessions')).toEqual({
      $ref: '#/components/schemas/PasskeyAuthenticationDto',
    });

    expect(contract.components.schemas.PasskeyLabelDto?.properties?.credential).toEqual({
      $ref: '#/components/schemas/PasskeyRegistrationCredentialDto',
    });
    expect(contract.components.schemas.PasskeyAuthenticationDto?.properties?.credential).toEqual({
      $ref: '#/components/schemas/PasskeyAuthenticationCredentialDto',
    });
    expect(
      contract.components.schemas.PasskeyRegistrationCredentialDto?.properties?.response,
    ).toEqual({ $ref: '#/components/schemas/PasskeyRegistrationCredentialResponseDto' });
    expect(
      contract.components.schemas.PasskeyAuthenticationCredentialDto?.properties?.response,
    ).toEqual({ $ref: '#/components/schemas/PasskeyAuthenticationCredentialResponseDto' });

    for (const name of [
      'PasskeyRegistrationCredentialDto',
      'PasskeyRegistrationCredentialResponseDto',
      'PasskeyAuthenticationCredentialDto',
      'PasskeyAuthenticationCredentialResponseDto',
    ]) {
      const schema = contract.components.schemas[name];
      expect(schema?.type).toBe('object');
      expect(Object.keys(schema?.properties ?? {})).not.toHaveLength(0);
      expect(schema?.required?.length).toBeGreaterThan(0);
    }
  });

  it('requires cookie authentication and a UUID server identifier for list and deletion', () => {
    const list = operation('get', '/api/v1/auth/passkeys');
    const deletion = operation('delete', '/api/v1/auth/passkeys/{id}');
    expect(list.security).toEqual([{ cookie: [] }]);
    expect(deletion.security).toEqual([{ cookie: [] }]);
    expect(deletion.parameters).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          name: 'id',
          schema: expect.objectContaining({ format: 'uuid' }),
        }),
      ]),
    );
  });

  function operation(method: string, path: string): Operation {
    const value = contract.paths[path]?.[method];
    if (!value) throw new Error(`Missing ${method.toUpperCase()} ${path}`);
    return value;
  }

  function responseSchema(method: string, path: string, status: string): Schema | undefined {
    return operation(method, path).responses?.[status]?.content?.['application/json']?.schema;
  }

  function requestSchema(method: string, path: string): Schema | undefined {
    return operation(method, path).requestBody?.content?.['application/json']?.schema;
  }
});
