import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

const routes = ['/auth', '/onboarding', '/app', '/admin', '/missing'];
const palettes = ['blue', 'green', 'purple', 'orange', 'teal', 'indigo', 'pink', 'red'];

async function assertNoHorizontalPageScroll(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}

function channel(hex: string, offset: number): number {
  return Number.parseInt(hex.slice(offset, offset + 2), 16) / 255;
}

function luminance(hex: string): number {
  const channels = [channel(hex, 1), channel(hex, 3), channel(hex, 5)].map((value) =>
    value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4,
  );
  return 0.2126 * (channels[0] ?? 0) + 0.7152 * (channels[1] ?? 0) + 0.0722 * (channels[2] ?? 0);
}

function contrast(foreground: string, background: string): number {
  const foregroundLuminance = luminance(foreground);
  const backgroundLuminance = luminance(background);
  const lighter = Math.max(foregroundLuminance, backgroundLuminance);
  const darker = Math.min(foregroundLuminance, backgroundLuminance);
  return (lighter + 0.05) / (darker + 0.05);
}

test('serves the responsive localized shell hierarchy accessibly', async ({ page }) => {
  for (const route of routes) {
    await page.goto(route);
    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.locator('h1')).toHaveCount(1);
    await assertNoHorizontalPageScroll(page);
    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);
  }
});

test('exposes keyboard focus and reduced-motion behavior', async ({ browserName, page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/auth');
  const focusShortcut = {
    chromium: 'Tab',
    firefox: 'Tab',
    webkit: 'Alt+Tab',
  }[browserName];
  await page.keyboard.press(focusShortcut);

  const skipLink = page.locator('.skip-link');
  await expect(skipLink).toBeFocused();
  expect((await skipLink.boundingBox())?.y).toBeGreaterThanOrEqual(0);
  await expect(page.locator('body')).toHaveCSS(
    'animation-duration',
    /^(0\.001ms|0\.000001s|1e-06s)$/,
  );
});

test('applies display mode before bootstrap and persists only the mode key', async ({ page }) => {
  await page.addInitScript(() => {
    window.localStorage.setItem('mymoneymap.display-mode.v1', 'dark');
  });
  await page.goto('/auth', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('html')).toHaveAttribute('data-display-mode', 'dark');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  expect(
    await page.evaluate(() =>
      Object.keys(window.localStorage).filter((key) => key.startsWith('mymoneymap.')),
    ),
  ).toEqual(['mymoneymap.display-mode.v1']);
});

test('registers every palette in light and dark with stable status meaning and AA text contrast', async ({
  page,
}) => {
  await page.goto('/auth');
  const statusesByMode: Record<string, string[]> = { dark: [], light: [] };

  for (const mode of ['light', 'dark']) {
    for (const palette of palettes) {
      const tokens = await page.evaluate(
        ({ selectedMode, selectedPalette }) => {
          const root = document.documentElement;
          root.dataset['theme'] = selectedMode;
          root.dataset['palette'] = selectedPalette;
          const styles = getComputedStyle(root);
          return {
            accent: styles.getPropertyValue('--accent').trim(),
            background: styles.getPropertyValue('--surface-card').trim(),
            status: styles.getPropertyValue('--status-success').trim(),
            text: styles.getPropertyValue('--text-primary').trim(),
          };
        },
        { selectedMode: mode, selectedPalette: palette },
      );

      expect(tokens.accent).toMatch(/^#[\da-f]{6}$/i);
      expect(contrast(tokens.text, tokens.background)).toBeGreaterThanOrEqual(4.5);
      statusesByMode[mode]?.push(tokens.status);
    }
  }

  expect(new Set(statusesByMode['light']).size).toBe(1);
  expect(new Set(statusesByMode['dark']).size).toBe(1);
});

test('uses the configured same-origin development proxy', async ({ request }) => {
  const response = await request.get('/api/v1/health/live');
  expect(response.status()).toBe(200);
  expect(response.headers()['x-step-01-synthetic-proxy']).toBe('true');
  expect(await response.json()).toEqual({ status: 'ok' });
});

test('keeps the shell layout visually stable at each supported browser viewport', async ({
  page,
}) => {
  await page.goto('/app');
  await expect(page).toHaveScreenshot('product-shell.png', {
    stylePath: 'apps/web-app-e2e/visual-stability.css',
  });
});
