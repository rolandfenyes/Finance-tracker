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
  type JournalEntry,
  type ManualEconomicType,
} from './ledger.types';

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
    type: Object,
    description:
      'Immutable main-currency conversion snapshot. convertedAmount is absent when status is unavailable.',
    example: {
      status: 'stale',
      sourceAmount: '125.50',
      sourceCurrency: 'USD',
      targetCurrency: 'HUF',
      convertedAmount: '45180.00',
      sourceRate: '1.1',
      targetRate: '396',
      conversionRate: '360',
      provider: 'frankfurter',
      rateAt: '2026-07-24T00:00:00.000Z',
      fetchedAt: '2026-07-27T08:00:00.000Z',
      precision: 2,
      roundingMode: 'HALF_EVEN',
    },
  })
  conversion?: NonNullable<JournalEntry['conversion']>;

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
