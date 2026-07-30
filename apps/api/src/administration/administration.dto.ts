import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsBoolean,
  IsEmail,
  IsIn,
  IsInt,
  IsObject,
  IsOptional,
  IsString,
  IsUrl,
  Length,
  Matches,
  Max,
  Min,
  ValidateIf,
} from 'class-validator';

export class AdminPageQueryDto {
  @ApiPropertyOptional({ minimum: 1, maximum: 100, default: 25 })
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  @Max(100)
  limit = 25;

  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  @Length(1, 500)
  cursor?: string;
}

export class AdminUsersQueryDto extends AdminPageQueryDto {
  @ApiPropertyOptional({ maxLength: 160 })
  @IsOptional()
  @IsString()
  @Length(1, 160)
  q?: string;

  @ApiPropertyOptional({ enum: ['free', 'premium', 'admin'] })
  @IsOptional()
  @IsIn(['free', 'premium', 'admin'])
  role?: 'free' | 'premium' | 'admin';

  @ApiPropertyOptional({ enum: ['active', 'inactive'] })
  @IsOptional()
  @IsIn(['active', 'inactive'])
  status?: 'active' | 'inactive';

  @ApiPropertyOptional({ enum: ['true', 'false'] })
  @IsOptional()
  @IsIn(['true', 'false'])
  verified?: 'true' | 'false';
}

export class AdminFeedbackQueryDto extends AdminPageQueryDto {
  @ApiPropertyOptional({ maxLength: 160 })
  @IsOptional()
  @IsString()
  @Length(1, 160)
  q?: string;

  @ApiPropertyOptional({ enum: ['bug', 'idea'] })
  @IsOptional()
  @IsIn(['bug', 'idea'])
  kind?: 'bug' | 'idea';

  @ApiPropertyOptional({ enum: ['low', 'medium', 'high'] })
  @IsOptional()
  @IsIn(['low', 'medium', 'high'])
  severity?: 'low' | 'medium' | 'high';

  @ApiPropertyOptional({ enum: ['open', 'in_progress', 'resolved', 'closed'] })
  @IsOptional()
  @IsIn(['open', 'in_progress', 'resolved', 'closed'])
  status?: 'open' | 'in_progress' | 'resolved' | 'closed';
}

export class UpdateAdminFeedbackDto {
  @ApiPropertyOptional({ enum: ['bug', 'idea'] })
  @IsOptional()
  @IsIn(['bug', 'idea'])
  kind?: 'bug' | 'idea';

  @ApiPropertyOptional({ enum: ['low', 'medium', 'high', null], nullable: true })
  @IsOptional()
  @IsIn(['low', 'medium', 'high', null])
  severity?: 'low' | 'medium' | 'high' | null;

  @ApiPropertyOptional({ enum: ['open', 'in_progress', 'resolved', 'closed'] })
  @IsOptional()
  @IsIn(['open', 'in_progress', 'resolved', 'closed'])
  status?: 'open' | 'in_progress' | 'resolved' | 'closed';

  @ApiPropertyOptional({ maxLength: 200 })
  @IsOptional()
  @IsString()
  @Length(1, 200)
  title?: string;

  @ApiPropertyOptional({ maxLength: 10000 })
  @IsOptional()
  @IsString()
  @Length(1, 10000)
  message?: string;
}

export class CreateAdminFeedbackResponseDto {
  @ApiProperty({ maxLength: 10000 })
  @IsString()
  @Length(1, 10000)
  message!: string;
}

export class UpdateUserRoleDto {
  @ApiProperty({ enum: ['free', 'premium', 'admin'] })
  @IsIn(['free', 'premium', 'admin'])
  role!: 'free' | 'premium' | 'admin';
}

export class UpdateUserStatusDto {
  @ApiProperty({ enum: ['active', 'inactive'] })
  @IsIn(['active', 'inactive'])
  status!: 'active' | 'inactive';
}

export class EmailChangeRequestDto {
  @ApiProperty({ format: 'email' })
  @IsEmail()
  @Length(3, 320)
  email!: string;
}

export class UpdateSystemSettingsDto {
  @ApiPropertyOptional({ maxLength: 160 })
  @IsOptional()
  @IsString()
  @Length(1, 160)
  siteName?: string;

  @ApiPropertyOptional({ format: 'uri', nullable: true })
  @IsOptional()
  @IsUrl({ require_protocol: true, protocols: ['http', 'https'] })
  primaryUrl?: string | null;

  @ApiPropertyOptional({ format: 'email', nullable: true })
  @IsOptional()
  @ValidateIf((_object, value) => value !== null)
  @IsEmail()
  supportEmail?: string | null;

  @ApiPropertyOptional({ format: 'email', nullable: true })
  @IsOptional()
  @ValidateIf((_object, value) => value !== null)
  @IsEmail()
  contactEmail?: string | null;

  @ApiPropertyOptional({ format: 'uri', nullable: true })
  @IsOptional()
  @ValidateIf((_object, value) => value !== null)
  @IsUrl({ require_protocol: true, protocols: ['http', 'https'] })
  logoUrl?: string | null;

  @ApiPropertyOptional({ format: 'uri', nullable: true })
  @IsOptional()
  @ValidateIf((_object, value) => value !== null)
  @IsUrl({ require_protocol: true, protocols: ['http', 'https'] })
  faviconUrl?: string | null;

  @ApiPropertyOptional()
  @IsOptional()
  @IsBoolean()
  maintenanceMode?: boolean;

  @ApiPropertyOptional({ maxLength: 1000, nullable: true })
  @IsOptional()
  @ValidateIf((_object, value) => value !== null)
  @IsString()
  @Length(1, 1000)
  maintenanceMessage?: string | null;
}

export class PutIntegrationDto {
  @ApiProperty({ maxLength: 160 })
  @IsString()
  @Length(1, 160)
  name!: string;

  @ApiProperty({
    minLength: 8,
    maxLength: 4096,
    writeOnly: true,
    description: 'Accepted for encryption and never returned by the API.',
  })
  @IsString()
  @Length(8, 4096)
  secret!: string;

  @ApiPropertyOptional({ enum: ['active', 'inactive'], default: 'active' })
  @IsOptional()
  @IsIn(['active', 'inactive'])
  status: 'active' | 'inactive' = 'active';

  @ApiPropertyOptional({ type: Object })
  @IsOptional()
  @IsObject()
  metadata: Record<string, string | boolean | null> = {};
}

export class IntegrationServiceParamDto {
  @ApiProperty()
  @Matches(/^[a-z][a-z0-9_-]{1,63}$/)
  service!: string;
}

export class AdministrationResponseDto {
  @ApiProperty({ type: Object }) data!: object;
}
