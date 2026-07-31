import {
  Controller,
  Get,
  Inject,
  Param,
  ParseIntPipe,
  Query,
  Req,
  UseGuards,
} from '@nestjs/common';
import {
  ApiBadRequestResponse,
  ApiCookieAuth,
  ApiOkResponse,
  ApiOperation,
  ApiParam,
  ApiTags,
  ApiUnprocessableEntityResponse,
} from '@nestjs/swagger';
import type { Request } from 'express';
import { AuthenticationGuard } from '../identity/authentication.guard';
import { PersonalFinanceAccessGuard } from '../users/entitlements.service';
import {
  MonthReportQueryDto,
  MonthReportResponseDto,
  ReportYearsResponseDto,
  YearReportResponseDto,
} from './reporting.dto';
import { ReportingService } from './reporting.service';

@ApiTags('Reporting')
@ApiCookieAuth()
@UseGuards(AuthenticationGuard, PersonalFinanceAccessGuard)
@Controller('reports')
export class ReportingController {
  constructor(@Inject(ReportingService) private readonly reporting: ReportingService) {}

  @Get('months/current')
  @ApiOperation({
    summary: 'Read the current Budapest calendar-month dashboard report',
    description:
      'Separates posted activity, forecast sources, and the combined projection. This is a cash-flow report, not net worth.',
  })
  @ApiOkResponse({ type: MonthReportResponseDto })
  @ApiBadRequestResponse({ description: 'An activity filter or cursor is invalid' })
  @ApiUnprocessableEntityResponse({ description: 'A financial filter or forecast is invalid' })
  current(
    @Query() query: MonthReportQueryDto,
    @Req() request: Request,
  ): Promise<MonthReportResponseDto> {
    return this.reporting.currentMonth(request.session.principal!.userId, query);
  }

  @Get('months/:year/:month')
  @ApiOperation({
    summary: 'Read a month report with stable posted-activity pagination and filters',
    description:
      'Totals reconcile over the complete filtered source set and therefore do not change between activity pages.',
  })
  @ApiOkResponse({ type: MonthReportResponseDto })
  @ApiParam({
    name: 'year',
    schema: { type: 'integer', example: 2026, minimum: 1, maximum: 9999 },
  })
  @ApiParam({
    name: 'month',
    schema: { type: 'integer', example: 7, minimum: 1, maximum: 12 },
  })
  @ApiBadRequestResponse({ description: 'The period, activity filter, or cursor is invalid' })
  @ApiUnprocessableEntityResponse({ description: 'A financial filter or forecast is invalid' })
  month(
    @Param('year', ParseIntPipe) year: number,
    @Param('month', ParseIntPipe) month: number,
    @Query() query: MonthReportQueryDto,
    @Req() request: Request,
  ): Promise<MonthReportResponseDto> {
    return this.reporting.month(request.session.principal!.userId, year, month, query);
  }

  @Get('years')
  @ApiOperation({ summary: 'List years that have owned report sources' })
  @ApiOkResponse({ type: ReportYearsResponseDto })
  years(@Req() request: Request): Promise<ReportYearsResponseDto> {
    return this.reporting.years(request.session.principal!.userId);
  }

  @Get('years/:year')
  @ApiOperation({
    summary: 'Read explainable month-by-month and annual cash-flow aggregates',
  })
  @ApiOkResponse({ type: YearReportResponseDto })
  @ApiParam({
    name: 'year',
    schema: { type: 'integer', example: 2026, minimum: 1, maximum: 9999 },
  })
  @ApiBadRequestResponse({ description: 'The year is outside the supported calendar range' })
  year(
    @Param('year', ParseIntPipe) year: number,
    @Req() request: Request,
  ): Promise<YearReportResponseDto> {
    return this.reporting.year(request.session.principal!.userId, year);
  }
}
