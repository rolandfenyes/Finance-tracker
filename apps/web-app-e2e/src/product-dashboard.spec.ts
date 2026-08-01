import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await setPersonalSession(page);
});

test('renders the authoritative dashboard states and keeps distinctions explicit', async ({
  page,
}) => {
  await page.goto('/app/home');

  await expect(page.getByRole('heading', { level: 1, name: 'Financial overview' })).toBeVisible();
  await expect(
    page.getByRole('region', { name: 'Posted cash flow' }).getByText('1,000 EUR'),
  ).toBeVisible();
  await expect(page.getByText(/not an account balance or net worth/i)).toBeVisible();
  await expect(page.getByText(/missing value is shown as zero/i)).toBeVisible();
  await expect(
    page.getByRole('region', { name: 'Activity preview' }).locator('.activity-amount strong'),
  ).toHaveText('Unavailable');

  await page.getByRole('button', { name: 'Forecast' }).click();
  await expect(page.getByRole('heading', { name: 'Forecast cash flow' })).toBeVisible();
  await page.getByRole('button', { name: 'Combined projection' }).click();
  await expect(page.getByRole('heading', { name: 'Combined cash-flow projection' })).toBeVisible();

  const activityLink = page.getByRole('link', { name: 'View activity' });
  await expect(activityLink).toHaveAttribute('href', /cursor=opaque-dashboard-cursor/);
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('renders a complete conversion state without a partial-data warning', async ({ page }) => {
  await page
    .context()
    .addCookies([{ name: 'mmm-e2e-report', value: 'complete', url: 'http://localhost:4200' }]);
  await page.goto('/app/home');

  await expect(page.getByText('Conversion complete')).toBeVisible();
  await expect(page.locator('.partial-data-banner')).toHaveCount(0);
  await expect(
    page.getByRole('region', { name: 'Activity preview' }).locator('.activity-amount strong'),
  ).toHaveText('146.25 EUR');
});

test('uses mobile bottom navigation and desktop sidebar without horizontal overflow', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto('/app/home');
  await expect(page.locator('.product-bottom-navigation')).toBeVisible();
  await expect(page.locator('.product-sidebar')).toBeHidden();
  await assertNoHorizontalScroll(page);

  await page.setViewportSize({ width: 768, height: 1024 });
  await expect(page.locator('.product-sidebar')).toBeVisible();
  await expect(page.locator('.product-bottom-navigation')).toBeHidden();
  await expect(page.locator('.sidebar-toggle')).toBeHidden();
  await assertNoHorizontalScroll(page);

  await page.setViewportSize({ width: 1280, height: 800 });
  await expect(page.locator('.product-sidebar')).toBeVisible();
  await expect(page.locator('.product-bottom-navigation')).toBeHidden();
  await page.getByRole('button', { name: 'Collapse navigation' }).click();
  await expect(page.locator('.product-app-shell')).toHaveClass(/sidebar-collapsed/);
  await assertNoHorizontalScroll(page);
});

test('renders empty, error retry, partial, and locale fallback states', async ({ page }) => {
  await page.route('**/api/v1/reports/months/current**', async (route) => {
    await route.fulfill({
      status: 503,
      headers: { 'content-type': 'application/json', 'x-request-id': 'safe-e2e-reference' },
      body: JSON.stringify({ error: { code: 'UNAVAILABLE' } }),
    });
  });
  await page.goto('/app/home');
  await expect(page.getByRole('alert')).toContainText('Something went wrong');
  await expect(page.getByText('safe-e2e-reference')).toBeVisible();
  const retry = page.getByRole('button', { name: 'Try again' });
  await expect(retry).toBeVisible();

  await page.unroute('**/api/v1/reports/months/current**');
  await retry.click();
  await expect(page.getByRole('heading', { name: 'Posted cash flow' })).toBeVisible();
  await expect(page.getByText(/Some totals exclude sources/i)).toBeVisible();

  await page.route('**/api/v1/reports/months/current**', async (route) => {
    await route.fulfill({
      status: 403,
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ error: { code: 'FORBIDDEN' } }),
    });
  });
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Dashboard unavailable' })).toBeVisible();

  await page.unroute('**/api/v1/reports/months/current**');
  await page.evaluate(() => document.documentElement.setAttribute('lang', 'xx'));
  await page.reload();
  await expect(page.getByRole('heading', { level: 1, name: 'Financial overview' })).toBeVisible();
});

test('retains exact financial strings across themes and exposes the chart table', async ({
  page,
}) => {
  await page.addInitScript(() => localStorage.setItem('mymoneymap.display-mode.v1', 'dark'));
  await page.goto('/app/home');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.getByRole('table', { name: /Accessible table alternative/i })).toContainText(
    '874.25 EUR',
  );
  expect(
    await page.evaluate(() =>
      [...Object.keys(localStorage), ...Object.keys(sessionStorage)].filter((key) =>
        /(token|session|jwt|financial|report)/i.test(key),
      ),
    ),
  ).toEqual([]);
});

async function setPersonalSession(page: Page): Promise<void> {
  await page
    .context()
    .addCookies([{ name: 'mmm-e2e-session', value: 'personal', url: 'http://localhost:4200' }]);
}

async function assertNoHorizontalScroll(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}
