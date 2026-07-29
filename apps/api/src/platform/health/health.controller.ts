import { Controller, Get, Inject, ServiceUnavailableException } from '@nestjs/common';
import {
  ApiInternalServerErrorResponse,
  ApiOkResponse,
  ApiOperation,
  ApiServiceUnavailableResponse,
  ApiTags,
} from '@nestjs/swagger';
import { ApiErrorResponse } from '../http/api-error-response';
import { DEPENDENCY_PROBE, DependencyProbe } from './dependency-probe';
import { LivenessResponse, ReadinessResponse } from './health.dto';

@ApiTags('health')
@ApiInternalServerErrorResponse({ type: ApiErrorResponse })
@Controller('health')
export class HealthController {
  constructor(
    @Inject(DEPENDENCY_PROBE)
    private readonly dependencyProbe: DependencyProbe,
  ) {}

  @Get('live')
  @ApiOperation({ summary: 'Report that the API process is alive' })
  @ApiOkResponse({ type: LivenessResponse })
  live(): LivenessResponse {
    return { status: 'ok' };
  }

  @Get('ready')
  @ApiOperation({ summary: 'Verify required PostgreSQL and Redis dependencies' })
  @ApiOkResponse({ type: ReadinessResponse })
  @ApiServiceUnavailableResponse({ type: ApiErrorResponse })
  async ready(): Promise<ReadinessResponse> {
    const dependencies = await this.dependencyProbe.check();
    if (dependencies.postgresql !== 'up' || dependencies.redis !== 'up') {
      throw new ServiceUnavailableException({
        code: 'SERVICE_NOT_READY',
        message: 'Required service dependencies are unavailable',
      });
    }

    return {
      status: 'ready',
      dependencies: {
        postgresql: 'up',
        redis: 'up',
      },
    };
  }
}
