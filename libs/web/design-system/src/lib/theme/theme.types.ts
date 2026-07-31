export const DISPLAY_MODES = ['system', 'light', 'dark'] as const;
export const PALETTE_IDS = [
  'blue',
  'green',
  'purple',
  'orange',
  'teal',
  'indigo',
  'pink',
  'red',
] as const;

export type DisplayMode = (typeof DISPLAY_MODES)[number];
export type ResolvedDisplayMode = Exclude<DisplayMode, 'system'>;
export type PaletteId = (typeof PALETTE_IDS)[number];

export const DISPLAY_MODE_STORAGE_KEY = 'mymoneymap.display-mode.v1';
export const DEFAULT_DISPLAY_MODE: DisplayMode = 'system';
export const DEFAULT_PALETTE_ID: PaletteId = 'blue';

export function isDisplayMode(value: string | null): value is DisplayMode {
  return value !== null && DISPLAY_MODES.some((mode) => mode === value);
}

export function isPaletteId(value: string): value is PaletteId {
  return PALETTE_IDS.some((palette) => palette === value);
}

export function resolveDisplayMode(mode: DisplayMode, prefersDark: boolean): ResolvedDisplayMode {
  return mode === 'system' ? (prefersDark ? 'dark' : 'light') : mode;
}
