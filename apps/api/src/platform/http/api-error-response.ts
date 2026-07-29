import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';

export class ApiViolationResponse {
  @ApiProperty({ example: 'fieldName', type: String })
  field!: string;

  @ApiProperty({ example: 'must be a valid value', type: String })
  message!: string;
}

export class ApiErrorBodyResponse {
  @ApiProperty({ example: 'VALIDATION_FAILED', type: String })
  code!: string;

  @ApiProperty({ example: 'Request validation failed', type: String })
  message!: string;

  @ApiProperty({
    example: '4d69ed37-7f7f-4318-8b9a-62b3a2469ed5',
    format: 'uuid',
    type: String,
  })
  requestId!: string;

  @ApiPropertyOptional({ type: () => [ApiViolationResponse] })
  violations?: ApiViolationResponse[];
}

export class ApiErrorResponse {
  @ApiProperty({ type: () => ApiErrorBodyResponse })
  error!: ApiErrorBodyResponse;
}
