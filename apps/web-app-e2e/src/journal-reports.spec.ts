import AxeBuilder from '@axe-core/playwright';
import type { JournalEntryResponseDto } from '@mymoneymap/generated-api-client/models/journal-entry-response-dto';
import type { MonthReportResponseDto } from '@mymoneymap/generated-api-client/models/month-report-response-dto';
import { expect, test, type Page } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page
    .context()
    .addCookies([{ name: 'mmm-e2e-session', value: 'personal', url: 'http://localhost:4200' }]);
  await mockJournalAndReports(page);
});

test('filters, reviews, posts, inspects, corrects, and reverses immutable activity', async ({
  page,
}) => {
  await page.goto('/app/activity?dateFrom=2026-07-01&dateTo=2026-07-31');
  await expect(page.getByRole('heading', { level: 1, name: 'Activity' })).toBeVisible();
  await expect(page.getByText('123,456,789,012,345,678.12345678 EUR')).toBeVisible();
  await expect(page.locator('.journal-amount small')).toHaveText('Unavailable');
  await page.getByRole('link', { name: 'External expense' }).click();
  await expect(page.getByText('Balanced legs', { exact: true })).toBeVisible();
  await expect(page.getByText(/manual/)).toBeVisible();

  await page.goto('/app/activity/new');
  await page.getByLabel('Exact amount').fill('999999999999999999.00000001');
  await page.getByLabel('Currency').fill('eur');
  await page.getByLabel('Posted date').fill('2026-07-22');
  await page.getByLabel('Account identifier').fill('00000000-0000-4000-8000-000000000002');
  await page.getByRole('button', { name: 'Review activity' }).click();
  await expect(page.getByRole('dialog')).toContainText('999999999999999999.00000001 EUR');
  await page.getByRole('button', { name: 'Post activity' }).click();
  await expect(page).toHaveURL(/\/app\/activity\//);

  await page.goto('/app/reports/2026/7');
  await expect(page.getByRole('heading', { level: 1, name: /July 2026/ })).toBeVisible();

  await page.goto('/app/activity/00000000-0000-4000-8000-000000000010/correct');
  await expect(page.getByLabel('Exact amount')).toHaveValue('123456789012345678.12345678');
  await page.getByLabel('Exact amount').fill('123456789012345678.00000001');
  await page.getByRole('button', { name: 'Review activity' }).click();
  await page.getByRole('button', { name: 'Post activity' }).click();
  await expect(page).toHaveURL('/app/activity/00000000-0000-4000-8000-000000000021');

  await page.goto('/app/activity/00000000-0000-4000-8000-000000000010/reverse');
  await expect(page.getByText(/never edits or deletes the original/i)).toBeVisible();
  await page.getByLabel('Posted date').fill('2026-07-23');
  await page.getByRole('button', { name: 'Post reversal' }).click();
  const reversalDialog = page.getByRole('dialog');
  await expect(reversalDialog).toContainText(/never edits or deletes the original/i);
  await reversalDialog.getByRole('button', { name: 'Post reversal' }).click();
  await expect(page).toHaveURL('/app/activity/00000000-0000-4000-8000-000000000022');
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('keeps report filters in the URL, shows partial data, and works at mobile and desktop sizes', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto('/app/reports/2026/7?kind=expense&minAmount=0.00000001');
  await expect(page.getByRole('heading', { level: 1, name: /July 2026/ })).toBeVisible();
  await expect(page.getByText(/No missing value is shown as zero/i)).toBeVisible();
  await expect(page.locator('.activity-section .activity-amount strong')).toHaveText('Unavailable');
  await expect(page.getByRole('combobox', { name: 'Activity kind' })).toHaveText('Expense');
  await assertNoHorizontalScroll(page);
  await page.setViewportSize({ width: 1280, height: 800 });
  await assertNoHorizontalScroll(page);
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

async function mockJournalAndReports(page: Page): Promise<void> {
  await page.route('**/api/v1/users/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        id: '00000000-0000-4000-8000-000000000001',
        email: 'synthetic@example.test',
        fullName: 'Synthetic User',
        dateOfBirth: '1990-01-01',
        desiredLanguage: 'en',
        emailVerified: true,
        role: 'free',
        theme: 'verdant-horizon',
        entitlements: {
          administration: false,
          cashFlowRuleEditing: false,
          personalFinanceAccess: true,
          resources: Object.fromEntries(
            ['activeGoals', 'activeLoans', 'activeScheduledItems', 'categories', 'currencies'].map(
              (resource) => [resource, { allowed: true, limit: 2 }],
            ),
          ),
        },
      }),
    });
  });
  await page.route('**/api/v1/users/me/onboarding', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        currentStep: 6,
        next: 'complete',
        onboardingComplete: true,
        tutorialCompleted: true,
        tutorialRequired: false,
      }),
    });
  });
  await page.route('**/api/v1/journal/entries**', async (route) => {
    if (route.request().method() === 'POST') {
      expect(route.request().headers()['idempotency-key']).toBeTruthy();
      const url = route.request().url();
      const response = url.endsWith('/corrections')
        ? {
            reversal: { ...entry(), id: '00000000-0000-4000-8000-000000000020' },
            replacement: {
              ...entry(),
              id: '00000000-0000-4000-8000-000000000021',
              replacesEntryId: '00000000-0000-4000-8000-000000000010',
            },
          }
        : url.endsWith('/reversals')
          ? {
              ...entry(),
              id: '00000000-0000-4000-8000-000000000022',
              reversesEntryId: '00000000-0000-4000-8000-000000000010',
            }
          : entry();
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify(response),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [entry()], nextCursor: null }),
    });
  });
  await page.route('**/api/v1/reports/months/2026/7**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(monthReport()),
    });
  });
  await page.route('**/api/v1/reports/months/current**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(monthReport()),
    });
  });
  await page.route('**/api/v1/reports/years/2026', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ...monthReport(), months: [] }),
    });
  });
  await page.route('**/api/v1/reports/years', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [{ year: 2026 }] }),
    });
  });
}

function entry(): JournalEntryResponseDto {
  return {
    actorUserId: '00000000-0000-4000-8000-000000000001',
    categoryId: null,
    createdAt: '2026-07-20T10:00:00.000Z',
    economicType: 'external_expense',
    effectiveAt: '2026-07-20T10:00:00.000Z',
    id: '00000000-0000-4000-8000-000000000010',
    legs: [
      {
        id: '00000000-0000-4000-8000-000000000011',
        accountId: '00000000-0000-4000-8000-000000000002',
        amount: '123456789012345678.12345678',
        currency: 'EUR',
        side: 'credit',
      },
      {
        id: '00000000-0000-4000-8000-000000000012',
        accountId: null,
        amount: '123456789012345678.12345678',
        currency: 'EUR',
        side: 'debit',
      },
    ],
    note: null,
    postedOn: '2026-07-20',
    replacesEntryId: null,
    reversesEntryId: null,
    source: { module: 'manual', referenceId: null },
    conversion: {
      sourceAmount: '123456789012345678.12345678',
      sourceCurrency: 'EUR',
      targetCurrency: 'EUR',
      precision: 8,
      roundingMode: 'HALF_EVEN',
      status: 'unavailable',
    },
  };
}
function monthReport(): MonthReportResponseDto {
  const conversion = {
    complete: false,
    includedSourceCount: 1,
    newestFetchedAt: '2026-07-20T00:00:00.000Z',
    oldestRateAt: '2026-07-19T00:00:00.000Z',
    providers: ['synthetic'],
    staleSourceCount: 1,
    status: 'stale' as const,
    unavailableSourceCount: 1,
  };
  const summary = {
    adjustmentNet: '0.00000000',
    conversion,
    currency: 'EUR',
    expense: '250.00000000',
    income: '1000.00000000',
    netCashFlow: '750.00000000',
    tradeCashNet: '0.00000000',
    transfer: '500.00000000',
  };
  return {
    period: {
      first: '2026-07-01',
      last: '2026-07-31',
      month: 7,
      timeZone: 'Europe/Budapest',
      year: 2026,
    },
    posted: summary,
    forecast: { summary, sources: [] },
    combinedProjection: summary,
    budget: {
      allocation: { overAllocatedBy: '0', status: 'within_allocation', totalPercent: '100' },
      period: {
        currency: 'EUR',
        forecastIncome: '1000',
        forecastIncomeStatus: 'available',
        month: '2026-07',
      },
      items: [],
    },
    activity: {
      items: [
        {
          amount: '500.00000000',
          categoryId: null,
          conversionStatus: 'unavailable',
          currency: 'USD',
          economicType: 'internal_transfer',
          effectiveAt: '2026-07-20T00:00:00.000Z',
          fetchedAt: null,
          kind: 'transfer',
          note: null,
          postedOn: '2026-07-20',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          reversesEntryId: null,
          source: { module: 'manual', referenceId: null },
          sourceEntryId: 'entry-one',
        },
      ],
      nextCursor: null,
    },
  };
}
async function assertNoHorizontalScroll(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}
