import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  HttpStatus,
  Inject,
  Param,
  ParseUUIDPipe,
  Post,
  Put,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiCookieAuth,
  ApiBadRequestResponse,
  ApiConflictResponse,
  ApiCreatedResponse,
  ApiForbiddenResponse,
  ApiNoContentResponse,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import type { Request } from 'express';
import {
  EmailVerificationDto,
  EmailVerificationRequestDto,
  PasskeyAuthenticationDto,
  PasskeyAuthenticationOptionsDto,
  PasskeyLabelDto,
  PasswordChangeDto,
  PasswordSessionDto,
  RegistrationDto,
} from './identity.dto';
import { AuthenticationGuard, VerifiedEmailGuard } from './authentication.guard';
import { IdentityService } from './identity.service';
import { PasskeyService } from './passkey.service';
import { SessionService } from './session.service';
import {
  PasskeyAuthenticationOptionsResponseDto,
  PasskeyListResponseDto,
  PasskeyRegistrationOptionsResponseDto,
  PasskeyRegistrationResponseDto,
} from './webauthn.dto';

@ApiTags('Identity')
@Controller()
export class IdentityController {
  constructor(
    @Inject(IdentityService) private readonly identity: IdentityService,
    @Inject(SessionService) private readonly sessions: SessionService,
    @Inject(PasskeyService) private readonly passkeys: PasskeyService,
  ) {}

  @Post('auth/registrations')
  @HttpCode(HttpStatus.ACCEPTED)
  @ApiOperation({ summary: 'Register an account and issue email verification' })
  async register(@Body() dto: RegistrationDto): Promise<{ status: string }> {
    await this.identity.register(dto);
    return { status: 'accepted' };
  }

  @Post('auth/email-verifications')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  async verify(@Body() dto: EmailVerificationDto, @Req() request: Request): Promise<void> {
    await this.identity.verifyEmail(dto.token, request);
  }

  @Post('auth/email-verification-requests')
  @HttpCode(HttpStatus.ACCEPTED)
  async requestVerification(@Body() dto: EmailVerificationRequestDto): Promise<{ status: string }> {
    await this.identity.requestVerification(dto.email);
    return { status: 'accepted' };
  }

  @Post('auth/sessions')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  async login(@Body() dto: PasswordSessionDto, @Req() request: Request): Promise<void> {
    await this.identity.login({
      email: dto.email,
      password: dto.password,
      remember: dto.remember ?? false,
      request,
    });
  }

  @Delete('auth/session')
  @UseGuards(AuthenticationGuard)
  @ApiCookieAuth()
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  async logout(@Req() request: Request): Promise<void> {
    await this.sessions.revoke(request);
  }

  @Put('users/me/password')
  @UseGuards(AuthenticationGuard)
  @ApiCookieAuth()
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  async changePassword(@Body() dto: PasswordChangeDto, @Req() request: Request): Promise<void> {
    await this.identity.changePassword(
      request.session.principal!.userId,
      dto.currentPassword,
      dto.newPassword,
    );
    await this.sessions.revoke(request);
  }

  @Post('auth/passkeys/registration-options')
  @UseGuards(AuthenticationGuard, VerifiedEmailGuard)
  @ApiCookieAuth()
  @ApiOperation({ summary: 'Create single-use passkey registration options' })
  @ApiCreatedResponse({ type: PasskeyRegistrationOptionsResponseDto })
  @ApiUnauthorizedResponse({ description: 'An authenticated session is required' })
  @ApiForbiddenResponse({ description: 'Email verification is required' })
  registrationOptions(@Req() request: Request): Promise<PasskeyRegistrationOptionsResponseDto> {
    return this.passkeys.registrationOptions(request.session.principal!.userId, request);
  }

  @Post('auth/passkeys')
  @UseGuards(AuthenticationGuard, VerifiedEmailGuard)
  @ApiCookieAuth()
  @ApiOperation({ summary: 'Register a verified WebAuthn credential for the current user' })
  @ApiCreatedResponse({ type: PasskeyRegistrationResponseDto })
  @ApiBadRequestResponse({ description: 'The credential payload is invalid' })
  @ApiUnauthorizedResponse({ description: 'The session or WebAuthn ceremony is invalid' })
  @ApiForbiddenResponse({ description: 'Email verification is required' })
  @ApiConflictResponse({ description: 'The passkey is already registered' })
  async registerPasskey(
    @Body() dto: PasskeyLabelDto,
    @Req() request: Request,
  ): Promise<PasskeyRegistrationResponseDto> {
    return this.passkeys.register(
      request.session.principal!.userId,
      dto.label,
      dto.credential,
      request,
    );
  }

  @Get('auth/passkeys')
  @UseGuards(AuthenticationGuard, VerifiedEmailGuard)
  @ApiCookieAuth()
  @ApiOperation({ summary: 'List safe metadata for the current user passkeys' })
  @ApiOkResponse({ type: PasskeyListResponseDto })
  @ApiUnauthorizedResponse({ description: 'An authenticated session is required' })
  @ApiForbiddenResponse({ description: 'Email verification is required' })
  listPasskeys(@Req() request: Request): Promise<PasskeyListResponseDto> {
    return this.passkeys.list(request.session.principal!.userId);
  }

  @Post('auth/passkey-sessions/options')
  @ApiOperation({ summary: 'Create single-use passkey authentication options' })
  @ApiCreatedResponse({ type: PasskeyAuthenticationOptionsResponseDto })
  passkeyOptions(
    @Body() _dto: PasskeyAuthenticationOptionsDto,
    @Req() request: Request,
  ): Promise<PasskeyAuthenticationOptionsResponseDto> {
    return this.passkeys.authenticationOptions(request);
  }

  @Post('auth/passkey-sessions')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  @ApiBadRequestResponse({ description: 'The credential payload is invalid' })
  @ApiUnauthorizedResponse({ description: 'Passkey authentication failed' })
  async passkeyLogin(
    @Body() dto: PasskeyAuthenticationDto,
    @Req() request: Request,
  ): Promise<void> {
    await this.passkeys.authenticate(dto.credential, dto.remember ?? false, request);
  }

  @Delete('auth/passkeys/:id')
  @UseGuards(AuthenticationGuard, VerifiedEmailGuard)
  @ApiCookieAuth()
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
  @ApiBadRequestResponse({ description: 'The server-owned passkey identifier is invalid' })
  @ApiUnauthorizedResponse({ description: 'An authenticated session is required' })
  @ApiForbiddenResponse({ description: 'Email verification is required' })
  @ApiNotFoundResponse({ description: 'The owned passkey was not found' })
  @ApiParam({ name: 'id', type: String, format: 'uuid', description: 'Server-owned passkey ID' })
  async deletePasskey(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.passkeys.delete(request.session.principal!.userId, id);
  }
}
