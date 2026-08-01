import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page
    .context()
    .addCookies([{ name: 'mmm-e2e-session', value: 'planning', url: 'http://localhost:4200' }]);
  await mockPlanning(page);
});

test('shows authoritative over-allocation, exact negative variance, and free-tier rule gating', async ({
  page,
}) => {
  await page.goto('/app/plan/budget');
  await expect(page.getByRole('heading', { level: 1, name: 'Budget plan' })).toBeVisible();
  await expect(page.getByText('101.0001%', { exact: true })).toBeVisible();
  await expect(page.getByText(/1.0001 percentage points over allocated/)).toBeVisible();
  await expect(page.getByText('-250.01 HUF')).toBeVisible();
  await expect(page.getByText(/editing rules is not included/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Edit' })).toHaveCount(0);
  await assertNoHorizontalScroll(page);
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('keeps server forecast dates distinct from posted activity at mobile and desktop', async ({
  page,
}) => {
  await page.setViewportSize({ width: 320, height: 800 });
  await page.goto('/app/plan/schedules');
  await page.getByLabel('Forecast from').fill('2028-02-01');
  await page.getByLabel('Forecast to').fill('2028-03-31');
  await page.getByRole('button', { name: 'Preview forecast' }).click();
  await expect(page.getByText('2028-02-29')).toBeVisible();
  await expect(page.getByText('2028-03-31')).toBeVisible();
  await expect(page.getByText(/Forecast occurrences — Not posted/)).toBeVisible();
  await expect(page.getByText('Managed by another module')).toBeVisible();
  await assertNoHorizontalScroll(page);
  await page.setViewportSize({ width: 1440, height: 900 });
  await assertNoHorizontalScroll(page);
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('shows category quota and keeps baseline income planning-only', async ({ page }) => {
  await page.goto('/app/plan/categories');
  await expect(page.getByText('10 / 10 used')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Save' })).toBeDisabled();
  await expect(page.getByText('Referenced by a budget rule')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Delete' }).nth(1)).toBeDisabled();
  await page.goto('/app/plan/income');
  await expect(page.getByText(/does not post a transaction/i)).toBeVisible();
  await expect(page.getByText('2500.0001 HUF')).toBeVisible();
});

test('rejects recurrence values outside the documented structured subset', async ({ page }) => {
  await page.goto('/app/plan/schedules/new');
  await page.getByLabel('Title').fill('Synthetic weekly plan');
  await page.getByLabel('Exact amount').fill('10.0001');
  await page.getByLabel('Currency code').click();
  await page.getByRole('option', { name: /HUF/ }).click();
  await page.getByLabel('Starts on').fill('2026-08-01');
  await page.getByLabel('Frequency').click();
  await page.getByRole('option', { name: 'WEEKLY' }).click();
  const weekdayInput = page.getByLabel(/Weekdays/);
  await weekdayInput.fill('MONDAY');
  await weekdayInput.press('Tab');
  await expect(weekdayInput).toHaveAttribute('aria-invalid', 'true');
  await expect(
    page.getByText('Use only the supported recurrence values shown by this form.'),
  ).toBeVisible();
  await expect(page.getByRole('button', { name: 'Save' })).toBeDisabled();
});

async function mockPlanning(page: Page): Promise<void> {
  await page.route('**/api/v1/users/me', (route) =>
    route.fulfill({
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
        theme: 'polar-quartz',
        entitlements: {
          administration: false,
          cashFlowRuleEditing: false,
          personalFinanceAccess: true,
          resources: {
            activeGoals: { allowed: true, limit: 1 },
            activeLoans: { allowed: true, limit: 1 },
            activeScheduledItems: { allowed: true, limit: 2 },
            categories: { allowed: true, limit: 10 },
            currencies: { allowed: true, limit: 2 },
          },
        },
      }),
    }),
  );
  await page.route('**/api/v1/users/me/onboarding', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        currentStep: 6,
        next: 'complete',
        onboardingComplete: true,
        tutorialCompleted: true,
        tutorialRequired: false,
      }),
    }),
  );
  await page.route('**/api/v1/budget-rules**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        allocation: {
          status: 'over_allocated',
          totalPercent: '101.0001',
          overAllocatedBy: '1.0001',
        },
        period: {
          month: '2026-08',
          currency: 'HUF',
          forecastIncomeStatus: 'available',
          forecastIncome: '1000.01',
        },
        items: [
          {
            id: 'rule-1',
            label: 'Needs',
            percent: '101.0001',
            targetHint: null,
            assignedCategoryIds: [],
            createdAt: '2026-01-01T00:00:00.000Z',
            updatedAt: '2026-01-01T00:00:00.000Z',
            plan: {
              status: 'available',
              currency: 'HUF',
              plannedAmount: '1000.01',
              assignedCategorySpending: '1250.02',
              signedVariance: '-250.01',
            },
          },
        ],
      }),
    }),
  );
  await page.route('**/api/v1/recurring-rules**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [
          {
            id: 'schedule-1',
            title: 'Leap month plan',
            amount: '10.0001',
            currency: 'HUF',
            economicType: 'transfer',
            categoryId: null,
            categoryLabel: null,
            startsOn: '2024-02-29',
            rrule: 'FREQ=YEARLY;INTERVAL=1;BYMONTH=2;BYMONTHDAY=29',
            goalId: 'goal-1',
            loanId: null,
            investmentId: null,
            createdAt: '2026-01-01T00:00:00.000Z',
            updatedAt: '2026-01-01T00:00:00.000Z',
            forecast: route.request().url().includes('from=')
              ? {
                  from: '2028-02-01',
                  to: '2028-03-31',
                  occurrences: ['2028-02-29', '2028-03-31'],
                  truncated: false,
                  iterationLimit: 2000,
                }
              : null,
          },
        ],
      }),
    }),
  );
  await page.route('**/api/v1/categories**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: Array.from({ length: 10 }, (_, index) => ({
          id: `category-${index}`,
          label: `Category ${index + 1}`,
          kind: index === 0 ? 'income' : 'spending',
          color: '#2563eb',
          protected: index === 0,
          systemKey: index === 0 ? 'income' : null,
          budgetRuleId: index === 1 ? 'rule-1' : null,
          budgetRuleLabel: index === 1 ? 'Needs' : null,
          createdAt: '2026-01-01T00:00:00.000Z',
          updatedAt: '2026-01-01T00:00:00.000Z',
        })),
      }),
    }),
  );
  await page.route('**/api/v1/basic-incomes**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        items: [
          {
            id: 'income-1',
            label: 'Salary plan',
            amount: '2500.0001',
            currency: 'HUF',
            categoryId: 'category-0',
            categoryLabel: 'Category 1',
            validFrom: '2026-01-01',
            validTo: null,
            createdAt: '2026-01-01T00:00:00.000Z',
            updatedAt: '2026-01-01T00:00:00.000Z',
          },
        ],
      }),
    }),
  );
  await page.route('**/api/v1/users/me/currencies**', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        mainCurrency: 'HUF',
        available: [],
        items: [
          {
            code: 'HUF',
            name: 'Hungarian Forint',
            minorUnit: 2,
            roundingMode: 'HALF_EVEN',
            isMain: true,
          },
        ],
      }),
    }),
  );
}

async function assertNoHorizontalScroll(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}
