import { ApiProperty } from '@nestjs/swagger';
import { IsString, Matches } from 'class-validator';
import type { RoundingMode } from '../platform/decimal/rounding-policy';

export class AddUserCurrencyDto {
  @ApiProperty({ type: String, example: 'EUR', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  code!: string;
}

export class SetMainCurrencyDto extends AddUserCurrencyDto {}

export class CurrencyResponseDto {
  @ApiProperty({ type: String, example: 'EUR' })
  code!: string;

  @ApiProperty({ type: String, example: 'Euro' })
  name!: string;

  @ApiProperty({ type: Number, example: 2, minimum: 0, maximum: 4 })
  minorUnit!: number;

  @ApiProperty({ enum: ['DOWN', 'UP', 'HALF_UP', 'HALF_EVEN'] })
  roundingMode!: RoundingMode;
}

export class UserCurrencyResponseDto extends CurrencyResponseDto {
  @ApiProperty({ type: Boolean })
  isMain!: boolean;
}

export class CurrencyCatalogueResponseDto {
  @ApiProperty({ type: CurrencyResponseDto, isArray: true })
  items!: CurrencyResponseDto[];
}

export class UserCurrenciesResponseDto {
  @ApiProperty({ type: String, example: 'HUF' })
  mainCurrency!: string;

  @ApiProperty({ type: UserCurrencyResponseDto, isArray: true })
  items!: UserCurrencyResponseDto[];

  @ApiProperty({ type: CurrencyResponseDto, isArray: true })
  available!: CurrencyResponseDto[];
}
