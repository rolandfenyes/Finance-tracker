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

  if (request.url === '/api/v1/users/me/onboarding') {
    const incomplete = (request.headers.cookie ?? '').includes('mmm-e2e-session=onboarding');
    response.writeHead(200, {
      'content-type': 'application/json',
      'x-request-id': 'synthetic-e2e-onboarding',
    });
    response.end(
      JSON.stringify({
        currentStep: incomplete ? 2 : 6,
        next: incomplete ? 'rules' : 'complete',
        onboardingComplete: !incomplete,
        tutorialCompleted: !incomplete,
        tutorialRequired: incomplete,
      }),
    );
    return;
  }

  response.writeHead(404, { 'content-type': 'application/json' });
  response.end(JSON.stringify({ error: { code: 'synthetic_not_found' } }));
});

server.listen(3334, '127.0.0.1');

const close = () => server.close();
process.on('SIGINT', close);
process.on('SIGTERM', close);
