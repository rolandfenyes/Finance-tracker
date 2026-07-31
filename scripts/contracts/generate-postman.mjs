import { readFile, writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const openApiPath = path.join(root, 'apps/api/openapi/openapi.json');
const outputPath = path.join(root, 'postman/MyMoneyMap-backend-v1.postman_collection.json');

function testScript(statuses, extra = []) {
  const assertions = [
    `pm.test("status is ${statuses.join(' or ')}", () => pm.expect(${JSON.stringify(statuses)}).to.include(pm.response.code));`,
  ];
  if (!statuses.some((status) => status >= 500)) {
    assertions.push(
      'pm.test("response has no server error", () => pm.expect(pm.response.code).to.be.below(500));',
    );
  }
  return {
    listen: 'test',
    script: {
      type: 'text/javascript',
      exec: [...assertions, ...extra],
    },
  };
}

function request(name, method, url, statuses, body, extra = [], headers = []) {
  const item = {
    name,
    event: [testScript(statuses, extra)],
    request: {
      method,
      header: [
        { key: 'Accept', value: 'application/json' },
        ...(body ? [{ key: 'Content-Type', value: 'application/json' }] : []),
        ...headers,
      ],
      url: `{{baseUrl}}${url}`,
      description: 'Synthetic Step 22 acceptance scenario. No production data or provider is used.',
    },
  };
  if (body) item.request.body = { mode: 'raw', raw: JSON.stringify(body, null, 2) };
  return item;
}

function login(name, emailVariable, passwordVariable) {
  return request(
    name,
    'POST',
    '/api/v1/auth/sessions',
    [204],
    { email: `{{${emailVariable}}}`, password: `{{${passwordVariable}}}`, remember: false },
    [
      'pm.test("session cookie is issued", () => pm.expect(pm.cookies.has("mymoneymap.sid")).to.eql(true));',
    ],
  );
}

function logout(name = 'Logout') {
  return request(name, 'DELETE', '/api/v1/auth/session', [204]);
}

function delayedRequest(name, method, url, statuses, delayMilliseconds) {
  const item = request(name, method, url, statuses);
  item.event.unshift({
    listen: 'prerequest',
    script: {
      type: 'text/javascript',
      exec: [
        `const deadline = Date.now() + ${delayMilliseconds};`,
        'while (Date.now() < deadline) { /* deterministic idle-expiry wait */ }',
      ],
    },
  });
  return item;
}

function explicitBodyExample(operation) {
  const media = operation.requestBody?.content?.['application/json'];
  if (!media) return undefined;
  const examples = Object.values(media.examples || {});
  if (examples[0]?.value !== undefined) return examples[0].value;
  if (media.example !== undefined) return media.example;
  return {};
}

function contractCatalogue(openapi) {
  const folders = new Map();
  for (const [route, pathItem] of Object.entries(openapi.paths)) {
    for (const method of ['get', 'post', 'put', 'patch', 'delete']) {
      const operation = pathItem[method];
      if (!operation) continue;
      const tag = operation.tags?.[0] || 'Unclassified';
      const body = explicitBodyExample(operation);
      const schema =
        operation.requestBody?.content?.['application/json']?.schema?.$ref?.split('/').at(-1) ||
        null;
      const headers = [{ key: 'Accept', value: 'application/json' }];
      if (body !== undefined) headers.push({ key: 'Content-Type', value: 'application/json' });
      const item = {
        name: operation.summary || operation.operationId,
        request: {
          method: method.toUpperCase(),
          header: headers,
          url: `{{baseUrl}}${route.replace(/\{([^}]+)\}/g, ':$1')}`,
          description: [
            `OpenAPI operation: ${operation.operationId}`,
            schema ? `Request schema: ${schema}` : 'Request schema: none',
            operation.description || '',
          ]
            .filter(Boolean)
            .join('\n\n'),
        },
        response: [],
      };
      if (body !== undefined) {
        item.request.body = { mode: 'raw', raw: JSON.stringify(body, null, 2) };
      }
      if (!folders.has(tag)) folders.set(tag, { name: tag, item: [] });
      folders.get(tag).item.push(item);
    }
  }
  return [...folders.values()].sort((left, right) => left.name.localeCompare(right.name));
}

function acceptanceFolder() {
  const idempotency = [{ key: 'Idempotency-Key', value: 'step22-journal-stable-event' }];
  return {
    name: 'Acceptance',
    description:
      'Executable synthetic acceptance path. Deep concurrency, rollback, retry, provider, and cross-module invariants remain in the PostgreSQL/Redis integration suite and are mapped in the freeze report.',
    item: [
      {
        name: '01 Health and public contract',
        item: [
          request('Liveness', 'GET', '/api/v1/health/live', [200]),
          request('Readiness', 'GET', '/api/v1/health/ready', [200]),
          request(
            'Protected currency catalogue rejects anonymous access',
            'GET',
            '/api/v1/currencies',
            [401],
          ),
          request('Reject malformed registration', 'POST', '/api/v1/auth/registrations', [400], {
            email: 'not-an-email',
            password: 'short',
            fullName: '',
            dateOfBirth: '2026-02-30',
          }),
          request('Registration is enumeration-safe', 'POST', '/api/v1/auth/registrations', [202], {
            email: '{{registrationEmail}}',
            password: '{{registrationPassword}}',
            fullName: 'Synthetic Registration',
            dateOfBirth: '1992-04-05',
          }),
          request(
            'Verification request is enumeration-safe',
            'POST',
            '/api/v1/auth/email-verification-requests',
            [202],
            { email: '{{registrationEmail}}' },
          ),
          request(
            'Passkey authentication options',
            'POST',
            '/api/v1/auth/passkey-sessions/options',
            [201],
            { email: '{{premiumEmail}}' },
            [
              'pm.test("challenge is returned", () => pm.expect(pm.response.json()).to.have.property("challenge"));',
            ],
          ),
        ],
      },
      {
        name: '02 Premium user financial workflow',
        item: [
          login('Login premium owner', 'premiumEmail', 'premiumPassword'),
          request('Current user', 'GET', '/api/v1/users/me', [200]),
          request('Theme preference', 'PATCH', '/api/v1/users/me/preferences/theme', [200], {
            theme: 'verdant-horizon',
          }),
          request(
            'Notification preferences',
            'GET',
            '/api/v1/users/me/notification-preferences',
            [200],
          ),
          request('List owned currencies', 'GET', '/api/v1/users/me/currencies', [200]),
          request(
            'Create spending category',
            'POST',
            '/api/v1/categories',
            [201],
            { label: 'Step 22 groceries', kind: 'spending', color: '#FACC15' },
            [
              'const body=pm.response.json(); const item=(body.items||[]).find(x=>x.label==="Step 22 groceries"); pm.expect(item).to.exist; pm.collectionVariables.set("categoryId", item.id);',
            ],
          ),
          request(
            'Create exact-decimal income',
            'POST',
            '/api/v1/journal/entries',
            [201],
            {
              economicType: 'external_income',
              amount: '1000.25',
              currency: 'HUF',
              postedOn: '2026-07-29',
              note: 'Synthetic Step 22 income',
            },
            [
              'const body=pm.response.json(); pm.collectionVariables.set("entryId", body.id); pm.test("money remains a string",()=>pm.expect(body.legs[0].amount).to.be.a("string"));',
            ],
            idempotency,
          ),
          request(
            'Replay journal idempotency key',
            'POST',
            '/api/v1/journal/entries',
            [201],
            {
              economicType: 'external_income',
              amount: '1000.25',
              currency: 'HUF',
              postedOn: '2026-07-29',
              note: 'Synthetic Step 22 income',
            },
            [
              'pm.test("replay returns the same entry",()=>pm.expect(pm.response.json().id).to.eql(pm.collectionVariables.get("entryId")));',
            ],
            idempotency,
          ),
          request(
            'Reverse immutable journal entry',
            'POST',
            '/api/v1/journal/entries/{{entryId}}/reversals',
            [201],
            { postedOn: '2026-07-30', note: 'Synthetic reversal' },
            [],
            [{ key: 'Idempotency-Key', value: 'step22-reversal-stable-event' }],
          ),
          request('Paginated journal read', 'GET', '/api/v1/journal/entries?limit=1', [200]),
          request(
            'Current report read model',
            'GET',
            '/api/v1/reports/months/current?limit=1',
            [200],
          ),
          request('Create goal', 'POST', '/api/v1/goals', [201], {
            title: 'Step 22 goal',
            targetAmount: '1000.00',
            currency: 'HUF',
            deadline: '2026-12-31',
            priority: 3,
            status: 'active',
          }),
          request(
            'Set emergency reserve target',
            'PUT',
            '/api/v1/emergency-reserve/target',
            [200],
            { targetAmount: '250000.00', currency: 'HUF', linkedInvestmentAccountId: null },
          ),
          request('Create loan', 'POST', '/api/v1/loans', [201], {
            title: 'Step 22 loan',
            principal: '120000',
            currency: 'HUF',
            nominalAnnualRate: '12',
            termMonths: 12,
            startsOn: '2026-07-30',
            paymentDay: 30,
            extraPaymentScenario: '0',
            insuranceMonthly: '500',
          }),
          request('Create generic investment', 'POST', '/api/v1/investments', [201], {
            type: 'savings',
            name: 'Step 22 reserve',
            provider: 'Synthetic provider',
            currency: 'HUF',
            scenarioAnnualRate: '5',
            scenarioFrequency: 'monthly',
          }),
          request(
            'Securities portfolio without live provider',
            'GET',
            '/api/v1/securities/portfolio',
            [200],
          ),
          request('List feedback', 'GET', '/api/v1/feedback', [200]),
          request('Privacy export production gate', 'POST', '/api/v1/privacy/exports', [503]),
          logout(),
        ],
      },
      {
        name: '03 Ownership, verification, entitlement, and quota',
        item: [
          login('Login second premium user', 'otherEmail', 'otherPassword'),
          request(
            'Other user creates category',
            'POST',
            '/api/v1/categories',
            [201],
            { label: 'Other private category', kind: 'spending', color: '#123456' },
            [
              'const item=pm.response.json().items.find(x=>x.label==="Other private category"); pm.collectionVariables.set("otherCategoryId",item.id);',
            ],
          ),
          logout('Logout second user'),
          login('Login first premium user', 'premiumEmail', 'premiumPassword'),
          request(
            'Cross-user update is hidden',
            'PATCH',
            '/api/v1/categories/{{otherCategoryId}}',
            [404],
            { label: 'Forbidden update' },
          ),
          logout('Logout premium user'),
          login('Login unverified user', 'unverifiedEmail', 'unverifiedPassword'),
          request('Unverified financial access denied', 'GET', '/api/v1/categories', [403]),
          logout('Logout unverified user'),
          login('Login free user', 'freeEmail', 'freePassword'),
          request('Free user sees own profile', 'GET', '/api/v1/users/me', [200]),
          request(
            'Free user cannot edit premium budget rules',
            'POST',
            '/api/v1/budget-rules',
            [403],
            {
              label: 'Premium-only rule',
              percent: '10',
            },
          ),
          ...Array.from({ length: 11 }, (_, index) =>
            request(
              `Free category quota ${index + 1}`,
              'POST',
              '/api/v1/categories',
              index === 10 ? [403] : [201],
              {
                label: `Free quota category ${index + 1}`,
                kind: 'spending',
                color: '#ABCDEF',
              },
            ),
          ),
          logout('Logout free user'),
          login('Login idle-expiry user', 'expiryEmail', 'expiryPassword'),
          delayedRequest(
            'Idle-expired session is rejected',
            'GET',
            '/api/v1/users/me',
            [401],
            3000,
          ),
        ],
      },
      {
        name: '04 Administration, billing, jobs, and provider gates',
        item: [
          login('Login administrator', 'adminEmail', 'adminPassword'),
          request('Admin dashboard', 'GET', '/api/v1/admin/dashboard', [200]),
          request('Admin queue observability', 'GET', '/api/v1/admin/operations/queues', [200]),
          request('Billing summary', 'GET', '/api/v1/admin/billing/summary', [200]),
          request(
            'Email channel status',
            'GET',
            '/api/v1/admin/notification-channels/email',
            [200],
          ),
          request(
            'Disabled email provider test job',
            'POST',
            '/api/v1/admin/email-test-jobs',
            [202],
            {
              templateCode: 'welcome',
              locale: 'en',
              recipientEmail: '{{adminEmail}}',
              data: { user_first_name: 'Synthetic admin' },
            },
          ),
          request(
            'Market data refresh remains gated',
            'POST',
            '/api/v1/securities/refresh-jobs',
            [403, 409, 503],
          ),
          logout('Logout administrator'),
        ],
      },
      {
        name: '05 Rate limiting',
        item: [
          ...Array.from({ length: 6 }, (_, index) =>
            request(
              `Failed login ${index + 1}`,
              'POST',
              '/api/v1/auth/sessions',
              index === 5 ? [429] : [401],
              { email: '{{rateLimitEmail}}', password: 'deliberately-wrong-password' },
            ),
          ),
        ],
      },
    ],
  };
}

const openapi = JSON.parse(await readFile(openApiPath, 'utf8'));
const operationCount = Object.values(openapi.paths).reduce(
  (count, item) =>
    count +
    Object.keys(item).filter((method) => ['get', 'post', 'put', 'patch', 'delete'].includes(method))
      .length,
  0,
);

const collection = {
  info: {
    name: 'MyMoneyMap Backend v1 — Step 22 Freeze',
    description: [
      `Generated from apps/api/openapi/openapi.json (${operationCount} operations).`,
      'The Contract catalogue is generated from the complete OpenAPI contract.',
      'The Acceptance folder is an executable synthetic smoke/contract journey; deeper PostgreSQL/Redis evidence is mapped in the Step 22 completion report.',
      'Do not put credentials or production data in this collection.',
    ].join('\n\n'),
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  variable: [{ key: 'baseUrl', value: 'http://127.0.0.1:3010', type: 'string' }],
  item: [acceptanceFolder(), { name: 'Contract catalogue', item: contractCatalogue(openapi) }],
};

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(collection, null, 2)}\n`);
console.log(
  `Generated ${path.relative(root, outputPath)} with ${operationCount} OpenAPI operations.`,
);
