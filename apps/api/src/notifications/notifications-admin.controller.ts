/* eslint-disable @typescript-eslint/explicit-function-return-type */
import {
  Body,
  Controller,
  Get,
  HttpCode,
  Inject,
  Param,
  Patch,
  Post,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiAcceptedResponse,
  ApiCookieAuth,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AdminGuard } from '../administration/admin.guard';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import {
  CreateEmailTestJobDto,
  PreviewEmailTemplateDto,
  UpdateEmailChannelDto,
} from './notifications.dto';
import { NotificationsQueueService } from './notifications-queue.service';
import { NotificationsService } from './notifications.service';

@ApiTags('Administration')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, AdminGuard)
@Controller('admin')
export class NotificationsAdminController {
  constructor(
    @Inject(NotificationsService) private readonly notifications: NotificationsService,
    @Inject(NotificationsQueueService) private readonly queue: NotificationsQueueService,
  ) {}
  @Get('email-templates')
  @ApiOperation({
    summary: 'List versioned EN/ES/HU email template contracts without recipient data',
  })
  @ApiOkResponse({ schema: { type: 'object' } })
  templates() {
    return this.notifications.templates();
  }

  @Post('email-templates/:code/preview')
  @ApiOperation({ summary: 'Validate a template contract and render a synthetic preview' })
  @ApiOkResponse({ schema: { type: 'object' } })
  preview(@Param('code') code: string, @Body() dto: PreviewEmailTemplateDto) {
    return this.notifications.preview(code, dto.locale, dto.data);
  }

  @Post('email-test-jobs')
  @HttpCode(202)
  @ApiOperation({ summary: 'Queue an idempotent template test using synthetic template data' })
  @ApiAcceptedResponse({ schema: { type: 'object' } })
  async test(@Req() request: Request, @Body() dto: CreateEmailTestJobDto) {
    const delivery = await this.notifications.prepare({
      eventKey: `admin-test:${request.session.principal!.userId}:${dto.templateCode}:${JSON.stringify(dto.data)}`,
      recipientEmail: dto.recipientEmail,
      templateCode: dto.templateCode,
      locale: dto.locale,
      data: dto.data,
    });
    return this.queue.enqueuePrepared(delivery);
  }

  @Get('notification-channels/email')
  @ApiOperation({ summary: 'Read the only approved notification channel without provider secrets' })
  @ApiOkResponse({ schema: { type: 'object' } })
  channel() {
    return this.notifications.channel();
  }

  @Patch('notification-channels/email')
  @ApiOperation({ summary: 'Configure the email channel; production enabling remains gated' })
  @ApiOkResponse({ schema: { type: 'object' } })
  updateChannel(@Req() request: Request, @Body() dto: UpdateEmailChannelDto) {
    return this.notifications.updateChannel(request.session.principal!.userId, dto);
  }

  @Patch('email-settings')
  @ApiOperation({ summary: 'Compatibility alias for the approved email channel settings contract' })
  @ApiOkResponse({ schema: { type: 'object' } })
  updateSettings(@Req() request: Request, @Body() dto: UpdateEmailChannelDto) {
    return this.notifications.updateChannel(request.session.principal!.userId, dto);
  }
}
