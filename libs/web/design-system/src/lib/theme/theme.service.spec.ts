import { TestBed } from '@angular/core/testing';
import {
  DEFAULT_PALETTE_ID,
  DISPLAY_MODE_STORAGE_KEY,
  isDisplayMode,
  isPaletteId,
  PALETTE_IDS,
  resolveDisplayMode,
} from './theme.types';
import { ThemeService } from './theme.service';

class MockMediaQueryList extends EventTarget implements MediaQueryList {
  readonly media = '(prefers-color-scheme: dark)';
  onchange: ((this: MediaQueryList, event: MediaQueryListEvent) => unknown) | null = null;

  constructor(public matches: boolean) {
    super();
  }

  addListener(): void {}

  removeListener(): void {}

  dispatchDarkMode(matches: boolean): void {
    this.matches = matches;
    const event = new Event('change') as MediaQueryListEvent;
    Object.defineProperty(event, 'matches', { value: matches });
    this.dispatchEvent(event);
  }
}

describe('ThemeService', () => {
  let mediaQuery: MockMediaQueryList;

  beforeEach(() => {
    window.localStorage.clear();
    mediaQuery = new MockMediaQueryList(false);
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: () => mediaQuery,
    });
    document.documentElement.removeAttribute('data-display-mode');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-palette');
    TestBed.configureTestingModule({});
  });

  it('registers the approved display modes and palettes', () => {
    expect(isDisplayMode('system')).toBe(true);
    expect(isDisplayMode('light')).toBe(true);
    expect(isDisplayMode('dark')).toBe(true);
    expect(isDisplayMode('sepia')).toBe(false);
    expect(PALETTE_IDS).toEqual([
      'blue',
      'green',
      'purple',
      'orange',
      'teal',
      'indigo',
      'pink',
      'red',
    ]);
    expect(PALETTE_IDS.every(isPaletteId)).toBe(true);
  });

  it('defaults to system mode and follows media-query changes', () => {
    const service = TestBed.inject(ThemeService);
    expect(service.mode()).toBe('system');
    expect(service.resolvedMode()).toBe('light');
    expect(document.documentElement.dataset['palette']).toBe(DEFAULT_PALETTE_ID);

    mediaQuery.dispatchDarkMode(true);

    expect(service.resolvedMode()).toBe('dark');
    expect(document.documentElement.dataset['theme']).toBe('dark');
  });

  it('persists only the validated device display mode', () => {
    const service = TestBed.inject(ThemeService);
    service.setMode('dark');
    service.setPalette('purple');

    expect(window.localStorage.length).toBe(1);
    expect(window.localStorage.getItem(DISPLAY_MODE_STORAGE_KEY)).toBe('dark');
    expect(document.documentElement.dataset['palette']).toBe('purple');
  });

  it('ignores an invalid stored display mode', () => {
    window.localStorage.setItem(DISPLAY_MODE_STORAGE_KEY, 'invalid');
    const service = TestBed.inject(ThemeService);

    expect(service.mode()).toBe('system');
    expect(window.localStorage.length).toBe(1);
  });

  it('resolves system, light, and dark without numeric coercion', () => {
    expect(resolveDisplayMode('system', true)).toBe('dark');
    expect(resolveDisplayMode('system', false)).toBe('light');
    expect(resolveDisplayMode('light', true)).toBe('light');
    expect(resolveDisplayMode('dark', false)).toBe('dark');
  });
});
