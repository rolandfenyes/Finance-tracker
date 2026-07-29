import { CanActivate, ExecutionContext, Inject, Injectable } from '@nestjs/common';
import type { Request } from 'express';
import { ApplicationError } from '../platform/http/application-error';
import { IdentityRepository } from './identity.repository';

@Injectable()
export class AuthenticationGuard implements CanActivate {
  constructor(@Inject(IdentityRepository) private readonly repository: IdentityRepository) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest<Request>();
    const principal = request.session.principal;
    if (!principal || !request.session.absoluteExpiresAt) throw unauthorized();
    if (Date.parse(request.session.absoluteExpiresAt) <= Date.now()) {
      await destroy(request);
      throw unauthorized();
    }
    const user = await this.repository.findUserById(principal.userId);
    if (!user || user.status !== 'active') {
      await destroy(request);
      throw unauthorized();
    }
    request.session.principal = {
      userId: user.id,
      role: user.role,
      emailVerified: user.emailVerifiedAt !== null,
    };
    return true;
  }
}

@Injectable()
export class VerifiedEmailGuard implements CanActivate {
  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<Request>();
    if (!request.session.principal?.emailVerified) {
      throw new ApplicationError(403, 'FORBIDDEN', 'Email verification is required');
    }
    return true;
  }
}

function unauthorized(): ApplicationError {
  return new ApplicationError(401, 'UNAUTHORIZED', 'Authentication is required');
}

function destroy(request: Request): Promise<void> {
  return new Promise((resolve) => request.session.destroy(() => resolve()));
}
