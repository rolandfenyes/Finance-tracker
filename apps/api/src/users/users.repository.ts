import { Inject, Injectable } from '@nestjs/common';
import type { Pool } from 'pg';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import type { SupportedLocale, SupportedTheme } from './users.constants';
import type { UserSettingsRecord } from './users.types';
import type { UserRole } from '../identity/identity.types';

interface UserSettingsRow {
  id: string;
  email: string;
  full_name: string;
  date_of_birth: string;
  role: UserRole;
  email_verified_at: Date | null;
  theme: SupportedTheme;
  desired_language: SupportedLocale;
  onboard_step: number;
  needs_tutorial: boolean;
  tutorial_seen: boolean;
  created_at: Date;
  updated_at: Date;
}

@Injectable()
export class UsersRepository {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async findById(userId: string): Promise<UserSettingsRecord | null> {
    const result = await this.pool.query<UserSettingsRow>(
      `SELECT id, email, full_name, date_of_birth::text AS date_of_birth, role, email_verified_at, theme,
              desired_language, onboard_step, needs_tutorial, tutorial_seen, created_at, updated_at
         FROM mymoneymap.users
        WHERE id = $1
        LIMIT 1`,
      [userId],
    );
    return result.rows[0] ? mapUserSettings(result.rows[0]) : null;
  }

  async updateProfile(
    userId: string,
    values: { fullName?: string; dateOfBirth?: string; desiredLanguage?: SupportedLocale },
    now: Date,
  ): Promise<UserSettingsRecord | null> {
    const result = await this.pool.query<UserSettingsRow>(
      `UPDATE mymoneymap.users
          SET full_name = COALESCE($2, full_name),
              date_of_birth = COALESCE($3::date, date_of_birth),
              desired_language = COALESCE($4, desired_language),
              updated_at = $5
        WHERE id = $1
      RETURNING id, email, full_name, date_of_birth::text AS date_of_birth, role, email_verified_at, theme,
                desired_language, onboard_step, needs_tutorial, tutorial_seen, created_at, updated_at`,
      [
        userId,
        values.fullName ?? null,
        values.dateOfBirth ?? null,
        values.desiredLanguage ?? null,
        now,
      ],
    );
    return result.rows[0] ? mapUserSettings(result.rows[0]) : null;
  }

  async updateTheme(
    userId: string,
    theme: SupportedTheme,
    now: Date,
  ): Promise<UserSettingsRecord | null> {
    const result = await this.pool.query<UserSettingsRow>(
      `UPDATE mymoneymap.users
          SET theme = $2,
              onboard_step = GREATEST(onboard_step, 2),
              updated_at = $3
        WHERE id = $1
      RETURNING id, email, full_name, date_of_birth::text AS date_of_birth, role, email_verified_at, theme,
                desired_language, onboard_step, needs_tutorial, tutorial_seen, created_at, updated_at`,
      [userId, theme, now],
    );
    return result.rows[0] ? mapUserSettings(result.rows[0]) : null;
  }

  async completeTutorial(userId: string, now: Date): Promise<UserSettingsRecord | null> {
    const result = await this.pool.query<UserSettingsRow>(
      `UPDATE mymoneymap.users
          SET tutorial_seen = true,
              needs_tutorial = false,
              updated_at = CASE
                WHEN tutorial_seen AND NOT needs_tutorial THEN updated_at
                ELSE $2
              END
        WHERE id = $1
      RETURNING id, email, full_name, date_of_birth::text AS date_of_birth, role, email_verified_at, theme,
                desired_language, onboard_step, needs_tutorial, tutorial_seen, created_at, updated_at`,
      [userId, now],
    );
    return result.rows[0] ? mapUserSettings(result.rows[0]) : null;
  }
}

function mapUserSettings(row: UserSettingsRow): UserSettingsRecord {
  return {
    id: row.id,
    email: row.email,
    fullName: row.full_name,
    dateOfBirth: row.date_of_birth,
    role: row.role,
    emailVerified: row.email_verified_at !== null,
    theme: row.theme,
    desiredLanguage: row.desired_language,
    onboardStep: row.onboard_step,
    needsTutorial: row.needs_tutorial,
    tutorialSeen: row.tutorial_seen,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
}
