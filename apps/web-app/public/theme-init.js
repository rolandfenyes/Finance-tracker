/* global document, window */

(() => {
  const storageKey = 'mymoneymap.display-mode.v1';
  const allowedModes = new Set(['system', 'light', 'dark']);
  let mode = 'system';

  try {
    const storedMode = window.localStorage.getItem(storageKey);
    if (storedMode && allowedModes.has(storedMode)) {
      mode = storedMode;
    }
  } catch {
    mode = 'system';
  }

  const resolvedMode =
    mode === 'system'
      ? window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light'
      : mode;
  const root = document.documentElement;
  root.dataset.displayMode = mode;
  root.dataset.theme = resolvedMode;
  root.dataset.palette = 'blue';
  root.style.colorScheme = resolvedMode;
})();
