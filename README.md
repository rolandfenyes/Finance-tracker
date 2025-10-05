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

4. Copy `.env.example` to `.env` and adjust the values for your environment (database host, name, user, password, and `MM_DATA_KEY`).
5. Launch a PHP dev server from the project root (routes automatically point to `index.php`):
    ```bash
    php -S localhost:8080 index.php
    ```
6. Open http://localhost:8080

## Tech

-   PHP 8.1+, PDO, PostgreSQL 13+
-   Tailwind via CDN (JIT, mobile‑first)
-   Alpine.js for sprinkles, Chart.js for charts

## Configuration & Security

-   **Environment variables** – Use the provided `.env` file or your web server configuration to define:
    -   `MM_DB_HOST`, `MM_DB_PORT`, `MM_DB_NAME`, `MM_DB_USER`, `MM_DB_PASS`
    -   `MM_DATA_KEY` – a 32-byte secret (Base64 or raw string) used to encrypt personal data before it is written to the database.
    -   Set additional variables (e.g. locale defaults) as needed.
-   **Sensitive data encryption** – Names and other personal identifiers are encrypted at rest using Sodium (or AES-256-GCM when Sodium is unavailable). Provide a consistent `MM_DATA_KEY` in every environment (application servers, CLI jobs, and background workers). To migrate existing data, run:
    ```bash
    php scripts/encrypt_full_names.php
    ```
-   **Database connection** – Credentials are read from the environment. For production deployments consider using managed secrets stores and enforcing TLS connections to PostgreSQL. When running the CLI helpers locally, make sure `MM_DB_USER`/`MM_DB_PASS` in `.env` match an existing PostgreSQL role so the scripts can connect.
-   **Dave Ramsey support** – use `baby_steps` for status + `emergency_fund`, `goals`, `loans` to reflect progress.

## Email notifications

-   Configure sender details in `.env` using the `MM_MAIL_*` variables. By default, messages are written to `storage/logs/mail.log` so you can test locally without an SMTP server.
-   Set `MM_MAIL_TRANSPORT=smtp` and provide the SMTP host, port, credentials, and encryption to deliver messages from production (e.g. Nethely).
-   The application automatically sends verification and welcome emails after registration. Additional digests can be sent via CLI:
    ```bash
    php scripts/send_user_emails.php tips
    php scripts/send_user_emails.php weekly
    php scripts/send_user_emails.php monthly
    ```

```
