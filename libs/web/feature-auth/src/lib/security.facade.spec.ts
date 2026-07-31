import { TestBed } from '@angular/core/testing';
import { IdentityService } from '@mymoneymap/generated-api-client/services/identity.service';
import { of } from 'rxjs';
import { PasskeyBrowserAdapter } from './passkey-browser.adapter';
import { SecurityFacade } from './security.facade';

describe('SecurityFacade', () => {
  const serverId = '00000000-0000-4000-8000-000000000009';
  const item = {
    backedUp: false,
    createdAt: '2026-07-31T10:00:00.000Z',
    deviceType: 'singleDevice' as const,
    id: serverId,
    label: 'Laptop',
    lastUsedAt: null,
    transports: ['internal' as const],
  };
  const api = {
    identityControllerDeletePasskey: vi.fn(),
    identityControllerListPasskeys: vi.fn(),
    identityControllerRegisterPasskey: vi.fn(),
    identityControllerRegistrationOptions: vi.fn(),
  };
  const credential = {
    id: 'browser-credential-id',
    rawId: 'AQID',
    type: 'public-key' as const,
    response: { attestationObject: 'BAUG', clientDataJSON: 'BwgJ' },
    clientExtensionResults: {},
  };
  const browser = { create: vi.fn() };

  beforeEach(() => {
    vi.clearAllMocks();
    TestBed.configureTestingModule({
      providers: [
        SecurityFacade,
        { provide: IdentityService, useValue: api },
        { provide: PasskeyBrowserAdapter, useValue: browser },
      ],
    });
  });

  it('lists passkeys using only server-owned stable identifiers', async () => {
    api.identityControllerListPasskeys.mockReturnValue(of({ items: [item] }));
    const facade = TestBed.inject(SecurityFacade);

    await facade.load();

    expect(facade.items()).toEqual([item]);
    expect(facade.items()[0]?.id).toBe(serverId);
  });

  it('enrolls with the typed browser credential and refreshes the server list', async () => {
    const options = {
      challenge: 'AQID',
      pubKeyCredParams: [{ alg: -7, type: 'public-key' as const }],
      rp: { id: 'localhost', name: 'MyMoneyMap' },
      user: { displayName: 'Synthetic User', id: 'BAUG', name: 'synthetic@example.test' },
    };
    api.identityControllerRegistrationOptions.mockReturnValue(of(options));
    api.identityControllerRegisterPasskey.mockReturnValue(of({ id: serverId }));
    api.identityControllerListPasskeys.mockReturnValue(of({ items: [item] }));
    browser.create.mockResolvedValue(credential);
    const facade = TestBed.inject(SecurityFacade);

    await facade.enroll('Laptop');

    expect(browser.create).toHaveBeenCalledWith(options);
    expect(api.identityControllerRegisterPasskey).toHaveBeenCalledWith(
      { body: { credential, label: 'Laptop' } },
      expect.anything(),
    );
    expect(facade.items()).toEqual([item]);
  });

  it('deletes by the server-owned identifier and removes only that list entry', async () => {
    api.identityControllerListPasskeys.mockReturnValue(of({ items: [item] }));
    api.identityControllerDeletePasskey.mockReturnValue(of(undefined));
    const facade = TestBed.inject(SecurityFacade);
    await facade.load();

    await facade.remove(serverId);

    expect(api.identityControllerDeletePasskey).toHaveBeenCalledWith(
      { id: serverId },
      expect.anything(),
    );
    expect(facade.items()).toEqual([]);
  });
});
