/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { Body, Controller, Get, Inject, Patch, Req, UseGuards } from '@nestjs/common';
import { ApiCookieAuth, ApiOkResponse, ApiOperation, ApiTags } from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { UpdateEmailPreferenceDto } from './notifications.dto';
import { NotificationsService } from './notifications.service';

@ApiTags('Notifications')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard)
@Controller('users/me/notification-preferences')
export class NotificationsController {
  constructor(@Inject(NotificationsService) private readonly notifications: NotificationsService) {}
  @Get()
  @ApiOperation({ summary: 'Read email preferences; transactional security mail remains required' })
  @ApiOkResponse({ schema: { example: { educationalEnabled: true, transactionalEnabled: true } } })
  async preference(@Req() request: Request) {
    return {
      ...(await this.notifications.preference(request.session.principal!.userId)),
      transactionalEnabled: true,
    };
  }
  @Patch()
  @ApiOperation({
    summary: 'Enable or disable educational email without disabling transactional mail',
  })
  @ApiOkResponse({ schema: { example: { educationalEnabled: false, transactionalEnabled: true } } })
  async update(@Req() request: Request, @Body() dto: UpdateEmailPreferenceDto) {
    return {
      ...(await this.notifications.setPreference(
        request.session.principal!.userId,
        dto.educationalEnabled,
      )),
      transactionalEnabled: true,
    };
  }
}
