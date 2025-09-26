# Security & GDPR Guidance

## Environment variables

The application reads configuration from environment variables when `config/load_env.php` is included. You can either:

- define variables in your web server/service manager (e.g. `systemd` unit files, Docker `env_file`, Apache `SetEnv`), or
- create a `.env` file based on `.env.example`. The loader accepts simple `KEY=value` pairs and ignores comment lines that begin with `#`.

Make sure the environment file is not world-readable and is excluded from version control.

## Application-level encryption

- `MM_DATA_KEY` must be a high-entropy secret shared by **every** process that touches the database (web, CLI, workers).
- The key can be supplied as raw text or as Base64; it is hashed internally to derive the cryptographic key material.
- When Sodium is available, the app uses `sodium_crypto_secretbox` (XSalsa20-Poly1305). Otherwise it falls back to AES-256-GCM via OpenSSL.
- New profile names are encrypted transparently. To secure existing records, run:

  ```bash
  php scripts/encrypt_full_names.php
  ```

  The script skips records that are already encrypted and prints a summary of updated rows.

## Database security recommendations

- Enforce TLS between the application and PostgreSQL (`sslmode=require`).
- Restrict database user permissions to the minimum required by the app.
- Enable backups and auditing on the database server.
- Consider Postgres-native features such as [`pgcrypto`](https://www.postgresql.org/docs/current/pgcrypto.html) or storage-level encryption for additional protection of financial transaction data.

## Operational practices

- Rotate `MM_DATA_KEY` using a database-wide re-encryption process during planned maintenance windows.
- Periodically verify that scheduled exports and deletion flows still succeed while the key is loaded.
- Document incident response procedures and data retention timelines to meet GDPR accountability requirements.
