import { ApiProperty } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsDateString,
  IsIn,
  IsInt,
  IsISO8601,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  Max,
  Min,
} from 'class-validator';
import {
  ledgerEconomicTypes,
  manualEconomicTypes,
  type AdjustmentDirection,
  type LedgerEconomicType,
  type LedgerSourceModule,
  type ManualEconomicType,
} from './ledger.types';
import type { RoundingMode } from '../platform/decimal/rounding-policy';

const amountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const calendarDatePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class CreateJournalEntryDto {
  @ApiProperty({ enum: manualEconomicTypes, example: 'external_expense' })
  @IsIn(manualEconomicTypes)
  economicType!: ManualEconomicType;

  @ApiProperty({
    type: String,
    example: '125.50',
    description: 'Positive exact base-10 decimal string; direction comes from economicType.',
  })
  @IsString()
  @Matches(amountPattern)
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ type: String, format: 'date', example: '2026-07-29' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  postedOn!: string;

  @ApiProperty({
    type: String,
    required: false,
    format: 'date-time',
    example: '2026-07-29T10:15:00.000Z',
  })
  @IsOptional()
  @IsISO8601({ strict: true })
  @Matches(/Z$/)
  effectiveAt?: string;

  @ApiProperty({ type: String, required: false, format: 'uuid' })
  @IsOptional()
  @IsUUID()
  accountId?: string;

  @ApiProperty({ type: String, required: false, format: 'uuid' })
  @IsOptional()
  @IsUUID()
  sourceAccountId?: string;

  @ApiProperty({ type: String, required: false, format: 'uuid' })
  @IsOptional()
  @IsUUID()
  destinationAccountId?: string;

  @ApiProperty({ required: false, enum: ['increase', 'decrease'] })
  @IsOptional()
  @IsIn(['increase', 'decrease'])
  adjustmentDirection?: AdjustmentDirection;

  @ApiProperty({
    type: String,
    required: false,
    format: 'uuid',
    description: 'Reserved nullable linkage for the Step 08 owned-category model.',
  })
  @IsOptional()
  @IsUUID()
  categoryId?: string;

  @ApiProperty({ type: String, required: false, maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class ReverseJournalEntryDto {
  @ApiProperty({ type: String, format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  postedOn!: string;

  @ApiProperty({ type: String, required: false, format: 'date-time' })
  @IsOptional()
  @IsISO8601({ strict: true })
  @Matches(/Z$/)
  effectiveAt?: string;

  @ApiProperty({ type: String, required: false, maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class CorrectJournalEntryDto extends CreateJournalEntryDto {}

export class ListJournalEntriesDto {
  @ApiProperty({ type: String, required: false, format: 'date' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  dateFrom?: string;

  @ApiProperty({ type: String, required: false, format: 'date' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  dateTo?: string;

  @ApiProperty({ type: Number, required: false, minimum: 1, maximum: 100, default: 25 })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100)
  limit?: number;

  @ApiProperty({ type: String, required: false })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  cursor?: string;
}

export class JournalLegResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  accountId!: string | null;

  @ApiProperty({ enum: ['debit', 'credit'] })
  side!: 'debit' | 'credit';

  @ApiProperty({ type: String, example: '125.50' })
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;
}

export class JournalSourceResponseDto {
  @ApiProperty({
    enum: [
      'manual',
      'scheduling',
      'goals',
      'emergency_fund',
      'loans',
      'investments',
      'securities',
      'migration',
    ],
  })
  module!: LedgerSourceModule;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  referenceId!: string | null;
}

export class JournalConversionResponseDto {
  @ApiProperty({ enum: ['available', 'stale', 'unavailable'] })
  status!: 'available' | 'stale' | 'unavailable';

  @ApiProperty({ type: String, example: '125.50' })
  sourceAmount!: string;

  @ApiProperty({ type: String, example: 'USD', pattern: '^[A-Z]{3}$' })
  sourceCurrency!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  targetCurrency!: string;

  @ApiProperty({ type: String, required: false, example: '45180.00' })
  convertedAmount?: string;

  @ApiProperty({ type: String, required: false, example: '1.1' })
  sourceRate?: string;

  @ApiProperty({ type: String, required: false, example: '396' })
  targetRate?: string;

  @ApiProperty({ type: String, required: false, example: '360' })
  conversionRate?: string;

  @ApiProperty({ type: String, required: false, example: 'frankfurter' })
  provider?: string;

  @ApiProperty({ type: String, format: 'date-time', required: false })
  rateAt?: string;

  @ApiProperty({ type: String, format: 'date-time', required: false })
  fetchedAt?: string;

  @ApiProperty({ type: Number, minimum: 0, maximum: 4, example: 2 })
  precision!: number;

  @ApiProperty({ enum: ['DOWN', 'UP', 'HALF_UP', 'HALF_EVEN'] })
  roundingMode!: RoundingMode;
}

export class JournalEntryResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ enum: ledgerEconomicTypes })
  economicType!: LedgerEconomicType;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;

  @ApiProperty({ type: String, nullable: true })
  note!: string | null;

  @ApiProperty({ type: JournalSourceResponseDto })
  source!: JournalSourceResponseDto;

  @ApiProperty({ type: String, format: 'date' })
  postedOn!: string;

  @ApiProperty({ type: String, format: 'date-time' })
  effectiveAt!: string;

  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;

  @ApiProperty({ type: String, format: 'uuid' })
  actorUserId!: string;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  reversesEntryId!: string | null;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  replacesEntryId!: string | null;

  @ApiProperty({
    type: JournalConversionResponseDto,
    required: false,
    description:
      'Immutable main-currency conversion snapshot. convertedAmount is absent when status is unavailable.',
  })
  conversion?: JournalConversionResponseDto;

  @ApiProperty({ type: JournalLegResponseDto, isArray: true })
  legs!: JournalLegResponseDto[];
}

export class JournalCorrectionResponseDto {
  @ApiProperty({ type: JournalEntryResponseDto })
  reversal!: JournalEntryResponseDto;

  @ApiProperty({ type: JournalEntryResponseDto })
  replacement!: JournalEntryResponseDto;
}

export class JournalListResponseDto {
  @ApiProperty({ type: JournalEntryResponseDto, isArray: true })
  items!: JournalEntryResponseDto[];

  @ApiProperty({ type: String, nullable: true })
  nextCursor!: string | null;
}
