import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsBoolean,
  IsDateString,
  IsIn,
  IsInt,
  IsObject,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  Max,
  Min,
  ValidateIf,
} from 'class-validator';

const decimal = /^(?:0|[1-9]\d{0,23})(?:\.\d{1,12})?$/;
const code = /^[a-z0-9][a-z0-9_-]{0,79}$/;
const promotionCode = /^[A-Z0-9][A-Z0-9_-]{0,79}$/;

export class PlanDto {
  @ApiProperty({ pattern: code.source }) @Matches(code) code!: string;
  @ApiProperty({ maxLength: 160 }) @IsString() @Length(1, 160) name!: string;
  @ApiPropertyOptional({ nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 2000)
  description?: string | null;
  @ApiProperty({ type: String }) @Matches(decimal) price!: string;
  @ApiProperty({ pattern: '^[A-Z]{3}$' }) @Matches(/^[A-Z]{3}$/) currency!: string;
  @ApiProperty({ enum: ['weekly', 'monthly', 'yearly', 'lifetime'] })
  @IsIn(['weekly', 'monthly', 'yearly', 'lifetime'])
  billingInterval!: string;
  @ApiProperty({ minimum: 1 }) @IsInt() @Min(1) intervalCount!: number;
  @ApiProperty({ enum: ['free', 'premium'] }) @IsIn(['free', 'premium']) roleSlug!:
    'free' | 'premium';
  @ApiPropertyOptional({ nullable: true, minimum: 0 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsInt()
  @Min(0)
  trialDays?: number | null;
  @ApiPropertyOptional({ default: true }) @IsOptional() @IsBoolean() isActive = true;
  @ApiPropertyOptional({ nullable: true, maxLength: 255 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 255)
  stripeProductId?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 255 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 255)
  stripePriceId?: string | null;
  @ApiPropertyOptional({ type: Object })
  @IsOptional()
  @IsObject()
  metadata: Record<string, string | boolean | null> = {};
}

export class UpdatePlanDto extends PlanDto {}

export class PromotionDto {
  @ApiProperty({ pattern: promotionCode.source }) @Matches(promotionCode) code!: string;
  @ApiProperty({ maxLength: 160 }) @IsString() @Length(1, 160) name!: string;
  @ApiPropertyOptional({ nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 2000)
  description?: string | null;
  @ApiPropertyOptional({ type: String, nullable: true })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @Matches(decimal)
  discountPercent?: string | null;
  @ApiPropertyOptional({ type: String, nullable: true })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @Matches(decimal)
  discountAmount?: string | null;
  @ApiPropertyOptional({ nullable: true })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @Matches(/^[A-Z]{3}$/)
  currency?: string | null;
  @ApiPropertyOptional({ nullable: true, minimum: 0 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsInt()
  @Min(0)
  maxRedemptions?: number | null;
  @ApiPropertyOptional({ nullable: true, format: 'date-time' })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsDateString()
  redeemBy?: string | null;
  @ApiPropertyOptional({ nullable: true, minimum: 0 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsInt()
  @Min(0)
  trialDays?: number | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 80 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @Matches(code)
  planCode?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 255 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 255)
  stripeCouponId?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 255 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 255)
  stripePromoCodeId?: string | null;
  @ApiPropertyOptional({ type: Object })
  @IsOptional()
  @IsObject()
  metadata: Record<string, string | boolean | null> = {};
}

export class TrialPromotionDto {
  @ApiProperty({ pattern: code.source }) @Matches(code) planCode!: string;
  @ApiPropertyOptional({ nullable: true, minimum: 1, maximum: 3650 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsInt()
  @Min(1)
  @Max(3650)
  trialDays?: number | null;
  @ApiPropertyOptional({ nullable: true, minimum: 1 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsInt()
  @Min(1)
  maxRedemptions?: number | null;
}

export class AssignSubscriptionDto {
  @ApiProperty({ format: 'uuid' }) @IsUUID('4') planId!: string;
  @ApiProperty({ enum: ['active', 'trialing', 'past_due', 'canceled', 'expired'] })
  @IsIn(['active', 'trialing', 'past_due', 'canceled', 'expired'])
  status!: string;
  @ApiPropertyOptional({ maxLength: 4000 })
  @IsOptional()
  @IsString()
  @Length(1, 4000)
  notes?: string;
}

export class UpdateInvoiceDto {
  @ApiProperty({ enum: ['draft', 'open', 'paid', 'failed', 'past_due', 'refunded', 'void'] })
  @IsIn(['draft', 'open', 'paid', 'failed', 'past_due', 'refunded', 'void'])
  status!: string;
  @ApiPropertyOptional({ nullable: true, format: 'date-time' })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsDateString()
  paidAt?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 2000)
  failureReason?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 2000)
  refundReason?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 4000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 4000)
  notes?: string | null;
}

export class PaymentDto {
  @ApiProperty({ format: 'uuid' }) @IsUUID('4') userId!: string;
  @ApiPropertyOptional({ nullable: true, format: 'uuid' })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsUUID('4')
  invoiceId?: string | null;
  @ApiProperty({ enum: ['charge', 'refund', 'adjustment'] })
  @IsIn(['charge', 'refund', 'adjustment'])
  type!: string;
  @ApiProperty({ enum: ['pending', 'succeeded', 'failed', 'canceled'] })
  @IsIn(['pending', 'succeeded', 'failed', 'canceled'])
  status!: string;
  @ApiProperty({ type: String }) @Matches(decimal) amount!: string;
  @ApiProperty({ pattern: '^[A-Z]{3}$' }) @Matches(/^[A-Z]{3}$/) currency!: string;
  @ApiPropertyOptional({ nullable: true, maxLength: 120 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 120)
  gateway?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 255 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 255)
  transactionReference?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 2000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 2000)
  failureReason?: string | null;
  @ApiPropertyOptional({ nullable: true, maxLength: 4000 })
  @IsOptional()
  @ValidateIf((_, value) => value !== null)
  @IsString()
  @Length(1, 4000)
  notes?: string | null;
  @ApiProperty({ format: 'date-time' }) @IsDateString() processedAt!: string;
}

export class BillingPageQueryDto {
  @ApiPropertyOptional({ default: 50, minimum: 1, maximum: 100 })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100)
  limit = 50;
}

export class BillingResponseDto {
  @ApiProperty({ type: Object }) data!: object;
}
