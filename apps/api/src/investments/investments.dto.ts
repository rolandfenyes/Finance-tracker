import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  IsDateString,
  IsIn,
  IsOptional,
  IsString,
  Length,
  Matches,
  MaxLength,
  ValidateIf,
} from 'class-validator';
import { investmentFrequencies, type InvestmentFrequency } from './investment-calculator';
import type { InvestmentMovementDirection, InvestmentType } from './investments.types';

const decimalPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class CreateInvestmentDto {
  @ApiProperty({ enum: ['savings', 'etf', 'stock'], example: 'savings' })
  @IsIn(['savings', 'etf', 'stock'])
  type!: InvestmentType;
  @ApiProperty({ type: String, maxLength: 180, example: 'Synthetic reserve account' })
  @IsString()
  @Length(1, 180)
  @Matches(/\S/)
  name!: string;
  @ApiPropertyOptional({ type: String, maxLength: 180, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 180)
  @Matches(/\S/)
  provider?: string | null;
  @ApiPropertyOptional({ type: String, maxLength: 120, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  identifier?: string | null;
  @ApiPropertyOptional({ type: String, maxLength: 2000, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 2000)
  @Matches(/\S/)
  notes?: string | null;
  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiPropertyOptional({
    type: String,
    nullable: true,
    description:
      'User-authored nominal annual return scenario. Zero is allowed; negative is rejected.',
  })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Matches(decimalPattern)
  scenarioAnnualRate?: string | null;
  @ApiPropertyOptional({ enum: investmentFrequencies, default: 'monthly' })
  @IsOptional()
  @IsIn(investmentFrequencies)
  scenarioFrequency?: InvestmentFrequency;
}

export class UpdateInvestmentDto {
  @ApiPropertyOptional({ enum: ['savings', 'etf', 'stock'] })
  @IsOptional()
  @IsIn(['savings', 'etf', 'stock'])
  type?: InvestmentType;
  @ApiPropertyOptional({ type: String, maxLength: 180 })
  @IsOptional()
  @IsString()
  @Length(1, 180)
  @Matches(/\S/)
  name?: string;
  @ApiPropertyOptional({ type: String, nullable: true, maxLength: 180 })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 180)
  @Matches(/\S/)
  provider?: string | null;
  @ApiPropertyOptional({ type: String, nullable: true, maxLength: 120 })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  identifier?: string | null;
  @ApiPropertyOptional({ type: String, nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Length(1, 2000)
  @Matches(/\S/)
  notes?: string | null;
  @ApiPropertyOptional({ type: String, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsString()
  @Matches(decimalPattern)
  scenarioAnnualRate?: string | null;
  @ApiPropertyOptional({ enum: investmentFrequencies })
  @IsOptional()
  @IsIn(investmentFrequencies)
  scenarioFrequency?: InvestmentFrequency;
}

export class CreateInvestmentMovementDto {
  @ApiProperty({ enum: ['deposit', 'withdrawal'] })
  @IsIn(['deposit', 'withdrawal'])
  direction!: InvestmentMovementDirection;
  @ApiProperty({ type: String, example: '10000' })
  @IsString()
  @Matches(decimalPattern)
  amount!: string;
  @ApiProperty({ type: String, example: 'HUF' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiProperty({ type: String, format: 'date', example: '2026-07-30' })
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

export class ReverseInvestmentMovementDto {
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

export class CreateInvestmentRecurringRuleDto {
  @ApiProperty({ type: String, maxLength: 120, example: 'Monthly investment contribution' })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;
  @ApiProperty({ type: String, example: '10000' })
  @IsString()
  @Matches(decimalPattern)
  amount!: string;
  @ApiProperty({ type: String, example: 'HUF' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiProperty({ type: String, format: 'date', example: '2026-08-01' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  startsOn!: string;
  @ApiProperty({ type: String, maxLength: 512, example: 'FREQ=MONTHLY;BYMONTHDAY=1' })
  @IsString()
  @MaxLength(512)
  rrule!: string;
}

export class InvestmentResponseDto {
  @ApiProperty({ type: String, format: 'uuid' }) id!: string;
  @ApiProperty({ enum: ['savings', 'etf', 'stock'] }) type!: InvestmentType;
  @ApiProperty({ type: String }) name!: string;
  @ApiProperty({ type: String, nullable: true }) provider!: string | null;
  @ApiProperty({ type: String, nullable: true }) identifier!: string | null;
  @ApiProperty({ type: String, nullable: true }) notes!: string | null;
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String }) balance!: string;
  @ApiProperty({ type: Object }) scenario!: object;
  @ApiProperty({ type: Object, nullable: true }) recurringContributionForecast!: object | null;
  @ApiProperty({ type: [Object] }) movements!: object[];
  @ApiProperty({ type: Object, nullable: true }) recurringRule!: object | null;
  @ApiProperty({ type: String, format: 'uuid' }) accountId!: string;
  @ApiProperty({ type: String, format: 'date-time' }) createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' }) updatedAt!: string;
}

export class InvestmentsResponseDto {
  @ApiProperty({ type: [InvestmentResponseDto] }) items!: InvestmentResponseDto[];
}
