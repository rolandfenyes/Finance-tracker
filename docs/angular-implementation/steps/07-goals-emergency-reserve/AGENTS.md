# Step 07 — Goals and Emergency Reserve

## Objective

Implement goal and emergency-reserve experiences using authoritative
ledger-derived progress and transfer semantics.

## Dependencies

- Steps 00–06 are complete.
- Journal idempotency, correction/reversal patterns, recurrence UI, currency
  display, and dashboard invalidation are operational.

## Required evidence

Read goal and emergency-reserve controllers, DTOs, types, generated services,
progress/reserve calculators and tests, recurrence linkage, notification
calculation-boundary tests, and approved goal/reserve decisions.

## Goal routes and behavior

- `/app/goals`: active, completed, and archived views with entitlement quota.
- `/app/goals/new`: zero-balance goal creation.
- `/app/goals/:id`: target, ledger-derived current/remaining/progress,
  contribution history, recurrence, category, and lifecycle.
- `/app/goals/:id/edit`: update an open goal.
- responsive dialogs/panels for contribute, correct, reverse, archive,
  unarchive, and recurring-rule create/replace/remove.

Preserve reject-not-cap overfunding, target-not-below-current, completed lock,
reopen-after-reversal, archive visibility semantics, and history-aware deletion.

## Reserve route and behavior

- `/app/reserve`: manual target, ledger-derived allocation, currency, movement
  history, optional investment link, neutral methodology, and raw scheduled
  activity.
- target/linkage editor;
- contribution, withdrawal, and reversal dialogs.

Reserve movements are internal transfers. Withdrawal is not income. Raw
scheduled activity is not a list of “needs.” Do not claim the user is safe,
adequately funded, or should invest.

## Calculation boundary

Render goal progress, remaining amount, reserve balance, converted movement
values, and scheduled totals only from generated response DTOs. The frontend
may calculate visual coordinates after preserving the exact source strings but
must not derive authoritative amounts from contribution/movement rows.

## Tests

- goal values are supplied by the goal read model, not local summation;
- reserve values are supplied by the reserve read model;
- idempotency across contribution/withdrawal/correction/reversal retry;
- overfunding, completed lock, target reduction, reopen, archive/unarchive, and
  history-aware delete states;
- transfers do not affect cash-flow income/expense presentation;
- recurrence linkage and forecast labels;
- neutral reserve copy and absence of advice;
- quota and cross-route entitlement presentation;
- progress accessibility, mobile dialogs, keyboard, EN/ES/HU;
- Playwright goal completion/reversal and reserve contribution/withdrawal
  journeys.

## Acceptance criteria

- Every goal and reserve operation has UI coverage.
- Calculation-source tests prove no local financial derivation.
- Immutable transfer and lifecycle semantics are explicit.
- Loan, investment, and securities work was not started.
- Step 08 was not started.
