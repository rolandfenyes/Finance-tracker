/* global process */

import { createServer } from 'node:http';

const server = createServer((request, response) => {
  if (request.url === '/api/v1/health/live') {
    response.writeHead(200, {
      'content-type': 'application/json',
      'x-step-01-synthetic-proxy': 'true',
    });
    response.end(JSON.stringify({ status: 'ok' }));
    return;
  }

  if (request.url === '/api/v1/users/me') {
    const mode = /mmm-e2e-session=([^;]+)/.exec(request.headers.cookie ?? '')?.[1] ?? 'personal';
    if (mode === 'anonymous') {
      response.writeHead(401, {
        'content-type': 'application/json',
        'x-request-id': 'synthetic-e2e-401',
      });
      response.end(
        JSON.stringify({
          error: {
            code: 'UNAUTHORIZED',
            message: 'Synthetic unauthenticated request',
            requestId: 'synthetic-e2e-401',
          },
        }),
      );
      return;
    }
    const admin = mode === 'admin';
    response.writeHead(200, {
      'content-type': 'application/json',
      'x-request-id': 'synthetic-e2e-user',
    });
    response.end(
      JSON.stringify({
        id: '00000000-0000-4000-8000-000000000001',
        email: 'synthetic@example.test',
        fullName: 'Synthetic User',
        dateOfBirth: '1990-01-01',
        desiredLanguage: 'en',
        emailVerified: true,
        role: admin ? 'admin' : 'free',
        theme: 'verdant-horizon',
        entitlements: {
          administration: admin,
          cashFlowRuleEditing: false,
          personalFinanceAccess: !admin,
          resources: Object.fromEntries(
            ['activeGoals', 'activeLoans', 'activeScheduledItems', 'categories', 'currencies'].map(
              (resource) => [resource, { allowed: !admin, limit: admin ? null : 2 }],
            ),
          ),
        },
      }),
    );
    return;
  }

  if (request.url?.startsWith('/api/v1/reports/months/current')) {
    return json(response, 200, dashboardReport(cookie(request, 'mmm-e2e-report') === 'complete'));
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/registrations') {
    return json(response, 202, { accepted: true });
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/email-verification-requests') {
    return json(response, 202, { accepted: true });
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/email-verifications') {
    response.setHeader('set-cookie', sessionCookies('theme'));
    return empty(response, 204);
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/sessions') {
    response.setHeader('set-cookie', sessionCookies('theme'));
    return empty(response, 204);
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/passkey-sessions/options') {
    return json(response, 200, {
      challenge: 'AQID',
      rpId: 'localhost',
      userVerification: 'preferred',
    });
  }

  if (request.method === 'POST' && request.url === '/api/v1/auth/passkey-sessions') {
    response.setHeader('set-cookie', sessionCookies('theme'));
    return empty(response, 204);
  }

  if (request.url === '/api/v1/users/me/onboarding') {
    if (request.method === 'PATCH') {
      response.setHeader('set-cookie', sessionCookies('complete'));
      return json(response, 200, onboardingState('complete'));
    }
    const incomplete = (request.headers.cookie ?? '').includes('mmm-e2e-session=onboarding');
    const step = incomplete ? (cookie(request, 'mmm-e2e-step') ?? 'rules') : 'complete';
    response.writeHead(200, {
      'content-type': 'application/json',
      'x-request-id': 'synthetic-e2e-onboarding',
    });
    response.end(JSON.stringify(onboardingState(step)));
    return;
  }

  if (request.url === '/api/v1/users/me/preferences/theme') {
    if (request.method === 'PATCH') response.setHeader('set-cookie', stepCookie('rules'));
    return json(response, 200, {
      theme: 'verdant-horizon',
      supportedThemes: [
        'polar-quartz',
        'verdant-horizon',
        'celestial-tide',
        'blush-nocturne',
        'ember-vanguard',
        'lilac-eclipse',
        'solaris-bloom',
        'dune-mirage',
      ],
    });
  }

  if (request.method === 'PUT' && request.url === '/api/v1/budget-rules') {
    response.setHeader('set-cookie', stepCookie('currencies'));
    return json(response, 201, { items: [] });
  }

  if (request.method === 'GET' && request.url === '/api/v1/currencies') {
    return json(response, 200, {
      items: [
        { code: 'EUR', name: 'Euro', minorUnit: 2 },
        { code: 'HUF', name: 'Hungarian Forint', minorUnit: 2 },
      ],
    });
  }

  if (request.url === '/api/v1/users/me/currencies') {
    if (request.method === 'POST') response.setHeader('set-cookie', stepCookie('categories'));
    return json(response, 200, {
      items: [{ code: 'EUR', name: 'Euro', isMain: true }],
    });
  }

  if (request.url === '/api/v1/categories') {
    if (request.method === 'POST') response.setHeader('set-cookie', stepCookie('income'));
    return json(response, request.method === 'POST' ? 201 : 200, { items: [] });
  }

  if (request.method === 'POST' && request.url === '/api/v1/basic-incomes') {
    response.setHeader('set-cookie', stepCookie('tutorial'));
    return json(response, 201, {
      id: '00000000-0000-4000-8000-000000000099',
      amount: '2500.00',
      currency: 'EUR',
      label: 'Synthetic baseline',
      validFrom: '2026-01-01',
    });
  }

  response.writeHead(404, { 'content-type': 'application/json' });
  response.end(JSON.stringify({ error: { code: 'synthetic_not_found' } }));
});

function cookie(request, name) {
  return new RegExp(`(?:^|;\\s*)${name}=([^;]+)`).exec(request.headers.cookie ?? '')?.[1];
}

function onboardingState(step) {
  const steps = ['theme', 'rules', 'currencies', 'categories', 'income', 'tutorial'];
  const complete = step === 'complete';
  return {
    currentStep: complete ? 6 : Math.max(1, steps.indexOf(step) + 1),
    next: step,
    onboardingComplete: complete,
    tutorialCompleted: complete,
    tutorialRequired: !complete,
  };
}

function dashboardReport(complete) {
  const conversion = {
    complete,
    includedSourceCount: 2,
    newestFetchedAt: '2026-07-31T10:00:00.000Z',
    oldestRateAt: '2026-07-31T09:00:00.000Z',
    providers: ['synthetic-fx'],
    staleSourceCount: complete ? 0 : 1,
    status: complete ? 'available' : 'stale',
    unavailableSourceCount: complete ? 0 : 1,
  };
  const summary = {
    adjustmentNet: '0.00000000',
    conversion,
    currency: 'EUR',
    expense: '125.75000000',
    income: '1000.00000000',
    netCashFlow: '874.25000000',
    tradeCashNet: '0.00000000',
    transfer: '50.00000000',
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
    forecast: {
      summary: { ...summary, income: '750.00000000', expense: '90.12500000' },
      sources: [
        {
          amount: '90.12500000',
          categoryId: null,
          conversionStatus: 'available',
          convertedAmount: '90.12500000',
          currency: 'EUR',
          fetchedAt: null,
          kind: 'expense',
          label: 'Synthetic rent forecast',
          occurrenceOn: '2026-07-20',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          sourceEntryId: 'forecast:2026-07-20',
          sourceId: 'forecast-source',
          sourceKind: 'recurring_rule',
        },
      ],
    },
    combinedProjection: { ...summary, income: '1750.00000000', expense: '215.87500000' },
    budget: {
      allocation: { overAllocatedBy: '5.5', status: 'over_allocated', totalPercent: '105.5' },
      period: {
        currency: 'EUR',
        forecastIncome: '1000.00000000',
        forecastIncomeStatus: 'available',
        month: '2026-07',
      },
      items: [
        {
          assignedCategoryIds: [],
          createdAt: '2026-07-01T00:00:00.000Z',
          id: 'budget-needs',
          label: 'Needs',
          percent: '55.5',
          plan: {
            assignedCategorySpending: '125.75000000',
            currency: 'EUR',
            plannedAmount: '555.00000000',
            signedVariance: '429.25000000',
            status: 'stale',
          },
          targetHint: null,
          updatedAt: '2026-07-01T00:00:00.000Z',
        },
      ],
    },
    activity: {
      nextCursor: 'opaque-dashboard-cursor',
      items: [
        {
          amount: '125.75000000',
          categoryId: null,
          conversionStatus: complete ? 'available' : 'unavailable',
          ...(complete ? { convertedAmount: '146.25000000' } : {}),
          currency: 'GBP',
          economicType: 'expense',
          effectiveAt: '2026-07-10T10:00:00.000Z',
          fetchedAt: null,
          kind: 'expense',
          note: null,
          postedOn: '2026-07-10',
          provider: null,
          rateAt: null,
          reportingCurrency: 'EUR',
          reversesEntryId: null,
          source: { module: 'manual', referenceId: null },
          sourceEntryId: 'activity-one',
        },
      ],
    },
  };
}

function sessionCookies(step) {
  return ['mmm-e2e-session=onboarding; Path=/; HttpOnly; SameSite=Lax', stepCookie(step)];
}

function stepCookie(step) {
  return `mmm-e2e-step=${step}; Path=/; HttpOnly; SameSite=Lax`;
}

function json(response, status, body) {
  response.writeHead(status, {
    'content-type': 'application/json',
    'x-request-id': 'synthetic-step-03',
  });
  response.end(JSON.stringify(body));
}

function empty(response, status) {
  response.writeHead(status, { 'x-request-id': 'synthetic-step-03' });
  response.end();
}

server.listen(3334, '127.0.0.1');

const close = () => server.close();
process.on('SIGINT', close);
process.on('SIGTERM', close);
