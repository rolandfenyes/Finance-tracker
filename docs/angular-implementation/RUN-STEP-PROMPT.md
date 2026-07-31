# Generic Angular Step Prompt

Replace `<STEP_DIRECTORY>` with one directory from
`docs/angular-implementation/steps/`, then run the prompt in a fresh Codex task.

```text
Implement the MyMoneyMap Angular step defined in:

<STEP_DIRECTORY> = 00-execution-contract

docs/angular-implementation/steps/<STEP_DIRECTORY>/AGENTS.md

Work only on this step.

Before implementation:

1. Read completely:
   - docs/angular-implementation/IMPLEMENTATION-PLAN.md
   - docs/angular-implementation/AGENTS.md
   - docs/angular-implementation/steps/<STEP_DIRECTORY>/AGENTS.md
   - docs/angular-implementation/NESTJS-API-AND-BUSINESS-LOGIC.md
   - docs/angular-implementation/ANGULAR-IMPLEMENTATION-PLAN.md
   - every source file, contract artifact, decision record, and test referenced
     by the step
   - any applicable repository-level AGENTS.md files

2. Inspect the current worktree and existing Angular implementation.
   - Preserve unrelated and user-owned changes.
   - Verify every required preceding step is complete.
   - Do not repeat completed work.
   - If a dependency is incomplete, report the exact dependency and stop.

3. Create a working plan from the step's deliverables, tests, and acceptance
   criteria.

Implementation rules:

- Implement the step fully; do not only describe or plan it.
- Stay strictly within the current step.
- Do not start a later Angular step, Astro work, or a new backend feature.
- Follow the source-of-truth order in the shared Angular AGENTS.md.
- Use the frozen OpenAPI and generated Angular client.
- Never hand-edit libs/generated/api-client/src.
- Do not replace a missing OpenAPI schema with a handwritten cast or duplicate
  DTO. Report the exact contract gap and stop the affected feature.
- Do not invent API behavior, pages, roles, tiers, business rules, financial
  formulas, provider features, legal claims, or notification channels.
- Do not store authentication tokens or session IDs in browser storage.
- Keep exact financial values as decimal strings and use only the approved
  decimal adapter.
- Do not recalculate backend-owned totals, FX, balances, progress,
  amortization, allocation, FIFO, or projections.
- Preserve posted, forecast, projection, scenario, stale, delayed, and
  unavailable distinctions.
- Retain idempotency keys across retries of the same intent and attach them only
  to declared operations.
- Keep Material, Tailwind, token, accessibility, localization, and responsive
  behavior within the approved Step 00 contracts.
- Use synthetic fixtures only.
- Do not expose secrets, credentials, tokens, PII, or financial payloads in
  source, tests, snapshots, URLs, logs, or telemetry.
- Do not modify the NestJS API, PostgreSQL schema, OpenAPI, or backend behavior
  unless the current step explicitly records an owner-approved contract-only
  correction. If approval is absent, stop and ask one precise question.

Testing requirements:

- Write tests during implementation.
- Add every component, unit, HTTP-contract, Playwright, accessibility,
  responsive, locale, theme, security, and exact-value test required by the
  step.
- Run all relevant existing tests in addition to new tests.
- Run formatting, linting, type checking, build, and contract drift checks.
- Run affected Playwright journeys at mobile and desktop viewports.
- Do not skip, weaken, quarantine, or delete tests to obtain a pass.

Completion requirements:

1. Compare the result with every deliverable and acceptance criterion in the
   step AGENTS.md.
2. Inspect the final diff for scope expansion, unrelated formatting, generated
   client edits, hardcoded secrets, PII, floating-point financial logic,
   duplicated backend rules, inaccessible interactions, untranslated copy,
   unsupported provider claims, and missing responsive states.
3. Do not mark the step complete while required work remains.
4. Do not create a commit or push unless explicitly requested.

At the end, report:

- Step implemented
- Main files and routes created or changed
- Generated-client services and API operations consumed
- Reusable components, tokens, directives, or adapters added
- Existing business behavior preserved
- Tests and verification commands with results
- Accessibility, localization, theme, and responsive evidence
- Acceptance criteria status
- Approved decisions applied
- Unresolved contract gaps or risks
- Confirmation that no later step was started
- Confirmation that no commit or push was created

Begin now with:

docs/angular-implementation/steps/<STEP_DIRECTORY>/AGENTS.md
```

## Step directories

1. `00-execution-contract`
2. `01-workspace-design-system`
3. `02-api-core-session`
4. `03-identity-onboarding`
5. `04-product-shell-dashboard`
6. `05-journal-reports`
7. `06-planning`
8. `07-goals-emergency-reserve`
9. `08-loans-investments`
10. `09-securities`
11. `10-feedback-settings-notifications-privacy`
12. `11-administration`
13. `12-hardening-frontend-freeze`
