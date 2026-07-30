/* eslint-disable @typescript-eslint/explicit-function-return-type */
import {
  Body,
  Controller,
  Get,
  Headers,
  HttpCode,
  Inject,
  Param,
  ParseUUIDPipe,
  Post,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiAcceptedResponse,
  ApiCookieAuth,
  ApiHeader,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  CreateDeletionRequestDto,
  PrivacyExportRequestResponseDto,
  PrivacyExportStatusResponseDto,
  PrivacyRequestResponseDto,
} from './privacy.dto';
import { PrivacyExportService } from './privacy-export.service';
import { PrivacyQueueService } from './privacy-queue.service';

@ApiTags('Privacy')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('privacy')
export class PrivacyController {
  constructor(
    @Inject(PrivacyQueueService) private readonly queue: PrivacyQueueService,
    @Inject(PrivacyExportService) private readonly exports: PrivacyExportService,
  ) {}

  @Post('exports')
  @HttpCode(202)
  @ApiHeader({
    name: 'Idempotency-Key',
    required: true,
    description: 'Stable key for this account-export request',
    example: 'export-2026-07-30-synthetic',
  })
  @ApiOperation({ summary: 'Queue a complete manifest-versioned JSON and CSV account export' })
  @ApiAcceptedResponse({ type: PrivacyExportRequestResponseDto })
  createExport(@Req() request: Request, @Headers('idempotency-key') key = '') {
    return this.queue.enqueueExport(userId(request), key);
  }

  @Get('exports/:id')
  @ApiOperation({ summary: 'Read owned export status and short-lived private download links' })
  @ApiParam({ name: 'id', type: String, format: 'uuid' })
  @ApiOkResponse({ type: PrivacyExportStatusResponseDto })
  exportStatus(
    @Req() request: Request,
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
  ) {
    return this.exports.status(userId(request), id);
  }

  @Post('deletion-requests')
  @HttpCode(202)
  @ApiHeader({
    name: 'Idempotency-Key',
    required: true,
    description: 'Stable key for this destructive account-deletion request',
    example: 'delete-account-synthetic-confirmation',
  })
  @ApiOperation({
    summary: 'Reauthenticate and queue complete account deletion and external-state cleanup',
  })
  @ApiAcceptedResponse({ type: PrivacyRequestResponseDto })
  createDeletion(
    @Req() request: Request,
    @Body() dto: CreateDeletionRequestDto,
    @Headers('idempotency-key') key = '',
  ) {
    return this.queue.enqueueDeletion(userId(request), dto, key);
  }
}

function userId(request: Request): string {
  return request.session.principal!.userId;
}
