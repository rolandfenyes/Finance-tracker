import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.users`)}
      ADD COLUMN theme varchar(32) NOT NULL DEFAULT 'verdant-horizon',
      ADD COLUMN desired_language varchar(2) NOT NULL DEFAULT 'en',
      ADD COLUMN onboard_step smallint NOT NULL DEFAULT 0,
      ADD COLUMN needs_tutorial boolean NOT NULL DEFAULT true,
      ADD COLUMN tutorial_seen boolean NOT NULL DEFAULT false,
      ADD CONSTRAINT users_theme_check CHECK (
        theme IN (
          'polar-quartz', 'verdant-horizon', 'celestial-tide', 'blush-nocturne',
          'ember-vanguard', 'lilac-eclipse', 'solaris-bloom', 'dune-mirage'
        )
      ),
      ADD CONSTRAINT users_desired_language_check CHECK (desired_language IN ('en', 'hu', 'es')),
      ADD CONSTRAINT users_onboard_step_check CHECK (onboard_step BETWEEN 0 AND 6),
      ADD CONSTRAINT users_tutorial_state_check CHECK (NOT tutorial_seen OR NOT needs_tutorial);
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.users`)}
      DROP CONSTRAINT users_tutorial_state_check,
      DROP CONSTRAINT users_onboard_step_check,
      DROP CONSTRAINT users_desired_language_check,
      DROP CONSTRAINT users_theme_check,
      DROP COLUMN tutorial_seen,
      DROP COLUMN needs_tutorial,
      DROP COLUMN onboard_step,
      DROP COLUMN desired_language,
      DROP COLUMN theme;
  `.execute(database);
}
