import { ApiProperty } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsIn,
  IsInt,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  Max,
  Min,
} from 'class-validator';
import { BudgetRulesResponseDto } from '../budgeting/budgeting.dto';
import { reportActivityKinds, type ReportActivityKind } from './reporting.types';

const amountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;

export class MonthReportQueryDto {
  @ApiProperty({ type: String, enum: reportActivityKinds, required: false })
  @IsOptional()
  @IsIn(reportActivityKinds)
  kind?: ReportActivityKind;

  @ApiProperty({ type: String, format: 'uuid', required: false })
  @IsOptional()
  @IsUUID()
  categoryId?: string;

  @ApiProperty({ type: String, pattern: '^[A-Z]{3}$', required: false })
  @IsOptional()
  @Matches(/^[A-Z]{3}$/)
  currency?: string;

  @ApiProperty({ type: String, minLength: 1, maxLength: 120, required: false })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  query?: string;

  @ApiProperty({ type: String, pattern: amountPattern.source, required: false })
  @IsOptional()
  @Matches(amountPattern)
  minAmount?: string;

  @ApiProperty({ type: String, pattern: amountPattern.source, required: false })
  @IsOptional()
  @Matches(amountPattern)
  maxAmount?: string;

  @ApiProperty({ type: Number, minimum: 1, maximum: 100, default: 25, required: false })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100)
  limit?: number;

  @ApiProperty({ type: String, minLength: 1, maxLength: 1000, required: false })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  cursor?: string;
}

export class ReportConversionSummaryDto {
  @ApiProperty({ type: String, enum: ['available', 'stale', 'unavailable'] })
  status!: 'available' | 'stale' | 'unavailable';

  @ApiProperty({
    type: Boolean,
    description: 'False when one or more sources were excluded for unavailable FX.',
  })
  complete!: boolean;

  @ApiProperty({ type: Number })
  includedSourceCount!: number;

  @ApiProperty({ type: Number })
  unavailableSourceCount!: number;

  @ApiProperty({ type: Number })
  staleSourceCount!: number;

  @ApiProperty({ type: String, isArray: true })
  providers!: string[];

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  oldestRateAt!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  newestFetchedAt!: string | null;
}

export class ReportSummaryDto {
  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;

  @ApiProperty({ type: String, example: '1000.25' })
  income!: string;

  @ApiProperty({ type: String, example: '450.1' })
  expense!: string;

  @ApiProperty({
    type: String,
    example: '200',
    description: 'Signed internal-transfer volume; it never affects income, expense, or cash flow.',
  })
  transfer!: string;

  @ApiProperty({ type: String, example: '-10' })
  adjustmentNet!: string;

  @ApiProperty({ type: String, example: '0' })
  tradeCashNet!: string;

  @ApiProperty({
    type: String,
    example: '540.15',
    description: 'Period cash-flow change, not an account balance or net worth.',
  })
  netCashFlow!: string;

  @ApiProperty({ type: ReportConversionSummaryDto })
  conversion!: ReportConversionSummaryDto;
}

export class ReportPeriodDto {
  @ApiProperty({ type: String, format: 'date' })
  first!: string;

  @ApiProperty({ type: String, format: 'date' })
  last!: string;

  @ApiProperty({ type: Number })
  year!: number;

  @ApiProperty({ type: Number, minimum: 1, maximum: 12, required: false })
  month?: number;

  @ApiProperty({ type: String, example: 'Europe/Budapest' })
  timeZone!: string;
}

export class ReportActivityItemDto {
  @ApiProperty({ type: String, format: 'uuid' })
  sourceEntryId!: string;

  @ApiProperty({ type: String })
  economicType!: string;

  @ApiProperty({ type: String, enum: reportActivityKinds })
  kind!: ReportActivityKind;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;

  @ApiProperty({ type: String, nullable: true })
  note!: string | null;

  @ApiProperty({ type: Object })
  source!: { module: string; referenceId: string | null };

  @ApiProperty({ type: String, format: 'date' })
  postedOn!: string;

  @ApiProperty({ type: String, format: 'date-time' })
  effectiveAt!: string;

  @ApiProperty({ type: String })
  amount!: string;

  @ApiProperty({ type: String, example: 'USD' })
  currency!: string;

  @ApiProperty({ type: String, required: false })
  convertedAmount?: string;

  @ApiProperty({ type: String, example: 'HUF' })
  reportingCurrency!: string;

  @ApiProperty({ type: String, enum: ['available', 'stale', 'unavailable'] })
  conversionStatus!: 'available' | 'stale' | 'unavailable';

  @ApiProperty({ type: String, nullable: true })
  provider!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  rateAt!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  fetchedAt!: string | null;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  reversesEntryId!: string | null;
}

export class ReportActivityPageDto {
  @ApiProperty({ type: ReportActivityItemDto, isArray: true })
  items!: ReportActivityItemDto[];

  @ApiProperty({ type: String, nullable: true })
  nextCursor!: string | null;
}

export class ForecastSourceDto {
  @ApiProperty({ type: String, enum: ['basic_income', 'recurring_rule'] })
  sourceKind!: 'basic_income' | 'recurring_rule';

  @ApiProperty({ type: String, format: 'uuid' })
  sourceId!: string;

  @ApiProperty({
    type: String,
    description: 'Stable source occurrence identifier: source UUID plus occurrence date.',
  })
  sourceEntryId!: string;

  @ApiProperty({ type: String })
  label!: string;

  @ApiProperty({ type: String, format: 'date' })
  occurrenceOn!: string;

  @ApiProperty({ type: String, enum: ['income', 'expense', 'transfer'] })
  kind!: 'income' | 'expense' | 'transfer';

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;

  @ApiProperty({ type: String })
  amount!: string;

  @ApiProperty({ type: String, example: 'USD' })
  currency!: string;

  @ApiProperty({ type: String, required: false })
  convertedAmount?: string;

  @ApiProperty({ type: String, example: 'HUF' })
  reportingCurrency!: string;

  @ApiProperty({ type: String, enum: ['available', 'stale', 'unavailable'] })
  conversionStatus!: 'available' | 'stale' | 'unavailable';

  @ApiProperty({ type: String, nullable: true })
  provider!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  rateAt!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  fetchedAt!: string | null;
}

export class ForecastReportDto {
  @ApiProperty({ type: ReportSummaryDto })
  summary!: ReportSummaryDto;

  @ApiProperty({ type: ForecastSourceDto, isArray: true })
  sources!: ForecastSourceDto[];
}

export class MonthReportResponseDto {
  @ApiProperty({ type: ReportPeriodDto })
  period!: ReportPeriodDto;

  @ApiProperty({ type: ReportSummaryDto })
  posted!: ReportSummaryDto;

  @ApiProperty({ type: ForecastReportDto })
  forecast!: ForecastReportDto;

  @ApiProperty({ type: ReportSummaryDto })
  combinedProjection!: ReportSummaryDto;

  @ApiProperty({
    type: BudgetRulesResponseDto,
    description: 'Approved rule-level planned value, assigned spending, and signed variance.',
  })
  budget!: BudgetRulesResponseDto;

  @ApiProperty({ type: ReportActivityPageDto })
  activity!: ReportActivityPageDto;
}

export class YearMonthReportDto {
  @ApiProperty({ type: ReportPeriodDto })
  period!: ReportPeriodDto;

  @ApiProperty({ type: ReportSummaryDto })
  posted!: ReportSummaryDto;

  @ApiProperty({ type: ReportSummaryDto })
  forecast!: ReportSummaryDto;

  @ApiProperty({ type: ReportSummaryDto })
  combinedProjection!: ReportSummaryDto;
}

export class YearReportResponseDto {
  @ApiProperty({ type: ReportPeriodDto })
  period!: ReportPeriodDto;

  @ApiProperty({ type: YearMonthReportDto, isArray: true })
  months!: YearMonthReportDto[];

  @ApiProperty({ type: ReportSummaryDto })
  posted!: ReportSummaryDto;

  @ApiProperty({ type: ReportSummaryDto })
  forecast!: ReportSummaryDto;

  @ApiProperty({ type: ReportSummaryDto })
  combinedProjection!: ReportSummaryDto;
}

export class ReportYearIndexItemDto {
  @ApiProperty({ type: Number })
  year!: number;
}

export class ReportYearsResponseDto {
  @ApiProperty({ type: ReportYearIndexItemDto, isArray: true })
  items!: ReportYearIndexItemDto[];
}
