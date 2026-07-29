import { ApiProperty } from '@nestjs/swagger';
import {
  IsBoolean,
  IsDateString,
  IsIn,
  IsOptional,
  IsString,
  Length,
  Matches,
} from 'class-validator';
import type { UserRole } from '../identity/identity.types';
import { supportedLocales, supportedThemes } from './users.constants';
import type { OnboardingDestination, SupportedLocale, SupportedTheme } from './users.constants';
import type { Entitlements, ResourceEntitlement } from './entitlements.service';

export class UpdateCurrentUserDto {
  @ApiProperty({ type: String, required: false, example: 'Ada Example' })
  @IsOptional()
  @IsString()
  @Length(1, 200)
  @Matches(/\S/)
  fullName?: string;

  @ApiProperty({ type: String, required: false, format: 'date', example: '1990-01-15' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/)
  dateOfBirth?: string;

  @ApiProperty({ type: String, required: false, enum: supportedLocales, example: 'en' })
  @IsOptional()
  @IsIn(supportedLocales)
  desiredLanguage?: SupportedLocale;
}

export class UpdateThemeDto {
  @ApiProperty({ type: String, enum: supportedThemes, example: 'verdant-horizon' })
  @IsString()
  @IsIn(supportedThemes)
  theme!: SupportedTheme;
}

export class CompleteTutorialDto {
  @ApiProperty({
    type: Boolean,
    enum: [true],
    description: 'Completes the post-onboarding tutorial. False is not a valid transition.',
  })
  @IsBoolean()
  @IsIn([true])
  tutorialCompleted!: true;
}

export class ResourceEntitlementDto implements ResourceEntitlement {
  @ApiProperty({ type: Boolean })
  allowed!: boolean;

  @ApiProperty({
    type: Number,
    nullable: true,
    description:
      'Null means unlimited only when allowed is true; otherwise the resource is unavailable.',
  })
  limit!: number | null;
}

export class ResourceEntitlementsDto {
  @ApiProperty({ type: ResourceEntitlementDto })
  currencies!: ResourceEntitlementDto;

  @ApiProperty({ type: ResourceEntitlementDto })
  activeGoals!: ResourceEntitlementDto;

  @ApiProperty({ type: ResourceEntitlementDto })
  activeLoans!: ResourceEntitlementDto;

  @ApiProperty({ type: ResourceEntitlementDto })
  categories!: ResourceEntitlementDto;

  @ApiProperty({ type: ResourceEntitlementDto })
  activeScheduledItems!: ResourceEntitlementDto;
}

export class EntitlementsDto implements Entitlements {
  @ApiProperty({ type: Boolean })
  personalFinanceAccess!: boolean;

  @ApiProperty({ type: Boolean })
  administration!: boolean;

  @ApiProperty({ type: Boolean })
  cashFlowRuleEditing!: boolean;

  @ApiProperty({ type: ResourceEntitlementsDto })
  resources!: ResourceEntitlementsDto;
}

export class CurrentUserResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ type: String, format: 'email' })
  email!: string;

  @ApiProperty({ type: String, example: 'Ada Example' })
  fullName!: string;

  @ApiProperty({ type: String, format: 'date', example: '1990-01-15' })
  dateOfBirth!: string;

  @ApiProperty({ type: String, enum: ['free', 'premium', 'admin'] })
  role!: UserRole;

  @ApiProperty({ type: Boolean })
  emailVerified!: boolean;

  @ApiProperty({ type: String, enum: supportedThemes })
  theme!: SupportedTheme;

  @ApiProperty({ type: String, enum: supportedLocales })
  desiredLanguage!: SupportedLocale;

  @ApiProperty({ type: EntitlementsDto })
  entitlements!: EntitlementsDto;
}

export class ThemePreferencesResponseDto {
  @ApiProperty({ type: String, enum: supportedThemes })
  theme!: SupportedTheme;

  @ApiProperty({ type: String, isArray: true, enum: supportedThemes })
  supportedThemes!: readonly SupportedTheme[];
}

export class OnboardingResponseDto {
  @ApiProperty({ type: Number, minimum: 0, maximum: 6 })
  currentStep!: number;

  @ApiProperty({
    type: String,
    enum: ['theme', 'rules', 'currencies', 'categories', 'income', 'tutorial', 'complete'],
  })
  next!: OnboardingDestination;

  @ApiProperty({ type: Boolean })
  onboardingComplete!: boolean;

  @ApiProperty({ type: Boolean })
  tutorialRequired!: boolean;

  @ApiProperty({ type: Boolean })
  tutorialCompleted!: boolean;
}
