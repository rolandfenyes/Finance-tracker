import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  ArrayMaxSize,
  IsArray,
  IsDateString,
  IsIn,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  MaxLength,
} from 'class-validator';
import type { TradeSide } from './securities.types';

const positiveDecimal = /^(?:0*[1-9]\d*)(?:\.\d{1,18})?$|^0*\.\d*[1-9]\d*$/;
const nonNegativeDecimal = /^(?:0|[1-9]\d{0,23})(?:\.\d{1,18})?$/;
const moneyDecimal = /^(?:0|[1-9]\d{0,23})(?:\.\d{1,12})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class CreateSecuritiesTradeDto {
  @ApiProperty({ enum: ['buy', 'sell'] })
  @IsIn(['buy', 'sell'])
  side!: TradeSide;

  @ApiProperty({ example: 'ACME' })
  @IsString()
  @Matches(/^[A-Z0-9._-]{1,32}$/)
  symbol!: string;

  @ApiProperty({ example: 'NASDAQ' })
  @IsString()
  @Matches(/^[A-Z0-9._-]{1,48}$/)
  market!: string;

  @ApiPropertyOptional({ maxLength: 120 })
  @IsOptional()
  @IsString()
  @Length(1, 120)
  exchange?: string;

  @ApiPropertyOptional({ maxLength: 240 })
  @IsOptional()
  @IsString()
  @Length(1, 240)
  name?: string;

  @ApiProperty({ type: String, example: '1.250000000000000001' })
  @IsString()
  @Matches(positiveDecimal)
  quantity!: string;

  @ApiProperty({ type: String, example: '125.50' })
  @IsString()
  @Matches(positiveDecimal)
  unitPrice!: string;

  @ApiPropertyOptional({ type: String, default: '0' })
  @IsOptional()
  @IsString()
  @Matches(nonNegativeDecimal)
  fee?: string;

  @ApiProperty({ example: 'USD' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ format: 'date-time', example: '2026-07-30T13:00:00.000Z' })
  @IsDateString()
  executedAt!: string;

  @ApiPropertyOptional({ maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class ReverseSecuritiesTradeDto {
  @ApiProperty({ format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  postedOn!: string;

  @ApiPropertyOptional({ maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class CreateSecuritiesCashMovementDto {
  @ApiProperty({ enum: ['deposit', 'withdrawal'] })
  @IsIn(['deposit', 'withdrawal'])
  direction!: 'deposit' | 'withdrawal';

  @ApiProperty({ type: String, example: '1000' })
  @IsString()
  @Matches(moneyDecimal)
  amount!: string;

  @ApiProperty({ example: 'USD' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiProperty({ format: 'date', example: '2026-07-30' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  occurredOn!: string;

  @ApiPropertyOptional({ maxLength: 1000 })
  @IsOptional()
  @IsString()
  @Length(1, 1000)
  @Matches(/\S/)
  note?: string;
}

export class PreviewSecuritiesImportDto {
  @ApiProperty({
    type: String,
    description: 'UTF-8 broker CSV. Preview is persisted by SHA-256 fingerprint before commit.',
  })
  @IsString()
  @MaxLength(2_000_000)
  csv!: string;

  @ApiPropertyOptional({
    example: 'NASDAQ',
    description: 'Required when trade rows do not contain a Market or Exchange column.',
  })
  @IsOptional()
  @IsString()
  @Matches(/^[A-Z0-9._-]{1,48}$/)
  defaultMarket?: string;
}

export class CreateSecuritiesRefreshJobDto {
  @ApiPropertyOptional({ type: [String], maxItems: 100 })
  @IsOptional()
  @IsArray()
  @ArrayMaxSize(100)
  @IsUUID('4', { each: true })
  instrumentIds?: string[];
}

export class ClearSecuritiesPortfolioDto {
  @ApiProperty({
    enum: ['CLEAR'],
    description: 'Explicit step-up confirmation phrase for the immutable reversal operation.',
  })
  @IsIn(['CLEAR'])
  confirmation!: 'CLEAR';
}

export class SecuritiesPricesQueryDto {
  @ApiProperty({ format: 'date' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  from!: string;

  @ApiProperty({ format: 'date' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(datePattern)
  to!: string;
}

export class SecuritiesQuoteQueryDto {
  @ApiProperty({ format: 'uuid' })
  @IsUUID('4')
  instrumentId!: string;
}

export class SecuritiesResponseDto {
  @ApiProperty({ type: Object }) data!: object;
}
