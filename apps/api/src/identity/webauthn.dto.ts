import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { Type } from 'class-transformer';
import {
  IsArray,
  IsBoolean,
  IsDefined,
  IsIn,
  IsInt,
  IsOptional,
  IsString,
  Matches,
  MaxLength,
  ValidateNested,
} from 'class-validator';

const base64UrlPattern = /^[A-Za-z0-9_-]+$/;
const authenticatorTransports = [
  'ble',
  'cable',
  'hybrid',
  'internal',
  'nfc',
  'smart-card',
  'usb',
] as const;
const authenticatorAttachments = ['cross-platform', 'platform'] as const;

export class WebAuthnRpEntityDto {
  @ApiProperty({ type: String, example: 'MyMoneyMap' })
  name!: string;

  @ApiPropertyOptional({ type: String, example: 'wallet.example.test' })
  id?: string;
}

export class WebAuthnUserEntityDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded user handle' })
  id!: string;

  @ApiProperty({ type: String, example: 'ada@example.test' })
  name!: string;

  @ApiProperty({ type: String, example: 'Ada Example' })
  displayName!: string;
}

export class WebAuthnCredentialParameterDto {
  @ApiProperty({ enum: ['public-key'] })
  type!: 'public-key';

  @ApiProperty({ type: Number, example: -7 })
  alg!: number;
}

export class WebAuthnCredentialDescriptorDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded credential identifier' })
  id!: string;

  @ApiProperty({ enum: ['public-key'] })
  type!: 'public-key';

  @ApiPropertyOptional({ enum: authenticatorTransports, isArray: true })
  transports?: (typeof authenticatorTransports)[number][];
}

export class WebAuthnAuthenticatorSelectionDto {
  @ApiPropertyOptional({ enum: authenticatorAttachments })
  authenticatorAttachment?: (typeof authenticatorAttachments)[number];

  @ApiPropertyOptional({ type: Boolean })
  requireResidentKey?: boolean;

  @ApiPropertyOptional({ enum: ['discouraged', 'preferred', 'required'] })
  residentKey?: 'discouraged' | 'preferred' | 'required';

  @ApiPropertyOptional({ enum: ['discouraged', 'preferred', 'required'] })
  userVerification?: 'discouraged' | 'preferred' | 'required';
}

export class WebAuthnExtensionInputsDto {
  @ApiPropertyOptional({ type: String })
  appid?: string;

  @ApiPropertyOptional({ type: Boolean })
  credProps?: boolean;

  @ApiPropertyOptional({ type: Boolean })
  hmacCreateSecret?: boolean;

  @ApiPropertyOptional({ type: Boolean })
  minPinLength?: boolean;
}

export class PasskeyRegistrationOptionsResponseDto {
  @ApiProperty({ type: WebAuthnRpEntityDto })
  rp!: WebAuthnRpEntityDto;

  @ApiProperty({ type: WebAuthnUserEntityDto })
  user!: WebAuthnUserEntityDto;

  @ApiProperty({ type: String, description: 'Base64url-encoded single-use challenge' })
  challenge!: string;

  @ApiProperty({ type: WebAuthnCredentialParameterDto, isArray: true })
  pubKeyCredParams!: WebAuthnCredentialParameterDto[];

  @ApiPropertyOptional({ type: Number, example: 60000 })
  timeout?: number;

  @ApiPropertyOptional({ type: WebAuthnCredentialDescriptorDto, isArray: true })
  excludeCredentials?: WebAuthnCredentialDescriptorDto[];

  @ApiPropertyOptional({ type: WebAuthnAuthenticatorSelectionDto })
  authenticatorSelection?: WebAuthnAuthenticatorSelectionDto;

  @ApiPropertyOptional({ enum: ['hybrid', 'security-key', 'client-device'], isArray: true })
  hints?: ('hybrid' | 'security-key' | 'client-device')[];

  @ApiPropertyOptional({ enum: ['direct', 'enterprise', 'indirect', 'none'] })
  attestation?: 'direct' | 'enterprise' | 'indirect' | 'none';

  @ApiPropertyOptional({
    enum: ['fido-u2f', 'packed', 'android-safetynet', 'android-key', 'tpm', 'apple', 'none'],
    isArray: true,
  })
  attestationFormats?: (
    'fido-u2f' | 'packed' | 'android-safetynet' | 'android-key' | 'tpm' | 'apple' | 'none'
  )[];

  @ApiPropertyOptional({ type: WebAuthnExtensionInputsDto })
  extensions?: WebAuthnExtensionInputsDto;
}

export class PasskeyAuthenticationOptionsResponseDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded single-use challenge' })
  challenge!: string;

  @ApiPropertyOptional({ type: Number, example: 60000 })
  timeout?: number;

  @ApiPropertyOptional({ type: String, example: 'wallet.example.test' })
  rpId?: string;

  @ApiPropertyOptional({ type: WebAuthnCredentialDescriptorDto, isArray: true })
  allowCredentials?: WebAuthnCredentialDescriptorDto[];

  @ApiPropertyOptional({ enum: ['discouraged', 'preferred', 'required'] })
  userVerification?: 'discouraged' | 'preferred' | 'required';

  @ApiPropertyOptional({ enum: ['hybrid', 'security-key', 'client-device'], isArray: true })
  hints?: ('hybrid' | 'security-key' | 'client-device')[];

  @ApiPropertyOptional({ type: WebAuthnExtensionInputsDto })
  extensions?: WebAuthnExtensionInputsDto;
}

export class WebAuthnCredentialPropertiesOutputDto {
  @ApiPropertyOptional({ type: Boolean })
  @IsOptional()
  @IsBoolean()
  rk?: boolean;
}

export class WebAuthnExtensionOutputsDto {
  @ApiPropertyOptional({ type: Boolean })
  @IsOptional()
  @IsBoolean()
  appid?: boolean;

  @ApiPropertyOptional({ type: WebAuthnCredentialPropertiesOutputDto })
  @IsOptional()
  @ValidateNested()
  @Type(() => WebAuthnCredentialPropertiesOutputDto)
  credProps?: WebAuthnCredentialPropertiesOutputDto;

  @ApiPropertyOptional({ type: Boolean })
  @IsOptional()
  @IsBoolean()
  hmacCreateSecret?: boolean;
}

export class PasskeyRegistrationCredentialResponseDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded client data JSON' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  clientDataJSON!: string;

  @ApiProperty({ type: String, description: 'Base64url-encoded attestation object' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(524288)
  attestationObject!: string;

  @ApiPropertyOptional({ type: String, description: 'Base64url-encoded authenticator data' })
  @IsOptional()
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  authenticatorData?: string;

  @ApiPropertyOptional({ enum: authenticatorTransports, isArray: true })
  @IsOptional()
  @IsArray()
  @IsIn(authenticatorTransports, { each: true })
  transports?: (typeof authenticatorTransports)[number][];

  @ApiPropertyOptional({ type: Number })
  @IsOptional()
  @IsInt()
  publicKeyAlgorithm?: number;

  @ApiPropertyOptional({ type: String, description: 'Base64url-encoded credential public key' })
  @IsOptional()
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  publicKey?: string;
}

export class PasskeyRegistrationCredentialDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded credential identifier' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(4096)
  id!: string;

  @ApiProperty({ type: String, description: 'Base64url-encoded credential identifier' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(4096)
  rawId!: string;

  @ApiProperty({ enum: ['public-key'] })
  @IsIn(['public-key'])
  type!: 'public-key';

  @ApiPropertyOptional({ enum: authenticatorAttachments })
  @IsOptional()
  @IsIn(authenticatorAttachments)
  authenticatorAttachment?: (typeof authenticatorAttachments)[number];

  @ApiProperty({ type: PasskeyRegistrationCredentialResponseDto })
  @IsDefined()
  @ValidateNested()
  @Type(() => PasskeyRegistrationCredentialResponseDto)
  response!: PasskeyRegistrationCredentialResponseDto;

  @ApiProperty({ type: WebAuthnExtensionOutputsDto })
  @IsDefined()
  @ValidateNested()
  @Type(() => WebAuthnExtensionOutputsDto)
  clientExtensionResults!: WebAuthnExtensionOutputsDto;
}

export class PasskeyAuthenticationCredentialResponseDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded client data JSON' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  clientDataJSON!: string;

  @ApiProperty({ type: String, description: 'Base64url-encoded authenticator data' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  authenticatorData!: string;

  @ApiProperty({ type: String, description: 'Base64url-encoded assertion signature' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(131072)
  signature!: string;

  @ApiPropertyOptional({ type: String, description: 'Base64url-encoded user handle' })
  @IsOptional()
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(4096)
  userHandle?: string;
}

export class PasskeyAuthenticationCredentialDto {
  @ApiProperty({ type: String, description: 'Base64url-encoded credential identifier' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(4096)
  id!: string;

  @ApiProperty({ type: String, description: 'Base64url-encoded credential identifier' })
  @IsString()
  @Matches(base64UrlPattern)
  @MaxLength(4096)
  rawId!: string;

  @ApiProperty({ enum: ['public-key'] })
  @IsIn(['public-key'])
  type!: 'public-key';

  @ApiPropertyOptional({ enum: authenticatorAttachments })
  @IsOptional()
  @IsIn(authenticatorAttachments)
  authenticatorAttachment?: (typeof authenticatorAttachments)[number];

  @ApiProperty({ type: PasskeyAuthenticationCredentialResponseDto })
  @IsDefined()
  @ValidateNested()
  @Type(() => PasskeyAuthenticationCredentialResponseDto)
  response!: PasskeyAuthenticationCredentialResponseDto;

  @ApiProperty({ type: WebAuthnExtensionOutputsDto })
  @IsDefined()
  @ValidateNested()
  @Type(() => WebAuthnExtensionOutputsDto)
  clientExtensionResults!: WebAuthnExtensionOutputsDto;
}

export class PasskeyRegistrationResponseDto {
  @ApiProperty({ type: String, format: 'uuid', description: 'Server-owned passkey identifier' })
  id!: string;
}

export class PasskeySummaryResponseDto {
  @ApiProperty({ type: String, format: 'uuid', description: 'Server-owned passkey identifier' })
  id!: string;

  @ApiProperty({ type: String, example: 'Work laptop' })
  label!: string;

  @ApiProperty({ enum: ['singleDevice', 'multiDevice'] })
  deviceType!: 'singleDevice' | 'multiDevice';

  @ApiProperty({ type: Boolean })
  backedUp!: boolean;

  @ApiProperty({ enum: authenticatorTransports, isArray: true })
  transports!: (typeof authenticatorTransports)[number][];

  @ApiProperty({ type: String, format: 'date-time' })
  createdAt!: string;

  @ApiProperty({ type: String, format: 'date-time', nullable: true })
  lastUsedAt!: string | null;
}

export class PasskeyListResponseDto {
  @ApiProperty({ type: PasskeySummaryResponseDto, isArray: true })
  items!: PasskeySummaryResponseDto[];
}
