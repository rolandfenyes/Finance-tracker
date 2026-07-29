import type { Pool } from 'pg';
import { APPLICATION_SCHEMA, MIGRATION_SCHEMA } from './database.constants';

export interface SchemaFingerprint {
  schemas: string[];
  relations: Array<{ schema: string; name: string; kind: string }>;
  columns: Array<{
    schema: string;
    relation: string;
    name: string;
    type: string;
    nullable: boolean;
    default: string | null;
  }>;
  constraints: Array<{
    schema: string;
    relation: string;
    name: string;
    definition: string;
  }>;
  indexes: Array<{ schema: string; relation: string; name: string; definition: string }>;
}

const managedSchemas = [APPLICATION_SCHEMA, MIGRATION_SCHEMA];

export async function readSchemaFingerprint(pool: Pool): Promise<SchemaFingerprint> {
  const [schemas, relations, columns, constraints, indexes] = await Promise.all([
    pool.query<{ schema: string }>(
      `SELECT nspname AS schema
         FROM pg_namespace
        WHERE nspname = ANY($1::text[])
        ORDER BY nspname`,
      [managedSchemas],
    ),
    pool.query<{ schema: string; name: string; kind: string }>(
      `SELECT n.nspname AS schema, c.relname AS name, c.relkind AS kind
         FROM pg_class c
         JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = ANY($1::text[])
          AND c.relkind IN ('r', 'p', 'v', 'm', 'S')
        ORDER BY n.nspname, c.relname`,
      [managedSchemas],
    ),
    pool.query<{
      schema: string;
      relation: string;
      name: string;
      type: string;
      nullable: boolean;
      default: string | null;
    }>(
      `SELECT n.nspname AS schema,
              c.relname AS relation,
              a.attname AS name,
              format_type(a.atttypid, a.atttypmod) AS type,
              NOT a.attnotnull AS nullable,
              pg_get_expr(d.adbin, d.adrelid) AS default
         FROM pg_attribute a
         JOIN pg_class c ON c.oid = a.attrelid
         JOIN pg_namespace n ON n.oid = c.relnamespace
         LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
        WHERE n.nspname = ANY($1::text[])
          AND c.relkind IN ('r', 'p', 'v', 'm')
          AND a.attnum > 0
          AND NOT a.attisdropped
        ORDER BY n.nspname, c.relname, a.attnum`,
      [managedSchemas],
    ),
    pool.query<{
      schema: string;
      relation: string;
      name: string;
      definition: string;
    }>(
      `SELECT n.nspname AS schema,
              c.relname AS relation,
              con.conname AS name,
              pg_get_constraintdef(con.oid, true) AS definition
         FROM pg_constraint con
         JOIN pg_class c ON c.oid = con.conrelid
         JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = ANY($1::text[])
        ORDER BY n.nspname, c.relname, con.conname`,
      [managedSchemas],
    ),
    pool.query<{ schema: string; relation: string; name: string; definition: string }>(
      `SELECT schemaname AS schema,
              tablename AS relation,
              indexname AS name,
              regexp_replace(indexdef, '\\s+', ' ', 'g') AS definition
         FROM pg_indexes
        WHERE schemaname = ANY($1::text[])
        ORDER BY schemaname, tablename, indexname`,
      [managedSchemas],
    ),
  ]);

  return {
    schemas: schemas.rows.map((row) => row.schema),
    relations: relations.rows,
    columns: columns.rows,
    constraints: constraints.rows,
    indexes: indexes.rows,
  };
}
