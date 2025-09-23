````md
# MyMoneyMap (PHP + PostgreSQL)

Modern, mobile‑first personal finance tracker using Tailwind CSS and Chart.js. Supports Dave Ramsey’s Baby Steps via dedicated tables and UI hooks. Includes:

-   Auth (register/login/logout)
-   Transactions (income/spending), custom categories
-   Currencies & main currency per user
-   Loans with payments & progress
-   Stocks (buy/sell) with portfolio value
-   Emergency fund & transactions
-   Financial goals & progress
-   Scheduled (recurring) payments (RRULE-like string)
-   Dashboard & month/year drill‑down views

## Quick Start

1. Copy files into a PHP web root (e.g., `money-map/`).
2. Install dependencies (none required beyond PHP 8.1+, PostgreSQL, and internet access for CDNs).
3. Create DB and run migrations:
    ```bash
    createdb moneymap
    psql moneymap < migrations/001_init.sql
    ```
````

4. Set DB credentials in `config/config.php`.
5. Launch a PHP dev server from the project root (routes automatically point to `index.php`):
    ```bash
    php -S localhost:8080 index.php
    ```
6. Open http://localhost:8080

## Tech

-   PHP 8.1+, PDO, PostgreSQL 13+
-   Tailwind via CDN (JIT, mobile‑first)
-   Alpine.js for sprinkles, Chart.js for charts

## Notes

-   This is a production‑grade scaffold with secure patterns (prepared statements, password hashing). Extend as needed.
-   Dave Ramsey support: use `baby_steps` for status + `emergency_fund`, `goals`, `loans` to reflect progress.

```

```
