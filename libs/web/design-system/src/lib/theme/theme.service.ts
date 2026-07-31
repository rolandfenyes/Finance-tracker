import { DOCUMENT } from '@angular/common';
import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import {
  DEFAULT_DISPLAY_MODE,
  DEFAULT_PALETTE_ID,
  DISPLAY_MODE_STORAGE_KEY,
  type DisplayMode,
  isDisplayMode,
  isPaletteId,
  type PaletteId,
  resolveDisplayMode,
  type ResolvedDisplayMode,
} from './theme.types';

const DARK_MEDIA_QUERY = '(prefers-color-scheme: dark)';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  private readonly destroyRef = inject(DestroyRef);
  private readonly view = this.document.defaultView;
  private readonly mediaQuery = this.view?.matchMedia?.(DARK_MEDIA_QUERY) ?? null;
  private readonly prefersDark = signal(this.mediaQuery?.matches ?? false);
  private readonly selectedMode = signal<DisplayMode>(this.readStoredMode());
  private readonly selectedPalette = signal<PaletteId>(DEFAULT_PALETTE_ID);

  readonly mode = this.selectedMode.asReadonly();
  readonly palette = this.selectedPalette.asReadonly();
  readonly resolvedMode = computed<ResolvedDisplayMode>(() =>
    resolveDisplayMode(this.selectedMode(), this.prefersDark()),
  );

  constructor() {
    this.applyTheme();

    const listener = (event: MediaQueryListEvent): void => {
      this.prefersDark.set(event.matches);
      if (this.selectedMode() === 'system') {
        this.applyTheme();
      }
    };

    this.mediaQuery?.addEventListener('change', listener);
    this.destroyRef.onDestroy(() => this.mediaQuery?.removeEventListener('change', listener));
  }

  setMode(mode: DisplayMode): void {
    this.selectedMode.set(mode);
    this.safeStorage()?.setItem(DISPLAY_MODE_STORAGE_KEY, mode);
    this.applyTheme();
  }

  setPalette(palette: PaletteId): void {
    if (!isPaletteId(palette)) {
      return;
    }

    this.selectedPalette.set(palette);
    this.applyTheme();
  }

  private readStoredMode(): DisplayMode {
    const stored = this.safeStorage()?.getItem(DISPLAY_MODE_STORAGE_KEY) ?? null;
    return isDisplayMode(stored) ? stored : DEFAULT_DISPLAY_MODE;
  }

  private safeStorage(): Storage | null {
    try {
      return this.view?.localStorage ?? null;
    } catch {
      return null;
    }
  }

  private applyTheme(): void {
    const root = this.document.documentElement;
    root.dataset['displayMode'] = this.selectedMode();
    root.dataset['theme'] = this.resolvedMode();
    root.dataset['palette'] = this.selectedPalette();
    root.style.colorScheme = this.resolvedMode();
  }
}
