import { Inject, Injectable } from '@nestjs/common';
import { CLOCK, type Clock } from '../platform/time/clock';
import { ApplicationError } from '../platform/http/application-error';
import { EntitlementsService } from './entitlements.service';
import type { OnboardingDestination, SupportedLocale, SupportedTheme } from './users.constants';
import { supportedThemes } from './users.constants';
import type {
  CurrentUserResponseDto,
  OnboardingResponseDto,
  ThemePreferencesResponseDto,
} from './users.dto';
import { UsersRepository } from './users.repository';
import type { UserSettingsRecord } from './users.types';

@Injectable()
export class UsersService {
  constructor(
    @Inject(UsersRepository) private readonly repository: UsersRepository,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async currentUser(userId: string): Promise<CurrentUserResponseDto> {
    const user = await this.requireUser(userId);
    return {
      id: user.id,
      email: user.email,
      fullName: user.fullName,
      dateOfBirth: user.dateOfBirth,
      role: user.role,
      emailVerified: user.emailVerified,
      theme: user.theme,
      desiredLanguage: user.desiredLanguage,
      entitlements: this.entitlements.forRole(user.role),
    };
  }

  async updateProfile(
    userId: string,
    values: { fullName?: string; dateOfBirth?: string; desiredLanguage?: SupportedLocale },
  ): Promise<CurrentUserResponseDto> {
    if (
      values.fullName === undefined &&
      values.dateOfBirth === undefined &&
      values.desiredLanguage === undefined
    ) {
      throw new ApplicationError(400, 'BAD_REQUEST', 'At least one profile field is required');
    }
    const normalized = {
      ...values,
      fullName: values.fullName?.trim(),
    };
    const user = await this.repository.updateProfile(userId, normalized, this.clock.now().toDate());
    if (!user) throw notFound();
    return this.currentUser(user.id);
  }

  async themePreferences(userId: string): Promise<ThemePreferencesResponseDto> {
    const user = await this.requireUser(userId);
    return { theme: user.theme, supportedThemes };
  }

  async updateTheme(userId: string, theme: SupportedTheme): Promise<ThemePreferencesResponseDto> {
    const user = await this.repository.updateTheme(userId, theme, this.clock.now().toDate());
    if (!user) throw notFound();
    return { theme: user.theme, supportedThemes };
  }

  async onboarding(userId: string): Promise<OnboardingResponseDto> {
    return mapOnboarding(await this.requireUser(userId));
  }

  async completeTutorial(userId: string): Promise<OnboardingResponseDto> {
    const user = await this.repository.completeTutorial(userId, this.clock.now().toDate());
    if (!user) throw notFound();
    return mapOnboarding(user);
  }

  private async requireUser(userId: string): Promise<UserSettingsRecord> {
    const user = await this.repository.findById(userId);
    if (!user) throw notFound();
    return user;
  }
}

export function mapOnboarding(user: UserSettingsRecord): OnboardingResponseDto {
  let next: OnboardingDestination;
  if (user.onboardStep < 2) next = 'theme';
  else if (user.onboardStep === 2) next = 'rules';
  else if (user.onboardStep === 3) next = 'currencies';
  else if (user.onboardStep === 4) next = 'categories';
  else if (user.onboardStep === 5) next = 'income';
  else if (user.needsTutorial && !user.tutorialSeen) next = 'tutorial';
  else next = 'complete';

  return {
    currentStep: user.onboardStep,
    next,
    onboardingComplete: user.onboardStep >= 6,
    tutorialRequired: user.needsTutorial,
    tutorialCompleted: user.tutorialSeen,
  };
}

function notFound(): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', 'User not found');
}
