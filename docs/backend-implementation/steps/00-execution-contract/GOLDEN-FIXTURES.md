# Step 00 — Synthetic Golden-Fixture Inventory

No fixture may contain a real email, name, credential, API key, transaction, account, provider payload tied to a real customer, or the legacy default-admin values.

## Fixture conventions

- Stable UUIDs reserved for tests.
- Emails use `example.test`.
- Dates use a fixed UTC clock and explicitly named user timezone.
- Monetary/quantity/rate inputs and expected outputs are decimal strings.
- Each fixture records whether it validates preserved parity or an audited correction.
- Live providers are replaced by deterministic adapters.
- Expected error fixtures assert stable machine codes, not translated UI text.

## Identity and permissions

| ID | Synthetic setup | Expected invariant |
|---|---|---|
| ID-01 | unverified free user | login may establish limited verification session, but application domain access is denied until verification |
| ID-02 | verified free user with password | login rotates session; logout revokes it |
| ID-03 | verified premium user | premium capabilities are unlimited where approved |
| ID-04 | active admin | admin APIs allowed; personal-finance APIs denied |
| ID-05 | inactive user | authentication/domain access denied and sessions revoked |
| ID-06 | expired/reused verification token | stable invalid/expired result without account enumeration |
| ID-07 | passkey challenge and synthetic credential fixture | challenge expiry, RP/origin, counter and ownership verified |
| ID-08 | user A and user B | every protected resource rejects cross-user IDs |

## Money, ledger, and FX

| ID | Inputs | Expected result |
|---|---|---|
| MON-01 | income `1000.00 HUF` to cash | posted income and cash increase `1000.00`; balanced journal |
| MON-02 | expense `125.50 HUF` | spending `125.50`; cash decreases; exact decimal |
| MON-03 | transfer `300.00 HUF` cash → goal | cash decreases, goal bucket increases, income/spending effect `0.00` |
| MON-04 | reverse MON-02 | original plus reversal nets to `0.00` without deleting history |
| MON-05 | duplicate idempotency key | one economic event and identical safe response |
| MON-06 | amount `0`, negative amount, mismatched currency | validation failure; no rows |
| FX-01 | same-currency conversion | exact original decimal with identity provenance |
| FX-02 | EUR→HUF and HUF→USD using fixed EUR-base rates | cross-rate matches decimal/rounding policy |
| FX-03 | missing source or target rate | status `unavailable`; no converted amount |
| FX-04 | stale prior-business-day rate | status and actual rate date exposed |
| FX-05 | future forecast assumption | never stored or labeled as observed historical rate |
| FX-06 | main-currency concurrent updates | exactly one valid main currency |

## Budgeting and recurrence

| ID | Inputs | Expected result |
|---|---|---|
| BUD-01 | income `1000`, rule `50%`, spent `425` | plan `500`, signed variance `75` |
| BUD-02 | same plan, spent `575` | signed variance `-75`, not clamped |
| BUD-03 | rules total `120%` | explicit over-allocation `20%`; values not normalized |
| BUD-04 | rule with three categories | no invented equal per-category cap |
| REC-01 | monthly day 31 across February | approved month-end clamp behavior preserved |
| REC-02 | weekly `BYDAY` | occurrences match legacy subset |
| REC-03 | `COUNT` and `UNTIL` boundaries | final occurrence deterministic |
| REC-04 | retry/concurrent workers | one materialized event per occurrence |
| REC-05 | API read during overdue schedule | no write or catch-up occurs |
| REC-06 | unsupported RRULE token | explicit validation error |

## Reporting

| ID | Setup | Expected result |
|---|---|---|
| REP-01 | income, expense, internal transfer | totals include income/expense and exclude transfer from cash flow |
| REP-02 | posted plus forecasts | separate posted, forecast, and combined projections |
| REP-03 | paginated activity | totals identical across page sizes |
| REP-04 | missing FX | affected aggregate marked incomplete/unavailable, never mislabeled |
| REP-05 | month/year boundary in Europe/Budapest | effective dates follow the approved date/time contract |

## Goals and emergency reserve

| ID | Setup | Expected result |
|---|---|---|
| GOAL-01 | target `1000`, contributions `400` + `600` | progress `100%`; goal locks |
| GOAL-02 | contribution after completion | rejected without a journal entry |
| GOAL-03 | archive/unarchive completed goal | no income/expense generated |
| GOAL-04 | contribution retry and reversal | one contribution; reversal reconciles bucket |
| EF-01 | transfer `250` to reserve | income/spending effect zero |
| EF-02 | withdraw `75` to cash | income/spending effect zero |
| EF-03 | linked generic investment | one economic transfer, not duplicate postings |
| EF-04 | target/raw schedule response | no “safe now” or investment recommendation field |

## Loans

| ID | Inputs | Expected result |
|---|---|---|
| LOAN-01 | principal `120000`, nominal annual `12%`, 12 months | standard monthly annuity fixture using exact decimal and approved rounding |
| LOAN-02 | principal `1200`, rate `0%`, 12 months | monthly estimate `100` |
| LOAN-03 | posted payment with components | liability, cash and payment ledger reconcile |
| LOAN-04 | manual and scheduled cross-currency equivalent | identical dated conversion treatment |
| LOAN-05 | projected payment | not present in confirmed payment history |
| LOAN-06 | read loan list | no history mutation |
| LOAN-07 | overpayment/reversal | policy is explicit and balance does not drift |

## Generic investments

| ID | Setup | Expected result |
|---|---|---|
| INV-01 | cash → investment deposit | transfer; income/spending effect zero |
| INV-02 | investment withdrawal | transfer; cannot exceed approved available balance |
| INV-03 | nominal compound scenario | matches approved formula and is labeled scenario |
| INV-04 | accrued scenario without posted interest | posted balance unchanged |
| INV-05 | recurring contribution | forecast separated; retry-safe posting when approved |

## Securities

| ID | Setup | Expected result |
|---|---|---|
| STK-01 | two buy lots and partial sell | FIFO lot quantities, allocated fees and realized P/L match fixture |
| STK-02 | sell above available holding | atomic rejection; no trade, lot, cash, or P/L changes |
| STK-03 | two concurrent sells exceeding combined availability | at most valid available quantity commits |
| STK-04 | reverse/delete trade | linked cash and derived state reverse/rebuild atomically |
| STK-05 | same symbol on two markets | distinct instruments |
| STK-06 | missing/stale quote | unavailable/stale valuation; cost not returned as market value |
| STK-07 | dated acquisition/sale FX | local result and FX contribution remain attributable |
| STK-08 | duplicate CSV import | fingerprint prevents duplicate posting |
| STK-09 | malformed mixed import batch | preview errors; posting rollback policy is deterministic |
| STK-10 | SMA/RSI/concentration | descriptive values only; no buy/trim output |
| STK-11 | Finnhub metadata fixture | IPO date never maps to industry |

## Administration, billing, notifications, and privacy

| ID | Setup | Expected result |
|---|---|---|
| ADM-01 | non-admin calls every admin family | forbidden |
| ADM-02 | admin writes integration secret | subsequent response is configured/masked, never plaintext |
| ADM-03 | admin user recovery | expiring reset action; no reusable password response |
| BILL-01 | assign current plan | entitlement transition audited |
| BILL-02 | plan/promotion CRUD | current fields/constraints preserved; no checkout claim |
| MAIL-01 | duplicate triggering event | one queued logical notification |
| MAIL-02 | locale EN/ES/HU | validated template data and fallback |
| MAIL-03 | provider failure | bounded retry then dead-letter/status |
| PRIV-01 | complete synthetic user | export includes every manifest-approved domain |
| PRIV-02 | export security data | hashes, secrets, challenges and internal tokens excluded |
| PRIV-03 | delete synthetic user | primary data, jobs, cache and exports handled per manifest |
| PRIV-04 | new user-owned table without manifest entry | required test fails |

## Migration and operational fixtures

| ID | Setup | Expected result |
|---|---|---|
| MIG-01 | clean legacy schema through recorded `035` | detected and transformed repeatably |
| MIG-02 | configured drift with goal category plus untracked investment columns | detected explicitly; mapped or quarantined |
| MIG-03 | default-admin row matching legacy bootstrap | never grants/migrates its known credential; row requires owner-approved recovery |
| MIG-04 | legacy goal/emergency transfer-like rows | reclassified with reconciliation report |
| MIG-05 | orphan/invalid ownership rows | quarantined; never assigned to another user |
| OPS-01 | PostgreSQL/Redis unavailable in required test | test fails, never reports skip-success |
| OPS-02 | backup/restore synthetic dataset | counts and financial totals reconcile |

## Fixture implementation timing

Step 00 defines the inventory only. Later steps implement fixtures in their test framework. A fixture may be refined with additional edge cases, but its invariant cannot be weakened without a superseding decision.

