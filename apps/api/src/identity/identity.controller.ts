import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  HttpStatus,
  Inject,
  Param,
  Post,
  Put,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import {
  CurrentUserResponseDto,
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
import { IdentityRepository } from './identity.repository';
import { IdentityService } from './identity.service';
import { PasskeyService } from './passkey.service';
import { SessionService } from './session.service';

@ApiTags('Identity')
@Controller()
export class IdentityController {
  constructor(
    @Inject(IdentityService) private readonly identity: IdentityService,
    @Inject(SessionService) private readonly sessions: SessionService,
    @Inject(PasskeyService) private readonly passkeys: PasskeyService,
    @Inject(IdentityRepository) private readonly repository: IdentityRepository,
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

  @Get('users/me')
  @UseGuards(AuthenticationGuard)
  @ApiCookieAuth()
  @ApiOkResponse({ type: CurrentUserResponseDto })
  async currentUser(@Req() request: Request): Promise<CurrentUserResponseDto> {
    const principal = request.session.principal!;
    const user = await this.repository.findUserById(principal.userId);
    return {
      id: principal.userId,
      email: user!.email,
      role: principal.role,
      emailVerified: principal.emailVerified,
    };
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
  registrationOptions(@Req() request: Request): Promise<unknown> {
    return this.passkeys.registrationOptions(request.session.principal!.userId, request);
  }

  @Post('auth/passkeys')
  @UseGuards(AuthenticationGuard, VerifiedEmailGuard)
  @ApiCookieAuth()
  @ApiCreatedResponse()
  async registerPasskey(
    @Body() dto: PasskeyLabelDto,
    @Req() request: Request,
  ): Promise<{ id: string }> {
    return {
      id: await this.passkeys.register(
        request.session.principal!.userId,
        dto.label,
        dto.credential,
        request,
      ),
    };
  }

  @Post('auth/passkey-sessions/options')
  passkeyOptions(
    @Body() _dto: PasskeyAuthenticationOptionsDto,
    @Req() request: Request,
  ): Promise<unknown> {
    return this.passkeys.authenticationOptions(request);
  }

  @Post('auth/passkey-sessions')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiNoContentResponse()
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
  async deletePasskey(@Param('id') id: string, @Req() request: Request): Promise<void> {
    await this.passkeys.delete(request.session.principal!.userId, id);
  }
}
