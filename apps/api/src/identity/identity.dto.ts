import { ApiProperty } from '@nestjs/swagger';
import {
  IsBoolean,
  IsDateString,
  IsEmail,
  IsNotEmpty,
  IsObject,
  IsOptional,
  IsString,
  Length,
  Matches,
  MaxLength,
  MinLength,
} from 'class-validator';

export class RegistrationDto {
  @ApiProperty({ type: String, example: 'ada@example.test' })
  @IsEmail()
  @MaxLength(320)
  email!: string;

  @ApiProperty({ type: String, minLength: 8, example: 'synthetic-long-password' })
  @IsString()
  @MinLength(8)
  @MaxLength(200)
  password!: string;

  @ApiProperty({ type: String, example: 'Ada Example' })
  @IsString()
  @Length(1, 200)
  fullName!: string;

  @ApiProperty({ type: String, format: 'date', example: '1990-01-15' })
  @IsDateString({ strict: true, strictSeparator: true })
  @Matches(/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/)
  dateOfBirth!: string;
}

export class EmailVerificationDto {
  @ApiProperty({
    type: String,
    description: 'Single-use token received through the configured email channel',
  })
  @IsString()
  @IsNotEmpty()
  @MaxLength(256)
  token!: string;
}

export class EmailVerificationRequestDto {
  @ApiProperty({ type: String, example: 'ada@example.test' })
  @IsEmail()
  @MaxLength(320)
  email!: string;
}

export class PasswordSessionDto {
  @ApiProperty({ type: String, example: 'ada@example.test' })
  @IsEmail()
  @MaxLength(320)
  email!: string;

  @ApiProperty({ type: String, format: 'password' })
  @IsString()
  @MaxLength(200)
  password!: string;

  @ApiProperty({ type: Boolean, required: false, default: false })
  @IsOptional()
  @IsBoolean()
  remember?: boolean;
}

export class PasswordChangeDto {
  @ApiProperty({ type: String, format: 'password' })
  @IsString()
  @MaxLength(200)
  currentPassword!: string;

  @ApiProperty({ type: String, format: 'password', minLength: 8 })
  @IsString()
  @MinLength(8)
  @MaxLength(200)
  newPassword!: string;
}

export class PasskeyLabelDto {
  @ApiProperty({ type: String, example: 'Work laptop' })
  @IsString()
  @Length(1, 100)
  label!: string;

  @ApiProperty({ type: Object })
  @IsObject()
  credential!: Record<string, unknown>;
}

export class PasskeyAuthenticationOptionsDto {
  @ApiProperty({ type: String, required: false, example: 'ada@example.test' })
  @IsOptional()
  @IsEmail()
  @MaxLength(320)
  email?: string;
}

export class PasskeyAuthenticationDto {
  @ApiProperty({ type: Object })
  @IsObject()
  credential!: Record<string, unknown>;

  @ApiProperty({ type: Boolean, required: false, default: false })
  @IsOptional()
  @IsBoolean()
  remember?: boolean;
}
