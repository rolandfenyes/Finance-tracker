import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsString, MaxLength, MinLength } from 'class-validator';

export class CreateDeletionRequestDto {
  @ApiProperty({ type: String, example: 'synthetic.user@example.test' })
  @IsEmail()
  @MaxLength(320)
  confirmEmail!: string;

  @ApiProperty({ type: String, example: 'synthetic-current-password-123' })
  @IsString()
  @MinLength(8)
  @MaxLength(1024)
  password!: string;
}

export class PrivacyRequestResponseDto {
  @ApiProperty({ type: String, example: 'd8b50ca2-9aaf-4ce4-bf0c-af786b787c7d' })
  id!: string;

  @ApiProperty({ type: String, enum: ['queued'], example: 'queued' })
  status!: string;

  @ApiProperty({ type: String, format: 'date-time', example: '2026-07-30T10:00:00.000Z' })
  createdAt!: string;
}

export class PrivacyExportRequestResponseDto extends PrivacyRequestResponseDto {
  @ApiProperty({ type: Number, example: 1 })
  manifestVersion!: number;
}

export class PrivacyExportArtifactResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ type: String, enum: ['json', 'csv'] })
  format!: string;

  @ApiProperty({ type: String, example: 'complete_export' })
  dataset!: string;

  @ApiProperty({ type: String, example: 'application/json' })
  mediaType!: string;

  @ApiProperty({ type: String, description: 'Exact byte count serialized as a decimal string' })
  byteSize!: string;

  @ApiProperty({ type: String, pattern: '^[0-9a-f]{64}$' })
  sha256!: string;

  @ApiProperty({ type: String, format: 'date-time' })
  expiresAt!: string;

  @ApiProperty({ type: String, format: 'uri', description: 'Short-lived private download URL' })
  downloadUrl!: string;

  @ApiProperty({ type: Number, example: 300 })
  downloadUrlExpiresInSeconds!: number;
}

export class PrivacyExportStatusResponseDto {
  @ApiProperty({ type: String, format: 'uuid' })
  id!: string;

  @ApiProperty({ type: Number, example: 1 })
  manifestVersion!: number;

  @ApiProperty({
    type: String,
    enum: ['queued', 'running', 'completed', 'retryable_failed', 'dead_letter', 'expired'],
  })
  status!: string;

  @ApiProperty({ type: Number, minimum: 0, maximum: 3 })
  attemptCount!: number;

  @ApiProperty({ type: Number, enum: [3] })
  maxAttempts!: number;

  @ApiProperty({ type: String, nullable: true })
  errorCode!: string | null;

  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  startedAt!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  completedAt!: string | null;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  expiresAt!: string | null;

  @ApiProperty({ type: () => [PrivacyExportArtifactResponseDto] })
  artifacts!: PrivacyExportArtifactResponseDto[];
}
