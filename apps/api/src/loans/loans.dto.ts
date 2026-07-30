import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsDateString,
  IsInt,
  IsOptional,
  IsString,
  Length,
  Matches,
  Max,
  MaxLength,
  Min,
  ValidateIf,
} from 'class-validator';

const decimalPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class CreateLoanDto {
  @ApiProperty({ type: String, example: 'Synthetic fixed-rate loan', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;
  @ApiProperty({ type: String, example: '120000' })
  @IsString()
  @Matches(decimalPattern)
  principal!: string;
  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiProperty({ type: String, example: '12' })
  @IsString()
  @Matches(decimalPattern)
  nominalAnnualRate!: string;
  @ApiProperty({ type: Number, example: 12 })
  @Type(() => Number)
  @IsInt()
  @Min(1)
  termMonths!: number;
  @ApiProperty({ type: String, format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  startsOn!: string;
  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  endsOn?: string | null;
  @ApiPropertyOptional({ type: Number, minimum: 1, maximum: 31, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(31)
  paymentDay?: number | null;
  @ApiPropertyOptional({ type: String, default: '0', description: 'Projection scenario only.' })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  extraPaymentScenario?: string;
  @ApiPropertyOptional({ type: String, default: '0' })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  insuranceMonthly?: string;
}

export class UpdateLoanDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title?: string;
  @ApiPropertyOptional({ type: String })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  principal?: string;
  @ApiPropertyOptional({ type: String })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  nominalAnnualRate?: string;
  @ApiPropertyOptional({ type: Number })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  termMonths?: number;
  @ApiPropertyOptional({ type: String, format: 'date' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  startsOn?: string;
  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  endsOn?: string | null;
  @ApiPropertyOptional({ type: Number, nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(31)
  paymentDay?: number | null;
  @ApiPropertyOptional({ type: String })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  extraPaymentScenario?: string;
  @ApiPropertyOptional({ type: String })
  @IsOptional()
  @IsString()
  @Matches(decimalPattern)
  insuranceMonthly?: string;
}

export class CreateLoanPaymentDto {
  @ApiProperty({ type: String, example: '11000' })
  @IsString()
  @Matches(decimalPattern)
  amount!: string;
  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiProperty({ type: String, example: '9000' })
  @IsString()
  @Matches(decimalPattern)
  principalComponent!: string;
  @ApiProperty({ type: String, example: '1500' })
  @IsString()
  @Matches(decimalPattern)
  interestComponent!: string;
  @ApiProperty({ type: String, example: '500' })
  @IsString()
  @Matches(decimalPattern)
  feeComponent!: string;
  @ApiProperty({ type: String, format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  paidOn!: string;
  @ApiPropertyOptional({ type: String, maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class ReverseLoanPaymentDto {
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

export class CreateLoanRecurringRuleDto {
  @ApiProperty({ type: String, example: 'Monthly loan repayment', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  title!: string;
  @ApiProperty({ type: String, example: '11000' })
  @IsString()
  @Matches(decimalPattern)
  amount!: string;
  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;
  @ApiProperty({ type: String, format: 'date', example: '2026-08-31' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  startsOn!: string;
  @ApiProperty({ type: String, example: 'FREQ=MONTHLY;BYMONTHDAY=31', maxLength: 512 })
  @IsString()
  @MaxLength(512)
  rrule!: string;
}

export class LoanPaymentResponseDto {
  @ApiProperty({ type: String, format: 'uuid' }) id!: string;
  @ApiProperty({ type: String, format: 'uuid' }) journalEntryId!: string;
  @ApiProperty({ type: String }) amount!: string;
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String }) principalComponent!: string;
  @ApiProperty({ type: String }) interestComponent!: string;
  @ApiProperty({ type: String }) feeComponent!: string;
  @ApiProperty({ type: String }) loanPrincipalComponent!: string;
  @ApiProperty({ type: String }) loanInterestComponent!: string;
  @ApiProperty({ type: String }) loanFeeComponent!: string;
  @ApiProperty({ type: String }) loanCurrency!: string;
  @ApiProperty({ type: Object }) conversion!: object;
  @ApiProperty({ type: String, format: 'date' }) paidOn!: string;
  @ApiProperty({ type: String, enum: ['manual', 'scheduled'] })
  source!: 'manual' | 'scheduled';
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  recurringOccurrenceId!: string | null;
  @ApiProperty({ type: String, nullable: true }) note!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  reversedByJournalEntryId!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  correctsPaymentId!: string | null;
  @ApiProperty({ type: String, format: 'date-time' }) createdAt!: string;
}

export class LoanResponseDto {
  @ApiProperty({ type: String, format: 'uuid' }) id!: string;
  @ApiProperty({ type: String }) title!: string;
  @ApiProperty({ type: String }) principal!: string;
  @ApiProperty({ type: String }) outstandingPrincipal!: string;
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String }) nominalAnnualRate!: string;
  @ApiProperty({ type: Number }) termMonths!: number;
  @ApiProperty({ type: String, format: 'date' }) startsOn!: string;
  @ApiProperty({ type: String, format: 'date', nullable: true }) endsOn!: string | null;
  @ApiProperty({ type: Number, nullable: true }) paymentDay!: number | null;
  @ApiProperty({ type: String }) extraPaymentScenario!: string;
  @ApiProperty({ type: String }) insuranceMonthly!: string;
  @ApiProperty({ type: Object }) estimate!: object;
  @ApiProperty({ type: Object, isArray: true }) projectedSchedule!: object[];
  @ApiProperty({ type: () => LoanPaymentResponseDto, isArray: true })
  payments!: LoanPaymentResponseDto[];
  @ApiProperty({ type: Object, nullable: true }) recurringRule!: object | null;
  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  completedAt!: string | null;
  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  archivedAt!: string | null;
  @ApiProperty({ type: String, format: 'date-time' }) createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' }) updatedAt!: string;
}

export class LoansResponseDto {
  @ApiProperty({ type: () => LoanResponseDto, isArray: true }) items!: LoanResponseDto[];
}
