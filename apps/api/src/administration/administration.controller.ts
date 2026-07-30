/* eslint-disable @typescript-eslint/explicit-function-return-type */
import {
  Body,
  Controller,
  Delete,
  Get,
  HttpCode,
  Inject,
  Param,
  ParseUUIDPipe,
  Patch,
  Post,
  Put,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiAcceptedResponse,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiNoContentResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { AdminGuard } from './admin.guard';
import {
  AdministrationResponseDto,
  AdminFeedbackQueryDto,
  AdminUsersQueryDto,
  CreateAdminFeedbackResponseDto,
  EmailChangeRequestDto,
  IntegrationServiceParamDto,
  PutIntegrationDto,
  UpdateAdminFeedbackDto,
  UpdateSystemSettingsDto,
  UpdateUserRoleDto,
  UpdateUserStatusDto,
} from './administration.dto';
import { AdministrationService } from './administration.service';

@ApiTags('Administration')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, AdminGuard)
@Controller('admin')
export class AdministrationController {
  constructor(
    @Inject(AdministrationService) private readonly administration: AdministrationService,
  ) {}

  @Get('dashboard')
  @ApiOperation({ summary: 'Read evidenced operational counts with explicit definitions' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  dashboard() {
    return this.administration.dashboard();
  }

  @Get('analytics')
  @ApiOperation({ summary: 'Read defined account metrics and trailing registration counts' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  analytics() {
    return this.administration.analytics();
  }

  @Get('users')
  @ApiOperation({ summary: 'List users with cursor pagination and masked identity fields' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  users(@Query() query: AdminUsersQueryDto) {
    return this.administration.listUsers(query);
  }

  @Get('users/:id')
  @ApiOperation({
    summary: 'Read a masked user administration summary and redacted login activity',
  })
  @ApiOkResponse({ type: AdministrationResponseDto })
  user(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string) {
    return this.administration.userDetail(id);
  }

  @Put('users/:id/role')
  @ApiOperation({ summary: 'Assign one of the approved fixed roles and audit the action' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  role(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: UpdateUserRoleDto,
    @Req() request: Request,
  ) {
    return this.administration.updateRole(adminId(request), id, dto.role);
  }

  @Put('users/:id/status')
  @ApiOperation({ summary: 'Activate or deactivate an account and revoke inactive sessions' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  status(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: UpdateUserStatusDto,
    @Req() request: Request,
  ) {
    return this.administration.updateStatus(adminId(request), id, dto.status);
  }

  @Post('users/:id/password-reset-request')
  @HttpCode(202)
  @ApiOperation({
    summary: 'Issue an expiring password-reset action without returning a password or token',
  })
  @ApiAcceptedResponse({ type: AdministrationResponseDto })
  passwordReset(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ) {
    return this.administration.requestPasswordReset(adminId(request), id);
  }

  @Post('users/:id/email-verification-request')
  @HttpCode(202)
  @ApiOperation({
    summary: 'Issue an expiring email-verification action without returning its token',
  })
  @ApiAcceptedResponse({ type: AdministrationResponseDto })
  verification(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ) {
    return this.administration.requestVerification(adminId(request), id);
  }

  @Post('users/:id/email-change-request')
  @HttpCode(202)
  @ApiOperation({ summary: 'Request a verified email change without changing the current address' })
  @ApiAcceptedResponse({ type: AdministrationResponseDto })
  emailChange(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: EmailChangeRequestDto,
    @Req() request: Request,
  ) {
    return this.administration.requestEmailChange(adminId(request), id, dto);
  }

  @Get('feedback')
  @ApiOperation({
    summary: 'List feedback across users with masked author identity and pagination',
  })
  @ApiOkResponse({ type: AdministrationResponseDto })
  feedback(@Query() query: AdminFeedbackQueryDto) {
    return this.administration.listFeedback(query);
  }

  @Patch('feedback/:id')
  @ApiOperation({ summary: 'Update staff-managed feedback fields and audit changed field names' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  updateFeedback(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: UpdateAdminFeedbackDto,
    @Req() request: Request,
  ) {
    return this.administration.updateFeedback(adminId(request), id, dto);
  }

  @Post('feedback/:id/responses')
  @ApiOperation({ summary: 'Add an attributed admin response and immutable audit event' })
  @ApiCreatedResponse({ type: AdministrationResponseDto })
  respond(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: CreateAdminFeedbackResponseDto,
    @Req() request: Request,
  ) {
    return this.administration.respondToFeedback(adminId(request), id, dto.message);
  }

  @Get('system')
  @ApiOperation({ summary: 'Read non-secret system settings and masked integration state' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  system() {
    return this.administration.system();
  }

  @Patch('system/settings')
  @ApiOperation({ summary: 'Update validated non-secret system settings and audit field names' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  settings(@Body() dto: UpdateSystemSettingsDto, @Req() request: Request) {
    return this.administration.updateSystem(adminId(request), dto);
  }

  @Put('integrations/:service')
  @ApiOperation({ summary: 'Encrypt and replace a write-only integration secret' })
  @ApiOkResponse({ type: AdministrationResponseDto })
  integration(
    @Param() params: IntegrationServiceParamDto,
    @Body() dto: PutIntegrationDto,
    @Req() request: Request,
  ) {
    return this.administration.putIntegration(adminId(request), params.service, dto);
  }

  @Delete('integrations/:service')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete an integration configuration and its encrypted secret' })
  @ApiNoContentResponse()
  deleteIntegration(
    @Param() params: IntegrationServiceParamDto,
    @Req() request: Request,
  ): Promise<void> {
    return this.administration.deleteIntegration(adminId(request), params.service);
  }
}

function adminId(request: Request): string {
  return request.session.principal!.userId;
}
