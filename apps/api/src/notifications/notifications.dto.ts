import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import {
  IsBoolean,
  IsEmail,
  IsIn,
  IsObject,
  IsOptional,
  IsString,
  Matches,
  MaxLength,
} from 'class-validator';
import type { EmailProviderKind } from './email-provider';

export class UpdateEmailPreferenceDto {
  @ApiProperty()
  @IsBoolean()
  educationalEnabled!: boolean;
}
export class PreviewEmailTemplateDto {
  @ApiProperty({ enum: ['en', 'es', 'hu'] })
  @IsIn(['en', 'es', 'hu'])
  locale!: string;

  @ApiProperty({ type: 'object', additionalProperties: { type: 'string' } })
  @IsObject()
  data!: Record<string, string>;
}
export class CreateEmailTestJobDto extends PreviewEmailTemplateDto {
  @ApiProperty()
  @IsString()
  @Matches(/^[a-z][a-z0-9_]{0,79}$/)
  templateCode!: string;

  @ApiProperty()
  @IsEmail()
  @MaxLength(320)
  recipientEmail!: string;
}
export class UpdateEmailChannelDto {
  @ApiProperty()
  @IsBoolean()
  enabled!: boolean;

  @ApiProperty({ enum: ['disabled', 'log', 'postmark', 'smtp'] })
  @IsIn(['disabled', 'log', 'postmark', 'smtp'])
  provider!: EmailProviderKind;

  @ApiPropertyOptional()
  @IsOptional()
  @IsEmail()
  fromAddress?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsEmail()
  replyToAddress?: string;
}
