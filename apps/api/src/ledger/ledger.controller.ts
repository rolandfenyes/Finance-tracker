import {
  Body,
  Controller,
  Get,
  Headers,
  Inject,
  Param,
  ParseUUIDPipe,
  Post,
  Query,
  Req,
  Res,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBody,
  ApiConflictResponse,
  ApiCookieAuth,
  ApiCreatedResponse,
  ApiExtraModels,
  ApiHeader,
  ApiNotFoundResponse,
  ApiOkResponse,
  ApiOperation,
  ApiTags,
  ApiUnprocessableEntityResponse,
  getSchemaPath,
} from '@nestjs/swagger';
import type { Request, Response } from 'express';
import { AuthenticationGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  CorrectJournalEntryDto,
  CreateJournalEntryDto,
  JournalCorrectionResponseDto,
  JournalEntryResponseDto,
  JournalListResponseDto,
  ListJournalEntriesDto,
  ReverseJournalEntryDto,
} from './ledger.dto';
import { LedgerService } from './ledger.service';

@ApiTags('Ledger')
@ApiExtraModels(CreateJournalEntryDto, CorrectJournalEntryDto, ReverseJournalEntryDto)
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, PersonalFinanceAccessGuard)
@Controller('journal/entries')
export class LedgerController {
  constructor(@Inject(LedgerService) private readonly ledger: LedgerService) {}

  @Post()
  @ApiOperation({ summary: 'Post an immutable balanced journal entry' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiBody({
    schema: { $ref: getSchemaPath(CreateJournalEntryDto) },
    examples: {
      income: {
        summary: 'External income to the default cash account',
        value: {
          economicType: 'external_income',
          amount: '1000.00',
          currency: 'HUF',
          postedOn: '2026-07-29',
          note: 'Synthetic salary fixture',
        },
      },
      transfer: {
        summary: 'Internal transfer between owned accounts',
        value: {
          economicType: 'internal_transfer',
          amount: '300.00',
          currency: 'HUF',
          postedOn: '2026-07-29',
          sourceAccountId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
          destinationAccountId: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        },
      },
    },
  })
  @ApiCreatedResponse({ type: JournalEntryResponseDto })
  @ApiUnprocessableEntityResponse({
    description: 'Financial or journal semantic validation failed',
  })
  async create(
    @Body() dto: CreateJournalEntryDto,
    @Headers('idempotency-key') idempotencyKey: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<JournalEntryResponseDto> {
    const result = await this.ledger.createManualEntry(
      request.session.principal!.userId,
      idempotencyKey,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':id/reversals')
  @ApiOperation({ summary: 'Reverse a posted journal entry without deleting history' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: JournalEntryResponseDto })
  @ApiNotFoundResponse({ description: 'The owned journal entry was not found' })
  @ApiConflictResponse({ description: 'The entry was already reversed or is a reversal' })
  async reverse(
    @Param('id', new ParseUUIDPipe({ version: '4' })) entryId: string,
    @Body() dto: ReverseJournalEntryDto,
    @Headers('idempotency-key') idempotencyKey: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<JournalEntryResponseDto> {
    const result = await this.ledger.reverseEntry(
      request.session.principal!.userId,
      entryId,
      idempotencyKey,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Post(':id/corrections')
  @ApiOperation({ summary: 'Atomically reverse and replace a posted journal entry' })
  @ApiHeader({ name: 'Idempotency-Key', required: true })
  @ApiCreatedResponse({ type: JournalCorrectionResponseDto })
  @ApiNotFoundResponse({ description: 'The owned journal entry was not found' })
  @ApiConflictResponse({ description: 'The entry was already corrected or reversed' })
  async correct(
    @Param('id', new ParseUUIDPipe({ version: '4' })) entryId: string,
    @Body() dto: CorrectJournalEntryDto,
    @Headers('idempotency-key') idempotencyKey: string | undefined,
    @Req() request: Request,
    @Res({ passthrough: true }) response: Response,
  ): Promise<JournalCorrectionResponseDto> {
    const result = await this.ledger.correctEntry(
      request.session.principal!.userId,
      entryId,
      idempotencyKey,
      dto,
    );
    response.setHeader('Idempotency-Replayed', String(result.replayed));
    return result.value;
  }

  @Get()
  @ApiOperation({ summary: 'List posted journal entries using stable cursor and date filters' })
  @ApiOkResponse({ type: JournalListResponseDto })
  list(
    @Query() query: ListJournalEntriesDto,
    @Req() request: Request,
  ): Promise<JournalListResponseDto> {
    return this.ledger.list(request.session.principal!.userId, query);
  }
}
