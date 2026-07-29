import { ApiProperty } from '@nestjs/swagger';

export class LivenessResponse {
  @ApiProperty({ example: 'ok', type: String })
  status!: 'ok';
}

export class DependencyStatusResponse {
  @ApiProperty({ example: 'up', enum: ['up'], type: String })
  postgresql!: 'up';

  @ApiProperty({ example: 'up', enum: ['up'], type: String })
  redis!: 'up';
}

export class ReadinessResponse {
  @ApiProperty({ example: 'ready', type: String })
  status!: 'ready';

  @ApiProperty({ type: () => DependencyStatusResponse })
  dependencies!: DependencyStatusResponse;
}
