# MyMoneyMap — Further Development Opportunities

**Principle:** correctness, trust, and daily usefulness come before feature count. Features involving personalized recommendations, securities, credit, tax, or regulated data require jurisdiction-specific legal review.

## 1. Opportunity map

| Opportunity | User value | Complexity | Regulatory / dependency risk | Suggested horizon |
|---|---|---:|---:|---|
| Correct account + transfer ledger | accurate totals and net worth | High | Medium | Now |
| Mobile activity inbox and fast add | daily habit and lower entry friction | Medium | Low | Now |
| CSV/OFX/QIF import with reconciliation | less manual entry | High | Medium | Next |
| Open-banking aggregation | automatic data | Very high | Very high | Later |
| Shared household spaces | collaborative budgets | High | High privacy | Next/Later |
| Scenario planner | understand choices | Medium | Medium advice | Next |
| Loan statement reconciliation | trustworthy payoff view | High | Medium | Next |
| Goals/reserves as transfers | correct savings reporting | Medium | Low | Now |
| Securities corporate actions | credible portfolio accounting | Very high | High | Later |
| Tax lots and reports | investor utility | Very high | Very high | Later |
| Educational insights | improve habits | Medium | Medium | Next |
| AI assistant | natural-language exploration | High | High privacy/advice | Later |

## 2. P0 trust foundations

These are product improvements as much as engineering work:

### Accounts, buckets, and transfers

Introduce cash, bank, card, loan, savings, investment, and manual asset/liability accounts. A transfer has two balanced legs and never changes income/spending. Goals and emergency reserves may be virtual buckets or dedicated accounts, but their funding remains a transfer.

### Posted versus planned

Clearly distinguish:

- forecast recurring item;
- pending item;
- posted transaction;
- reconciled transaction;
- projection/scenario.

Users should never see a forecast silently become a posted transaction because they visited a page.

### Explainable totals

Every dashboard number should open to its source entries and state:

- included accounts and date range;
- currencies and rate time;
- pending/forecast inclusion;
- data freshness;
- calculation version.

### Safe uncertainty

If FX or market data is unavailable, show “unavailable/stale,” never reinterpret the original number as another currency or substitute cost as market value without an explicit indicator.

## 3. High-value user features

### Import and reconciliation

Build a mapping workflow for CSV first, followed by OFX/QIF where relevant:

- preview and validation;
- account/currency mapping;
- date/decimal/locale handling;
- stable import fingerprint and duplicate detection;
- category rules and payee normalization;
- undo batch;
- reconciliation to statement closing balance;
- import audit log.

Open banking should come later through an aggregation provider with explicit consent, data minimization, refresh status, revocation, deletion, and strong customer authentication considerations.

### Unified activity

Replace fragmented month/emergency/goal/investment histories with a single searchable activity stream. Filters can expose the underlying domain while the base event remains one auditable entry. Mobile should prioritize recent activity, review-required imports, recurring items due, and a thumb-reachable add action.

### Flexible budgeting

Support several planning styles without hardwiring advice:

- category amounts;
- percentage envelopes;
- zero-based plan;
- rolling limits;
- annual/irregular expenses;
- rollover policy;
- household/shared categories.

Budget views should display negative variance, not clamp it away.

### Cash-flow forecast

Create a forecast from opening account balances, confirmed recurring income/bills, known loan payments, and optional scenarios. Use confidence states and let users toggle planned items. A calendar view is then a real liquidity tool rather than a mixture of transactions and synthetic rows.

### Net worth

Once accounts exist, show assets, liabilities, and net worth over time. Preserve source/freshness per valuation. Manual assets should require periodic confirmation; securities should distinguish last price, delayed quote, stale quote, and unavailable quote.

### Better loan planning

- configurable nominal/APR/effective-rate labels;
- payment schedule from contract or statement;
- irregular dates, fees, rate changes, holidays;
- extra-payment scenarios without pretending they occurred;
- refinance comparison with total costs;
- statement reconciliation and explicit estimate disclaimer.

### Goal and reserve planning

- contribution plan as transfers;
- priority and target-date scenarios;
- separate “available” from “reserved” money;
- configurable emergency methodology;
- pause/resume and life-event adjustments;
- no prescriptive claim that a user is “good now.”

## 4. Investment opportunities

Decide whether MyMoneyMap is a budgeting product with lightweight valuations or an investment-accounting product. The second path is much more expensive.

### Lightweight portfolio path

- holdings and delayed prices;
- contributions/withdrawals as account transfers;
- allocation and performance with money-weighted/time-weighted definitions;
- quote freshness;
- dividends as cash distributions;
- broad educational diversification indicators;
- no buy/sell instructions.

### Full accounting path

- canonical security identifiers and exchange handling;
- FIFO/average/specific-identification rules by jurisdiction;
- splits, mergers, spin-offs, symbol changes, return of capital;
- dividends, withholding tax, fees, multiple cash currencies;
- broker reconciliation;
- realized/unrealized FX attribution;
- tax reports with jurisdiction/version disclaimers.

Do not call ten-second cached quotes “real time” unless the data license and exchange entitlement permit it. Market-data redistribution and exchange fees can dominate infrastructure cost.

## 5. Reporting and analytics

Recommended reports:

- income/spending by account, category, merchant, and period;
- cash-flow versus budget with visible variance;
- recurring-cost changes and subscription review;
- savings rate with an explicit definition;
- net worth and liability trend;
- FX effect separated from investment performance;
- goal/loan progress based on posted entries;
- data-quality report listing stale rates, uncategorized activity, duplicate candidates, and unreconciled accounts.

Exports should include complete machine-readable JSON and useful CSVs, with a schema/version manifest. A generated PDF can serve human portability, but must not replace raw export.

## 6. Household and collaboration

A household model should not be implemented as shared passwords or a single `user_id`.

Use:

- workspace/household;
- membership and invitation;
- owner/admin/editor/viewer;
- account-level visibility;
- private personal accounts;
- approval for destructive actions;
- audit history;
- export/delete semantics for both member and household.

This changes the tenant and privacy model, so it should be designed before large-scale ledger migration if collaboration is a target market.

## 7. Notifications

Create a notification preference center with:

- transactional versus educational classification;
- per-channel opt-in;
- frequency and quiet hours;
- locale/timezone;
- digest mode;
- delivery history and unsubscribe;
- alert thresholds authored by the user.

Useful notifications include forecasted low cash, an unusual recurring amount, an import requiring review, a stale connection, a bill due, or a user-defined budget threshold. Avoid guilt-oriented or deterministic financial advice.

## 8. AI opportunities

AI should be an explanation layer over verified data, not the accounting engine.

Safer early uses:

- natural-language search translated into read-only filters;
- explain “why did this total change?” with cited source entries;
- suggest category mappings for user approval;
- summarize a monthly report;
- help build a scenario without posting transactions;
- explain finance terms in the user's language.

Controls:

- explicit opt-in and data-use disclosure;
- minimal, redacted context;
- no model training on customer data by default;
- prompt-injection defenses for imported descriptions;
- tool permission boundaries;
- source citations and deterministic recomputation;
- no autonomous trades, credit decisions, or irreversible writes;
- jurisdiction-specific review of advice claims.

## 9. UX and mobile improvements

### Immediate

- restore pinch zoom and browser text scaling;
- remove forced portrait/landscape overlay;
- replace wide tables with prioritized mobile rows/cards;
- persistent fast-add action;
- consistent modal focus trap, escape, return focus, and screen-reader labels;
- inputs with correct mobile keyboards and locale-aware decimal entry;
- skeleton/error/empty/offline states.

### Navigation

Use Home, Activity, Plan, Grow, More. Preserve user context after add/edit. Deep-link every filtered report. Let users pin their top Plan items.

### PWA

Only call it offline-capable after implementing:

- service worker and version/update UX;
- cached app shell;
- encrypted/local-minimized read cache;
- queued drafts with explicit sync state;
- conflict handling;
- online-only gates for destructive/security-sensitive actions;
- device logout and cache purge.

Offline financial data materially increases device-loss and shared-device risk.

## 10. Internationalization

- make locale, currency, timezone, number/date parsing, and first-day-of-week explicit;
- use currency metadata rather than fixed two decimals;
- test RTL before claiming support;
- derive translated email and UI keys from a single catalog;
- remove or finish Greek rather than shipping an inaccessible locale file;
- ensure legal copy has version, effective date, locale, and consent record.

## 11. Administration and support

High-value admin capabilities:

- immutable audit trail for privileged actions;
- impersonation only with strong controls, banner, reason, and audit;
- user data export/deletion job state;
- job/webhook/provider health;
- reconciliation anomalies;
- entitlements and billing events sourced from provider webhooks;
- feature flags;
- template versioning and send test to a safe sink;
- PII redaction by default.

Avoid displaying full secrets after initial entry. Use “configured / last four / rotate” patterns.

## 12. Product strategy sequence

### MVP

- verified identity and privacy;
- account/transfer ledger;
- manual entry and CSV import;
- recurring forecasts;
- monthly budget/report;
- multi-currency with explainable rates;
- mobile-first activity/add;
- full export/delete;
- production operations.

### Feature parity

- goals, emergency fund, loans;
- generic tracked assets;
- multilingual emails;
- admin and paid entitlements;
- stock holdings if product research validates demand.

### Extended SaaS

- household collaboration;
- open banking;
- advanced scenarios;
- subscription intelligence;
- complete securities accounting;
- carefully bounded AI explanation.

The product should launch on trust and clarity, not on the number of dashboards.

## 13. Detailed opportunity portfolio

The tier labels are hypotheses for product research, not commitments.

| Category / feature | User problem and proposed solution | Target / value | Dependencies and data | Security/mobile | Complexity / tier / priority |
|---|---|---|---|---|---|
| Quick win: recurring review | users forget price changes; show detected/manual recurring items and review dates | all users; retention and savings awareness | correct ledger, normalized merchant/payee | no sensitive push preview; swipe review | M / Free / High |
| Quick win: rules categorization | repetitive classification; user-authored rules with preview/undo | frequent importers; less work | import fingerprints, merchant normalization | never auto-post irreversible change; mobile batch review | M / Free/Premium / High |
| Core: receipt attachments | lost evidence; attach receipt to a posted entry | households/freelancers; trust | private object storage, OCR optional | malware scan, signed URLs, device upload | M–H / Premium / Medium |
| Core: reconciliation | app totals drift from statements; closing-balance workflow | all serious users; correctness | accounts, imports, immutable journal | privileged corrections and audit; mobile guided flow | H / Free/Premium / Critical |
| Premium: cash-flow scenarios | uncertainty about future liquidity; toggle income/bills/events | variable-income users; conversion | forecast state, account balances, versioned assumptions | no claim of certainty; touch-friendly scenario controls | H / Premium / High |
| Premium: debt planner | compare extra payments/refinance; contract-aware schedules | borrowers; high willingness to pay | lender statement model, fees/rate changes | advice disclaimer; compact scenario cards | H / Premium / High |
| Retention: goal milestones | saving loses momentum; configurable reminders and progress explanations | savers; habit formation | correct transfer ledger | user-controlled tone/quiet hours; widgets later | M / Free/Premium / Medium |
| Retention: data-quality inbox | hidden stale/duplicate/missing data; action queue | all connected/import users | provenance and anomaly rules | minimize notification detail; one-handed actions | M / Free / High |
| Revenue: household | couples duplicate work; shared/private accounts and approvals | families; multi-seat revenue | workspace tenant model and invitations | granular privacy/audit; shared mobile activity | H / Household plan / Medium |
| Revenue: adviser/accountant view | users need read-only collaboration; scoped consented sharing | freelancers/advised users; B2B2C | roles, scoped grants, audit, export | expiring read-only access, step-up auth | H / Premium add-on / Later |
| Mobile: widgets | users want glanceable safe status; configurable non-sensitive widgets | engaged mobile users; retention | PWA/native capability, cached read model | device-lock/privacy modes | M–H / Premium / Later |
| Mobile: biometric re-auth | sensitive pages need step-up; WebAuthn/user verification | all users; trust | passkey recovery/session policies | native-quality browser flows | M / Free / High |
| Automation: bill detection | recurring obligations are missed; propose patterns for approval | import/open-banking users | normalized ledger and confidence model | explainability, no silent scheduling | H / Premium / Medium |
| Automation: anomaly detection | unusual value may be mistake/fraud; surface deviations | connected users; trust | sufficient history and category/payee model | avoid fraud certainty; secure alert content | H / Premium / Later |
| AI: cited finance Q&A | totals are hard to understand; read-only question to filtered sources | power users; differentiation | audited query tools and metric definitions | opt-in, redaction, prompt-injection control | H / Premium / Later |
| Banking: PSD2 aggregation | manual entry is burdensome; consented provider connections | EU users; major acquisition value | licensed aggregator, SCA/consent/webhooks | very high privacy/operational burden; mobile re-consent | VH / Premium / Later |
| Investment: dividend calendar | distributions are hard to track; provider/broker-reconciled events | investors; retention | security IDs, distributions, tax currency | licensing and tax caveats; mobile calendar | H / Investor tier / Later |
| Investment: multi-currency attribution | users cannot separate FX and asset return; acquisition/sale FX decomposition | international investors; trust | lot-level FX snapshots and prices | explain method; mobile drill-down | VH / Investor tier / Later |
| Reporting: net-worth history | cash flow is not wealth; account valuations over time | all users; core value | accounts/liabilities and valuation provenance | private export/share controls; readable charts | H / Free/Premium / High |
| Reporting: tax package | broker records are fragmented; jurisdiction-versioned export | investors/freelancers; revenue | complete ledger, jurisdiction rules, expert review | exceptionally sensitive; desktop generation/mobile status | VH / Add-on / Later |
| Small business: freelancer workspace | personal/business money mixes; separate workspace, invoices and tax reserves | sole traders; adjacent market | tenant model, invoices, business rules | stronger retention/legal boundaries; mobile receipts | VH / Separate product / Later |
| Internationalization | locale assumptions cause entry errors; localized parsing/calendar/legal content | international users; expansion | locale/currency/timezone model | consent/policy versions; native keyboards | H / All tiers / High |
| Platform: read API/webhooks | users cannot integrate; scoped tokens and event subscriptions | power users/partners; ecosystem | stable domain API, OAuth/scopes, quotas | high abuse/exfiltration risk; management UI | H / Premium/Partner / Later |
| Platform: white label | partners want branded service; tenant branding and contractual ops | institutions; enterprise revenue | mature tenant isolation/support/compliance | security reviews and data-controller contracts | VH / Enterprise / Much later |

### Features intentionally not framed as easy wins

- **Receipt OCR** requires secure media processing and confidence/review, not merely an AI call.
- **Automatic savings recommendations** can become personalized financial advice; prefer user-authored scenarios.
- **Tax reporting** requires jurisdiction/version/expert ownership.
- **Stock recommendations** should remain descriptive analytics unless a regulated business model is chosen.
- **Open banking** changes consent, incident response, provider dependency and operational risk.

## 14. Recommended 12-month roadmap

### Q1 — Trust and foundation

- contain credentials/admin risks;
- define journal, transfer, money, FX and advice semantics;
- build target platform, design system, identity, CI/CD and observability;
- deliver mobile Activity and fast-add prototypes;
- reconcile schema and create migration fixtures.

### Q2 — Private-beta MVP

- accounts, transactions, transfers, CSV import and reconciliation;
- categories, budget, recurrence and cash-flow forecast;
- complete export/delete;
- responsive Home/Activity/Plan/More;
- restore, accessibility and security gates.

### Q3 — Correct planning parity

- goals and emergency reserve on transfer ledger;
- contract-aware loan estimates and statement reconciliation;
- net-worth history and data-quality inbox;
- queued multilingual notifications;
- hosted billing beta and entitlements.

### Q4 — Launch and evidence-based expansion

- public-launch hardening, canary migration and support operations;
- paid launch if Gate C passes;
- lightweight delayed holdings only if accounting/licensing is ready;
- household and open-banking discovery/prototypes;
- cited read-only AI experiment with opt-in synthetic/private-beta data only.
