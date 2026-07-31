# MyMoneyMap backend v1 Postman suite

The versioned collection is generated from the frozen OpenAPI document. Run
`pnpm postman:generate` after an approved API-contract change and
`pnpm postman:check` to detect drift.

The committed environments are templates only. They contain no credentials.
`pnpm postman:acceptance` creates an isolated random PostgreSQL database,
applies every migration, seeds synthetic users, writes a temporary environment,
runs only the `Acceptance` folder with Newman, and drops the database in a
`finally` block. Provider delivery and privacy exports remain disabled.

The complete OpenAPI-derived `Contract catalogue` is intended for interactive
review. The acceptance folder is the repeatable cross-domain journey; exhaustive
rollback, retry, concurrency, decimal, date, authorization, and worker evidence
continues to run in the PostgreSQL/Redis integration suite.
