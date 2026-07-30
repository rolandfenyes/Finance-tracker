import { CanActivate, ExecutionContext, Injectable } from '@nestjs/common';
import type { Request } from 'express';
import { ApplicationError } from '../platform/http/application-error';

@Injectable()
export class AdminGuard implements CanActivate {
  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<Request>();
    if (request.session.principal?.role !== 'admin') {
      throw new ApplicationError(403, 'FORBIDDEN', 'Administrator access is required');
    }
    return true;
  }
}
