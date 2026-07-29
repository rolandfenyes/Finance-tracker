import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  ArrayMinSize,
  IsArray,
  IsDateString,
  IsIn,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  ValidateIf,
  ValidateNested,
} from 'class-validator';
import type { FxConversionStatus } from '../currency/currency.types';
import type { CategoryKind } from './budgeting.types';

const exactAmountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const percentagePattern = /^(?:0|[1-9]\d?)(?:\.\d{1,4})?$|^100(?:\.0{1,4})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;
const monthPattern = /^\d{4}-(?:0[1-9]|1[0-2])$/;
const colorPattern = /^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/;

export class BudgetRulesQueryDto {
  @ApiPropertyOptional({
    type: String,
    example: '2026-07',
    pattern: '^\\d{4}-(?:0[1-9]|1[0-2])$',
    description:
      'When supplied, includes forecast-income planning and signed rule variance for this month.',
  })
  @IsOptional()
  @IsString()
  @Matches(monthPattern)
  month?: string;
}

export class CreateBudgetRuleDto {
  @ApiProperty({ type: String, example: 'Needs', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label!: string;

  @ApiProperty({
    type: String,
    example: '50',
    description: 'Exact percentage string between 0 and 100 inclusive.',
  })
  @IsString()
  @Matches(percentagePattern)
  percent!: string;

  @ApiPropertyOptional({
    type: String,
    nullable: true,
    example: 'Descriptive target only',
    maxLength: 500,
  })
  @IsOptional()
  @IsString()
  @Length(1, 500)
  @Matches(/\S/)
  targetHint?: string | null;
}

export class UpdateBudgetRuleDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label?: string;

  @ApiPropertyOptional({ type: String, example: '50' })
  @IsOptional()
  @IsString()
  @Matches(percentagePattern)
  percent?: string;

  @ApiPropertyOptional({ type: String, nullable: true, maxLength: 500 })
  @IsOptional()
  @IsString()
  @Length(1, 500)
  @Matches(/\S/)
  targetHint?: string | null;
}

export class ReplaceBudgetRulesDto {
  @ApiProperty({ type: CreateBudgetRuleDto, isArray: true })
  @IsArray()
  @ArrayMinSize(1)
  @ValidateNested({ each: true })
  @Type(() => CreateBudgetRuleDto)
  rules!: CreateBudgetRuleDto[];
}

export class CreateCategoryDto {
  @ApiProperty({ type: String, example: 'Groceries', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label!: string;

  @ApiProperty({ enum: ['income', 'spending'] })
  @IsIn(['income', 'spending'])
  kind!: CategoryKind;

  @ApiProperty({
    type: String,
    example: '#FACC15',
    pattern: '^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$',
  })
  @IsString()
  @Matches(colorPattern)
  color!: string;
}

export class UpdateCategoryDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label?: string;

  @ApiPropertyOptional({ enum: ['income', 'spending'] })
  @IsOptional()
  @IsIn(['income', 'spending'])
  kind?: CategoryKind;

  @ApiPropertyOptional({
    type: String,
    pattern: '^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$',
  })
  @IsOptional()
  @IsString()
  @Matches(colorPattern)
  color?: string;
}

export class AssignBudgetRuleDto {
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  @ValidateIf((_, value: unknown) => value !== null)
  @IsUUID()
  budgetRuleId!: string | null;
}

export class CreateBasicIncomeDto {
  @ApiProperty({ type: String, example: 'Salary', maxLength: 120 })
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label!: string;

  @ApiProperty({ type: String, example: '1000.00' })
  @IsString()
  @Matches(exactAmountPattern)
  amount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ type: String, format: 'date', example: '2026-07-01' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  validFrom!: string;

  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  validTo?: string | null;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @IsOptional()
  @IsUUID()
  categoryId?: string | null;
}

export class UpdateBasicIncomeDto {
  @ApiPropertyOptional({ type: String, maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  @Matches(/\S/)
  label?: string;

  @ApiPropertyOptional({ type: String, example: '1200.00' })
  @IsOptional()
  @IsString()
  @Matches(exactAmountPattern)
  amount?: string;

  @ApiPropertyOptional({ type: String, pattern: '^[A-Z]{3}$' })
  @IsOptional()
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency?: string;

  @ApiPropertyOptional({ type: String, format: 'date' })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  validFrom?: string;

  @ApiPropertyOptional({ type: String, format: 'date', nullable: true })
  @IsOptional()
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  validTo?: string | null;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @ValidateIf((_, value: unknown) => value !== undefined && value !== null)
  @IsUUID()
  categoryId?: string | null;
}

export class CategoryResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String })
  label!: string;
  @ApiProperty({ enum: ['income', 'spending'] })
  kind!: CategoryKind;
  @ApiProperty({ type: String, example: '#FACC15' })
  color!: string;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  budgetRuleId!: string | null;
  @ApiProperty({ type: String, nullable: true })
  budgetRuleLabel!: string | null;
  @ApiProperty({ type: String, nullable: true })
  systemKey!: string | null;
  @ApiProperty({ type: Boolean })
  protected!: boolean;
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class CategoriesResponseDto {
  @ApiProperty({ type: CategoryResponseDto, isArray: true })
  items!: CategoryResponseDto[];
}

export class BasicIncomeResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String })
  label!: string;
  @ApiProperty({ type: String, example: '1000' })
  amount!: string;
  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;
  @ApiProperty({ type: String, format: 'date' })
  validFrom!: string;
  @ApiProperty({ type: String, format: 'date', nullable: true })
  validTo!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  categoryId!: string | null;
  @ApiProperty({ type: String, nullable: true })
  categoryLabel!: string | null;
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class BasicIncomesResponseDto {
  @ApiProperty({ type: BasicIncomeResponseDto, isArray: true })
  items!: BasicIncomeResponseDto[];
}

export class BudgetRulePlanResponseDto {
  @ApiProperty({ enum: ['available', 'stale', 'unavailable'] })
  status!: FxConversionStatus;
  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;
  @ApiPropertyOptional({ type: String, example: '500' })
  plannedAmount?: string;
  @ApiPropertyOptional({ type: String, example: '575' })
  assignedCategorySpending?: string;
  @ApiPropertyOptional({
    type: String,
    example: '-75',
    description: 'Signed planned minus spending value; overspend remains negative.',
  })
  signedVariance?: string;
}

export class BudgetRuleResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;
  @ApiProperty({ type: String })
  label!: string;
  @ApiProperty({ type: String, example: '50' })
  percent!: string;
  @ApiProperty({ type: String, nullable: true })
  targetHint!: string | null;
  @ApiProperty({ type: String, format: 'uuid', isArray: true })
  assignedCategoryIds!: string[];
  @ApiProperty({ type: BudgetRulePlanResponseDto, nullable: true })
  plan!: BudgetRulePlanResponseDto | null;
  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;
  @ApiProperty({ type: String, format: 'date-time' })
  updatedAt!: string;
}

export class BudgetAllocationResponseDto {
  @ApiProperty({ type: String, example: '120' })
  totalPercent!: string;
  @ApiProperty({ enum: ['within_allocation', 'over_allocated'] })
  status!: 'within_allocation' | 'over_allocated';
  @ApiProperty({
    type: String,
    example: '20',
    description: 'Exact percentage points above 100; never normalized.',
  })
  overAllocatedBy!: string;
}

export class BudgetPlanPeriodResponseDto {
  @ApiProperty({ type: String, example: '2026-07' })
  month!: string;
  @ApiProperty({ type: String, example: 'HUF' })
  currency!: string;
  @ApiProperty({ enum: ['available', 'stale', 'unavailable'] })
  forecastIncomeStatus!: FxConversionStatus;
  @ApiPropertyOptional({ type: String, example: '1000' })
  forecastIncome?: string;
}

export class BudgetRulesResponseDto {
  @ApiProperty({ type: BudgetRuleResponseDto, isArray: true })
  items!: BudgetRuleResponseDto[];
  @ApiProperty({ type: BudgetAllocationResponseDto })
  allocation!: BudgetAllocationResponseDto;
  @ApiProperty({ type: BudgetPlanPeriodResponseDto, nullable: true })
  period!: BudgetPlanPeriodResponseDto | null;
}
