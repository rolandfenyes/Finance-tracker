import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

test('registers without account enumeration and resends verification factually', async ({
  page,
}) => {
  await setSession(page, 'anonymous');
  await page.goto('/auth/register');
  await page.getByLabel('Email').fill('synthetic@example.test');
  await page.getByLabel('Full name').fill('Synthetic User');
  await page.getByLabel('Date of birth').fill('1990-01-01');
  await page.getByLabel('Password').fill('synthetic-password');
  await page.getByRole('button', { name: 'Create account' }).click();

  await expect(page).toHaveURL(/\/auth\/verification-sent$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Check your inbox');
  await page.getByLabel('Email').fill('unknown@example.test');
  await page.getByRole('button', { name: 'Send verification instructions' }).click();
  await expect(page.getByText('If the address can receive verification')).toBeVisible();
});

test('consumes verification without retaining its token and follows server onboarding state', async ({
  page,
}) => {
  await setSession(page, 'anonymous');
  const currentUserStatuses: number[] = [];
  page.on('response', (response) => {
    if (response.url().endsWith('/api/v1/users/me')) currentUserStatuses.push(response.status());
  });
  const verificationResponse = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/auth/email-verifications'),
  );
  const verificationOnboarding = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/users/me/onboarding'),
  );
  await page.goto('/auth/verify-email?token=synthetic-secret-token');
  expect((await verificationResponse).status()).toBe(204);
  await expect.poll(() => currentUserStatuses).toContain(200);
  expect((await verificationOnboarding).status()).toBe(200);

  await expect(page).toHaveURL(/\/onboarding\/theme$/);
  expect(page.url()).not.toContain('synthetic-secret-token');
  expect(await page.evaluate(() => document.documentElement.outerHTML)).not.toContain(
    'synthetic-secret-token',
  );
});

test('logs in with the server session and completes the server-directed onboarding journey', async ({
  page,
}) => {
  test.setTimeout(60_000);
  const pageErrors: string[] = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await setSession(page, 'anonymous');
  await page.goto('/auth/login');
  await page.getByLabel('Email').fill('synthetic@example.test');
  await page.getByLabel('Password').fill('synthetic-password');
  await page.getByRole('checkbox', { name: /Keep this server session/i }).check();
  const loginResponse = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/auth/sessions'),
  );
  const loginUser = page.waitForResponse((response) => response.url().endsWith('/api/v1/users/me'));
  const loginOnboarding = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/users/me/onboarding'),
  );
  await page.getByRole('button', { name: 'Sign in' }).click();
  expect((await loginResponse).status()).toBe(204);
  expect((await loginUser).status()).toBe(200);
  expect((await loginOnboarding).status()).toBe(200);
  await expect
    .poll(
      async () =>
        (await page.context().cookies()).find((item) => item.name === 'mmm-e2e-session')?.value,
    )
    .toBe('onboarding');
  expect(pageErrors).toEqual([]);
  await expect(page).toHaveURL(/\/onboarding\/theme$/);

  await page.getByRole('button', { name: /Verdant horizon/i }).click();
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/onboarding\/rules$/);

  await page.getByLabel('Label').fill('Needs');
  await page.getByLabel('Percent').fill('100');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/onboarding\/currencies$/);

  await page.getByLabel('Currency').click();
  await page.getByRole('option', { name: /EUR/ }).click();
  await page.getByRole('button', { name: 'Add currency' }).click();
  await expect(page).toHaveURL(/\/onboarding\/categories$/);

  await page.getByLabel('Category name').fill('Housing');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/onboarding\/income$/);

  await page.getByLabel('Income name').fill('Salary plan');
  await page.getByLabel('Exact amount').fill('2500.00');
  await page.getByLabel('Currency code').fill('EUR');
  await page.getByLabel('Valid from').fill('2026-01-01');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/onboarding\/tutorial$/);

  await expect(page.getByText(/planning baseline/i)).toHaveCount(0);
  await page.getByRole('button', { name: 'Finish setup' }).click();
  await expect(page).toHaveURL(/\/app$/);
  expect(await new AxeBuilder({ page }).analyze()).toMatchObject({ violations: [] });
});

test('shows throttling, passkey cancellation, and never stores session material', async ({
  page,
}) => {
  await setSession(page, 'anonymous');
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'credentials', {
      configurable: true,
      value: {
        create: () => Promise.resolve(null),
        get: () => Promise.reject(new DOMException('Synthetic cancellation', 'NotAllowedError')),
      },
    });
  });
  await page.route('**/api/v1/auth/sessions', async (route) => {
    await route.fulfill({
      status: 429,
      headers: { 'content-type': 'application/json', 'retry-after': '5' },
      body: JSON.stringify({ error: { code: 'RATE_LIMITED' } }),
    });
  });
  await page.goto('/auth/login');
  await page.getByLabel('Email').fill('synthetic@example.test');
  await page.getByLabel('Password').fill('synthetic-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByRole('alert')).toContainText(/too many attempts/i);

  await page.goto('/auth/passkey');
  await page.getByRole('button', { name: 'Continue with passkey' }).click();
  await expect(page.getByRole('alert')).toContainText(/cancelled/i);
  expect(
    await page.evaluate(() =>
      [...Object.keys(localStorage), ...Object.keys(sessionStorage)].filter((key) =>
        /(token|session|jwt|auth|credential)/i.test(key),
      ),
    ),
  ).toEqual([]);
});

async function setSession(page: Page, session: 'anonymous' | 'onboarding'): Promise<void> {
  await page.context().addCookies([
    { name: 'mmm-e2e-session', value: session, url: 'http://localhost:4200' },
    { name: 'mmm-e2e-step', value: 'theme', url: 'http://localhost:4200' },
  ]);
}
