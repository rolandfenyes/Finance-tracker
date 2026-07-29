import type { UserRole } from '../identity/identity.types';
import type { SupportedLocale, SupportedTheme } from './users.constants';

export interface UserSettingsRecord {
  id: string;
  email: string;
  fullName: string;
  dateOfBirth: string;
  role: UserRole;
  emailVerified: boolean;
  theme: SupportedTheme;
  desiredLanguage: SupportedLocale;
  onboardStep: number;
  needsTutorial: boolean;
  tutorialSeen: boolean;
  createdAt: Date;
  updatedAt: Date;
}
