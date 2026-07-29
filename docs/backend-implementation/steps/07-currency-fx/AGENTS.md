# Step 07 — Currencies and Foreign Exchange

## Objective

Preserve multi-currency behavior while making conversion reproducible and fail-closed.

## Evidence

`src/fx.php`, currency settings controller, migrations 002–004, `v_fx_latest`, and findings C-05, F-01 through F-05, D-08, M-07.

## Required behavior

- supported currency catalogue and user currency membership;
- exactly one valid main currency after onboarding, enforced transactionally;
- dated FX quote with provider/source, observed rate time, fetched time, and quality/status;
- EUR-pivot conversion only if retained by approved parity decision;
- structured unavailable/stale result;
- posted-entry conversion snapshot;
- queued provider refresh and cache with retry/circuit-breaker behavior;
- forecast assumptions clearly separate from observed historical rates.

## Prohibited

Never return the input amount as a successful conversion. Never stamp today's rate as an observed future rate. Never call external providers in a required test.

## Acceptance

Cross-rate, same-currency, weekend/as-of, missing/stale provider, precision/rounding, main-currency concurrency, and historical reproducibility tests pass.

