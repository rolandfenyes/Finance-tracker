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
  Post,
  Put,
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
  ApiUnprocessableEntityResponse,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard, VerifiedEmailGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  CreateSecuritiesCashMovementDto,
  CreateSecuritiesTradeDto,
  CreateSecuritiesRefreshJobDto,
  ClearSecuritiesPortfolioDto,
  PreviewSecuritiesImportDto,
  ReverseSecuritiesTradeDto,
  SecuritiesPricesQueryDto,
  SecuritiesQuoteQueryDto,
  SecuritiesResponseDto,
} from './securities.dto';
import { SecuritiesService } from './securities.service';
import { SecuritiesRefreshQueueService } from './securities-refresh-queue.service';

@ApiTags('Securities')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, VerifiedEmailGuard, PersonalFinanceAccessGuard)
@Controller('securities')
export class SecuritiesController {
  constructor(
    @Inject(SecuritiesService) private readonly securities: SecuritiesService,
    @Inject(SecuritiesRefreshQueueService)
    private readonly refreshQueue: SecuritiesRefreshQueueService,
  ) {}

  @Get('portfolio')
  @ApiOperation({
    summary: 'Read the owned FIFO portfolio without fetching or mutating market data',
  })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  portfolio(@Req() request: Request) {
    return this.securities.portfolio(userId(request));
  }

  @Get('activity')
  @ApiOperation({ summary: 'Read immutable trades, cash movements, and FIFO realized results' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  activity(@Req() request: Request) {
    return this.securities.activity(userId(request));
  }

  @Post('trades')
  @ApiOperation({
    summary: 'Post an atomic buy or sell with fees, FX provenance, and FIFO rebuild',
  })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  @ApiUnprocessableEntityResponse({
    description: 'FX, currency, or available-holdings invariant failed',
  })
  trade(@Body() dto: CreateSecuritiesTradeDto, @Req() request: Request) {
    return this.securities.trade(userId(request), dto);
  }

  @Post('trades/:id/reversals')
  @ApiOperation({ summary: 'Reverse linked trade cash and fee journals and rebuild FIFO' })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  reverse(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Body() dto: ReverseSecuritiesTradeDto,
    @Req() request: Request,
  ) {
    return this.securities.reverseTrade(userId(request), id, dto);
  }

  @Post('cash-movements')
  @ApiOperation({ summary: 'Transfer cash between the default cash and securities cash accounts' })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  cash(@Body() dto: CreateSecuritiesCashMovementDto, @Req() request: Request) {
    return this.securities.cashMovement(userId(request), dto);
  }

  @Post('imports')
  @ApiOperation({ summary: 'Validate and persist a fingerprinted broker CSV preview' })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  preview(@Body() dto: PreviewSecuritiesImportDto, @Req() request: Request) {
    return this.securities.previewImport(userId(request), dto);
  }

  @Post('imports/:id/commit')
  @ApiOperation({ summary: 'Commit a valid preview atomically; repeated commit is idempotent' })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  commit(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string, @Req() request: Request) {
    return this.securities.commitImport(userId(request), id);
  }

  @Post('refresh-jobs')
  @ApiOperation({ summary: 'Queue an observable, retry-safe market-data refresh' })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  refresh(@Body() dto: CreateSecuritiesRefreshJobDto, @Req() request: Request) {
    return this.refreshQueue.enqueue(userId(request), dto.instrumentIds ?? []);
  }

  @Get('refresh-jobs/:id')
  @ApiOperation({ summary: 'Read owned market-data refresh status and retry outcome' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  refreshStatus(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ) {
    return this.refreshQueue.status(userId(request), id);
  }

  @Post('portfolio-clear-requests')
  @ApiOperation({
    summary: 'Explicitly reverse all owned securities history and clear projections',
  })
  @ApiCreatedResponse({ type: SecuritiesResponseDto })
  clear(@Body() dto: ClearSecuritiesPortfolioDto, @Req() request: Request) {
    return this.securities.clear(userId(request), dto.confirmation);
  }

  @Get('quotes')
  @ApiOperation({ summary: 'Read a stored quote; absent quotes are explicitly unavailable' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  quote(@Query() query: SecuritiesQuoteQueryDto, @Req() request: Request) {
    return this.securities.quote(userId(request), query.instrumentId);
  }

  @Get('instruments/:id')
  @ApiOperation({ summary: 'Read an owned or watched canonical market instrument' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  instrument(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ) {
    return this.securities.instrument(userId(request), id);
  }

  @Get('instruments/:id/prices')
  @ApiOperation({ summary: 'Read actual trading-day prices and descriptive indicators' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  prices(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Query() query: SecuritiesPricesQueryDto,
    @Req() request: Request,
  ) {
    return this.securities.prices(userId(request), id, query.from, query.to);
  }

  @Put('watchlist/:id')
  @ApiOperation({ summary: 'Add a canonical instrument to the owned watchlist' })
  @ApiOkResponse({ type: SecuritiesResponseDto })
  watch(@Param('id', new ParseUUIDPipe({ version: '4' })) id: string, @Req() request: Request) {
    return this.securities.watch(userId(request), id, true);
  }

  @Delete('watchlist/:id')
  @HttpCode(204)
  @ApiOperation({ summary: 'Remove an instrument from the owned watchlist' })
  @ApiNoContentResponse()
  async unwatch(
    @Param('id', new ParseUUIDPipe({ version: '4' })) id: string,
    @Req() request: Request,
  ): Promise<void> {
    await this.securities.watch(userId(request), id, false);
  }
}

function userId(request: Request): string {
  return request.session.principal!.userId;
}
