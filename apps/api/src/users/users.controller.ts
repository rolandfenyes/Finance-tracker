import { Body, Controller, Get, Inject, Patch, Req, UseGuards } from '@nestjs/common';
import {
  ApiBody,
  ApiCookieAuth,
  ApiExtraModels,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  getSchemaPath,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard } from '../identity/authentication.guard';
import {
  CompleteTutorialDto,
  CurrentUserResponseDto,
  OnboardingResponseDto,
  ThemePreferencesResponseDto,
  UpdateCurrentUserDto,
  UpdateThemeDto,
} from './users.dto';
import { UsersService } from './users.service';

@ApiTags('Users and settings')
@ApiExtraModels(UpdateCurrentUserDto, UpdateThemeDto, CompleteTutorialDto)
@ApiCookieAuth()
@UseGuards(AuthenticationGuard)
@Controller('users/me')
export class UsersController {
  constructor(@Inject(UsersService) private readonly users: UsersService) {}

  @Get()
  @ApiOperation({ summary: 'Get the authenticated user profile, preferences, and entitlements' })
  @ApiOkResponse({ type: CurrentUserResponseDto })
  currentUser(@Req() request: Request): Promise<CurrentUserResponseDto> {
    return this.users.currentUser(request.session.principal!.userId);
  }

  @Patch()
  @ApiOperation({ summary: 'Update the authenticated user profile and desired language' })
  @ApiBody({
    schema: { $ref: getSchemaPath(UpdateCurrentUserDto) },
    examples: {
      profileAndLanguage: {
        summary: 'Update profile details and desired language',
        value: {
          fullName: 'Ada Example',
          dateOfBirth: '1990-01-15',
          desiredLanguage: 'hu',
        },
      },
    },
  })
  @ApiOkResponse({ type: CurrentUserResponseDto })
  updateCurrentUser(
    @Body() dto: UpdateCurrentUserDto,
    @Req() request: Request,
  ): Promise<CurrentUserResponseDto> {
    return this.users.updateProfile(request.session.principal!.userId, dto);
  }

  @Get('preferences/theme')
  @ApiOperation({ summary: 'Get the current and supported theme identifiers' })
  @ApiOkResponse({ type: ThemePreferencesResponseDto })
  theme(@Req() request: Request): Promise<ThemePreferencesResponseDto> {
    return this.users.themePreferences(request.session.principal!.userId);
  }

  @Patch('preferences/theme')
  @ApiOperation({ summary: 'Set a validated theme identifier' })
  @ApiBody({
    schema: { $ref: getSchemaPath(UpdateThemeDto) },
    examples: {
      theme: {
        summary: 'Select a supported theme identifier',
        value: { theme: 'verdant-horizon' },
      },
    },
  })
  @ApiOkResponse({ type: ThemePreferencesResponseDto })
  updateTheme(
    @Body() dto: UpdateThemeDto,
    @Req() request: Request,
  ): Promise<ThemePreferencesResponseDto> {
    return this.users.updateTheme(request.session.principal!.userId, dto.theme);
  }

  @Get('onboarding')
  @ApiOperation({ summary: 'Get persisted onboarding and tutorial progress' })
  @ApiOkResponse({ type: OnboardingResponseDto })
  onboarding(@Req() request: Request): Promise<OnboardingResponseDto> {
    return this.users.onboarding(request.session.principal!.userId);
  }

  @Patch('onboarding')
  @ApiOperation({ summary: 'Complete the post-onboarding tutorial' })
  @ApiBody({
    schema: { $ref: getSchemaPath(CompleteTutorialDto) },
    examples: {
      completion: {
        summary: 'Mark the tutorial completed',
        value: { tutorialCompleted: true },
      },
    },
  })
  @ApiOkResponse({ type: OnboardingResponseDto })
  completeTutorial(
    @Body() _dto: CompleteTutorialDto,
    @Req() request: Request,
  ): Promise<OnboardingResponseDto> {
    return this.users.completeTutorial(request.session.principal!.userId);
  }
}
