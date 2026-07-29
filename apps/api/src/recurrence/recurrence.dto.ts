import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  IsDateString,
  IsIn,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  MaxLength,
} from 'class-validator';
import { RECURRENCE_ITERATION_LIMIT } from './recurrence-rule';
import { recurrenceEconomicTypes, type RecurrenceEconomicType } from './recurrence.types';

const amountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const calendarDatePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class RecurringRulesQueryDto {
  @ApiPropertyOptional({ type: String, format: 'date', example: '2026-07-01' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  from?: string;

  @ApiPropertyOptional({ type: String, format: 'date', example: '2026-07-31' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  to?: string;
}

export class CreateRecurringRuleDto {
  @ApiProperty({ type: String, example: 'Rent', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;

  @ApiProperty({
    type: String,
    example: '125.50',
    description: 'Positive exact base-10 decimal string.',
  })
  @IsString()
  @Matches(amountPattern)
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ enum: recurrenceEconomicTypes, example: 'expense' })
  @IsIn(recurrenceEconomicTypes)
  economicType!: RecurrenceEconomicType;

  @ApiProperty({ type: String, format: 'date', example: '2026-07-31' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  startsOn!: string;

  @ApiProperty({
    type: String,
    example: 'FREQ=MONTHLY;BYMONTHDAY=31',
    maxLength: 512,
    description:
      'Canonical supported subset: DAILY/WEEKLY/MONTHLY/YEARLY, INTERVAL, BYDAY, BYMONTHDAY, BYMONTH, COUNT, UNTIL. Empty means one-time.',
  })
  @IsString()
  @MaxLength(512)
  rrule!: string;

  @ApiPropertyOptional({
    type: String,
    format: 'uuid',
    nullable: true,
    description: 'Optional owned income/spending category matching economicType.',
  })
  @IsOptional()
  @IsUUID()
  categoryId?: string | null;
}

export class UpdateRecurringRuleDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title?: string;

  @ApiPropertyOptional({ type: String, example: '125.50' })
  @IsOptional()
  @IsString()
  @Matches(amountPattern)
  amount?: string;

  @ApiPropertyOptional({ type: String, pattern: '^[A-Z]{3}$' })
  @IsOptional()
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency?: string;

  @ApiPropertyOptional({ enum: recurrenceEconomicTypes })
  @IsOptional()
  @IsIn(recurrenceEconomicTypes)
  economicType?: RecurrenceEconomicType;

  @ApiPropertyOptional({ type: String, format: 'date' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(calendarDatePattern)
  startsOn?: string;

  @ApiPropertyOptional({ type: String, maxLength: 512 })
  @IsOptional()
  @IsString()
  @MaxLength(512)
  rrule?: string;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @IsOptional()
  @IsUUID()
  categoryId?: string | null;
}

export class RecurrenceForecastResponseDto {
  @ApiProperty({ type: String, format: 'date' })
  from!: string;

  @ApiProperty({ type: String, format: 'date' })
  to!: string;

  @ApiProperty({ type: String, format: 'date', isArray: true })
  occurrences!: string[];

  @ApiProperty({ type: Boolean })
  truncated!: boolean;

  @ApiProperty({ type: Number, example: RECURRENCE_ITERATION_LIMIT })
  iterationLimit!: number;
}

export class RecurringRuleResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ type: String })
  title!: string;

  @ApiProperty({ type: String, example: '125.5' })
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;

  @ApiProperty({ enum: recurrenceEconomicTypes })
  economicType!: RecurrenceEconomicType;

  @ApiProperty({ type: String, format: 'date' })
  startsOn!: string;

  @ApiProperty({ type: String })
  rrule!: string;

  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;

  @ApiProperty({ type: String, nullable: true })
  categoryLabel!: string | null;

  @ApiProperty({
    type: String,
    format: 'uuid',
    nullable: true,
    description: 'Goal link when this transfer forecast is managed by the goals API.',
  })
  goalId!: string | null;

  @ApiProperty({ type: RecurrenceForecastResponseDto, nullable: true })
  forecast!: RecurrenceForecastResponseDto | null;

  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;

  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class RecurringRulesResponseDto {
  @ApiProperty({ type: RecurringRuleResponseDto, isArray: true })
  items!: RecurringRuleResponseDto[];
}
