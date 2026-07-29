export const DATABASE = Symbol('DATABASE');
export const POSTGRES_POOL = Symbol('POSTGRES_POOL');

export const APPLICATION_SCHEMA = 'mymoneymap';
export const MIGRATION_SCHEMA = 'mymoneymap_meta';
export const MIGRATION_TABLE = 'kysely_migration';
export const MIGRATION_LOCK_TABLE = 'kysely_migration_lock';
