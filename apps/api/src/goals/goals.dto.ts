import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsDateString,
  IsIn,
  IsInt,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  MaxLength,
  ValidateIf,
  ValidateNested,
} from 'class-validator';
import type { GoalStatus } from './goals.types';

const amountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;
const editableStatuses = ['active', 'paused'] as const;

export class CreateGoalDto {
  @ApiProperty({ type: String, example: 'Emergency laptop', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;

  @ApiProperty({ type: String, example: '1000.00' })
  @IsString()
  @Matches(amountPattern)
  targetAmount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  deadline?: string | null;

  @ApiPropertyOptional({ type: Number, example: 3, default: 3 })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  priority?: number;

  @ApiPropertyOptional({ enum: editableStatuses, default: 'active' })
  @IsOptional()
  @IsIn(editableStatuses)
  status?: Exclude<GoalStatus, 'completed'>;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsUUID()
  categoryId?: string | null;
}

export class UpdateGoalDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title?: string;

  @ApiPropertyOptional({ type: String, example: '1200.00' })
  @IsOptional()
  @IsString()
  @Matches(amountPattern)
  targetAmount?: string;

  @ApiPropertyOptional({ type: String, pattern: '^[A-Z]{3}$' })
  @IsOptional()
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency?: string;

  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  deadline?: string | null;

  @ApiPropertyOptional({ type: Number })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  priority?: number;

  @ApiPropertyOptional({ enum: editableStatuses })
  @IsOptional()
  @IsIn(editableStatuses)
  status?: Exclude<GoalStatus, 'completed'>;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsUUID()
  categoryId?: string | null;
}

export class CreateGoalContributionDto {
  @ApiProperty({ type: String, example: '400.00' })
  @IsString()
  @Matches(amountPattern)
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ type: String, format: 'date', example: '2026-07-29' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  occurredOn!: string;

  @ApiPropertyOptional({ type: String, maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class ReverseGoalContributionDto {
  @ApiProperty({ type: String, format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  postedOn!: string;

  @ApiPropertyOptional({ type: String, maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class CreateGoalRecurringRuleDto {
  @ApiProperty({ type: String, example: 'Monthly laptop contribution', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;

  @ApiProperty({ type: String, example: '100.00' })
  @IsString()
  @Matches(amountPattern)
  amount!: string;

  @ApiProperty({ type: String, format: 'date', example: '2026-08-01' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  startsOn!: string;

  @ApiProperty({
    type: String,
    example: 'FREQ=MONTHLY;BYMONTHDAY=1',
    maxLength: 512,
  })
  @IsString()
  @MaxLength(512)
  rrule!: string;
}

export class GoalContributionResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String, format: 'uuid' })
  journalEntryId!: string;
  @ApiProperty({ type: String })
  amount!: string;
  @ApiProperty({ type: String })
  currency!: string;
  @ApiProperty({ type: String })
  goalAmount!: string;
  @ApiProperty({ type: String })
  goalCurrency!: string;
  @ApiProperty({ type: String, format: 'date' })
  occurredOn!: string;
  @ApiProperty({ type: String, nullable: true })
  note!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  reversedByJournalEntryId!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  correctsContributionId!: string | null;
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
}

export class GoalRecurringRuleResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String })
  title!: string;
  @ApiProperty({ type: String })
  amount!: string;
  @ApiProperty({ type: String })
  currency!: string;
  @ApiProperty({ enum: ['transfer'] })
  economicType!: 'transfer';
  @ApiProperty({ type: String, format: 'date' })
  startsOn!: string;
  @ApiProperty({ type: String })
  rrule!: string;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: null;
  @ApiProperty({ type: String, nullable: true })
  categoryLabel!: null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  goalId!: string | null;
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class GoalResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String })
  title!: string;
  @ApiProperty({ type: String })
  targetAmount!: string;
  @ApiProperty({ type: String })
  currentAmount!: string;
  @ApiProperty({ type: String })
  remainingAmount!: string;
  @ApiProperty({ type: String, description: 'Derived percentage rounded to four decimal places.' })
  progressPercent!: string;
  @ApiProperty({ type: String })
  currency!: string;
  @ApiProperty({ type: String, format: 'date', nullable: true })
  deadline!: string | null;
  @ApiProperty({ type: Number })
  priority!: number;
  @ApiProperty({ enum: ['active', 'paused', 'completed'] })
  status!: GoalStatus;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;
  @ApiProperty({ type: String, nullable: true })
  categoryLabel!: string | null;
  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  archivedAt!: string | null;
  @ApiProperty({ type: GoalRecurringRuleResponseDto, nullable: true })
  recurringRule!: GoalRecurringRuleResponseDto | null;
  @ApiProperty({ type: GoalContributionResponseDto, isArray: true })
  @ValidateNested({ each: true })
  contributions!: GoalContributionResponseDto[];
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class GoalsResponseDto {
  @ApiProperty({ type: GoalResponseDto, isArray: true })
  items!: GoalResponseDto[];
}
