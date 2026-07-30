import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  IsDateString,
  IsOptional,
  IsString,
  IsUUID,
  Length,
  Matches,
  ValidateIf,
} from 'class-validator';

const amountPattern = /^(?:0|[1-9]\d{0,17})(?:\.\d{1,12})?$/;
const datePattern = /^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/;

export class UpdateEmergencyReserveTargetDto {
  @ApiProperty({ type: String, example: '250000.00' })
  @IsString()
  @Matches(amountPattern)
  targetAmount!: string;

  @ApiProperty({ type: String, example: 'HUF', pattern: '^[A-Z]{3}$' })
  @IsString()
  @Matches(/^[A-Z]{3}$/)
  currency!: string;

  @ApiPropertyOptional({ type: String, format: 'uuid', nullable: true })
  @IsOptional()
  @ValidateIf((_, value: unknown) => value !== null)
  @IsUUID()
  linkedInvestmentAccountId?: string | null;
}

export class CreateEmergencyReserveMovementDto {
  @ApiProperty({ type: String, example: '250.00' })
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

export class ReverseEmergencyReserveMovementDto {
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

export class EmergencyReserveMovementResponseDto {
  @ApiProperty({ type: String, format: 'uuid' }) id!: string;
  @ApiProperty({ type: String, format: 'uuid' }) journalEntryId!: string;
  @ApiProperty({ type: String, format: 'uuid' }) holdingAccountId!: string;
  @ApiProperty({ enum: ['contribution', 'withdrawal'] }) direction!: 'contribution' | 'withdrawal';
  @ApiProperty({ type: String }) amount!: string;
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String }) reserveAmount!: string;
  @ApiProperty({ type: String }) reserveCurrency!: string;
  @ApiProperty({ type: String, format: 'date' }) occurredOn!: string;
  @ApiProperty({ type: String, nullable: true }) note!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  reversedByJournalEntryId!: string | null;
  @ApiProperty({ type: String, format: 'date-time' }) createdAt!: string;
}

export class ScheduledActivityTotalResponseDto {
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String }) income!: string;
  @ApiProperty({ type: String }) expense!: string;
  @ApiProperty({ type: String }) transfer!: string;
}

export class EmergencyReserveTargetMethodologyResponseDto {
  @ApiProperty({ enum: ['manual_user_defined'] })
  code!: 'manual_user_defined';
  @ApiProperty({ enum: ['User-defined reserve target'] })
  label!: 'User-defined reserve target';
  @ApiProperty({ type: Boolean, enum: [true] })
  educationalOnly!: true;
}

export class EmergencyReserveScheduledActivityResponseDto {
  @ApiProperty({ enum: ['raw_unclassified_scheduled_activity'] })
  classification!: 'raw_unclassified_scheduled_activity';
  @ApiProperty({ enum: ['Raw scheduled activity totals'] })
  label!: 'Raw scheduled activity totals';
  @ApiProperty({ type: String, format: 'date' })
  periodFrom!: string;
  @ApiProperty({ type: String, format: 'date' })
  periodTo!: string;
  @ApiProperty({ type: ScheduledActivityTotalResponseDto, isArray: true })
  totals!: ScheduledActivityTotalResponseDto[];
}

export class EmergencyReserveResponseDto {
  @ApiProperty({ type: Boolean }) configured!: boolean;
  @ApiProperty({ type: String }) targetAmount!: string;
  @ApiProperty({ type: String }) currentAmount!: string;
  @ApiProperty({ type: String }) currency!: string;
  @ApiProperty({ type: String, format: 'uuid', nullable: true }) reserveAccountId!: string | null;
  @ApiProperty({ type: String, format: 'uuid', nullable: true })
  linkedInvestmentAccountId!: string | null;
  @ApiProperty({ type: EmergencyReserveTargetMethodologyResponseDto })
  targetMethodology!: EmergencyReserveTargetMethodologyResponseDto;
  @ApiProperty({ type: EmergencyReserveScheduledActivityResponseDto })
  scheduledActivity!: EmergencyReserveScheduledActivityResponseDto;
  @ApiProperty({ type: EmergencyReserveMovementResponseDto, isArray: true })
  movements!: EmergencyReserveMovementResponseDto[];
  @ApiProperty({ type: String, format: 'date-time', nullable: true }) createdAt!: string | null;
  @ApiProperty({ type: String, format: 'date-time', nullable: true }) updatedAt!: string | null;
}
