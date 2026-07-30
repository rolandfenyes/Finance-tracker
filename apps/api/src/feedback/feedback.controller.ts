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
  Query,
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
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import {
  CreateFeedbackDto,
  FeedbackListQueryDto,
  FeedbackResponseDto,
  UpdateOwnedFeedbackStatusDto,
} from './feedback.dto';
import { FeedbackService } from './feedback.service';

@ApiTags('Feedback')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard)
@Controller('feedback')
export class FeedbackController {
  constructor(@Inject(FeedbackService) private readonly feedback: FeedbackService) {}

  @Get()
  @ApiOperation({ summary: 'List only the authenticated user’s feedback and staff responses' })
  @ApiOkResponse({ type: FeedbackResponseDto })
  list(@Query() query: FeedbackListQueryDto, @Req() request: Request) {
    return this.feedback.list(userId(request), query);
  }

  @Post()
  @ApiOperation({ summary: 'Create owned bug or idea feedback' })
  @ApiCreatedResponse({ type: FeedbackResponseDto })
  create(@Body() dto: CreateFeedbackDto, @Req() request: Request) {
    return this.feedback.create(userId(request), dto);
  }

  @Patch(':id/status')
  @ApiOperation({
    summary: 'Close or reopen owned feedback without changing staff workflow states',
  })
  @ApiOkResponse({ type: FeedbackResponseDto })
  status(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: UpdateOwnedFeedbackStatusDto,
    @Req() request: Request,
  ) {
    return this.feedback.updateStatus(userId(request), id, dto);
  }

  @Delete(':id')
  @HttpCode(204)
  @ApiOperation({ summary: 'Delete owned feedback' })
  @ApiNoContentResponse()
  delete(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ): Promise<void> {
    return this.feedback.delete(userId(request), id);
  }
}

function userId(request: Request): string {
  return request.session.principal!.userId;
}
