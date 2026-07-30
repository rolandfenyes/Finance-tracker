import {
  CanActivate,
  ExecutionContext,
  Inject,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { timingSafeEqual } from 'node:crypto';
import type { Request } from 'express';

@Injectable()
export class OperationsMetricsGuard implements CanActivate {
  constructor(@Inject(ConfigService) private readonly config: ConfigService) {}

  canActivate(context: ExecutionContext): boolean {
    if (!this.config.getOrThrow<boolean>('OPERATIONS_METRICS_ENABLED')) {
      throw new NotFoundException();
    }
    const expected = this.config.getOrThrow<string>('OPERATIONS_METRICS_TOKEN');
    const supplied =
      context.switchToHttp().getRequest<Request>().header('x-operations-token') ?? '';
    const expectedBytes = Buffer.from(expected);
    const suppliedBytes = Buffer.from(supplied);
    if (
      expectedBytes.length !== suppliedBytes.length ||
      !timingSafeEqual(expectedBytes, suppliedBytes)
    ) {
      throw new NotFoundException();
    }
    return true;
  }
}
