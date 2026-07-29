import { CanActivate, ExecutionContext, Inject, Injectable } from '@nestjs/common';
import type { Request } from 'express';
import type { UserRole } from '../identity/identity.types';
import { ApplicationError } from '../platform/http/application-error';

export type PersonalFinanceResource =
  'currencies' | 'activeGoals' | 'activeLoans' | 'categories' | 'activeScheduledItems';

export interface ResourceEntitlement {
  allowed: boolean;
  limit: number | null;
}

export interface Entitlements {
  personalFinanceAccess: boolean;
  administration: boolean;
  cashFlowRuleEditing: boolean;
  resources: Record<PersonalFinanceResource, ResourceEntitlement>;
}

const deniedResource = (): ResourceEntitlement => ({ allowed: false, limit: null });
const unlimitedResource = (): ResourceEntitlement => ({ allowed: true, limit: null });

const ENTITLEMENTS: Readonly<Record<UserRole, Readonly<Entitlements>>> = {
  free: {
    personalFinanceAccess: true,
    administration: false,
    cashFlowRuleEditing: false,
    resources: {
      currencies: { allowed: true, limit: 1 },
      activeGoals: { allowed: true, limit: 2 },
      activeLoans: { allowed: true, limit: 2 },
      categories: { allowed: true, limit: 10 },
      activeScheduledItems: { allowed: true, limit: 2 },
    },
  },
  premium: {
    personalFinanceAccess: true,
    administration: false,
    cashFlowRuleEditing: true,
    resources: {
      currencies: unlimitedResource(),
      activeGoals: unlimitedResource(),
      activeLoans: unlimitedResource(),
      categories: unlimitedResource(),
      activeScheduledItems: unlimitedResource(),
    },
  },
  admin: {
    personalFinanceAccess: false,
    administration: true,
    cashFlowRuleEditing: false,
    resources: {
      currencies: deniedResource(),
      activeGoals: deniedResource(),
      activeLoans: deniedResource(),
      categories: deniedResource(),
      activeScheduledItems: deniedResource(),
    },
  },
};

@Injectable()
export class EntitlementsService {
  forRole(role: UserRole): Entitlements {
    return structuredClone(ENTITLEMENTS[role]);
  }

  assertWithinQuota(role: UserRole, resource: PersonalFinanceResource, currentCount: number): void {
    const entitlement = ENTITLEMENTS[role].resources[resource];
    if (!ENTITLEMENTS[role].personalFinanceAccess || !entitlement.allowed) {
      throw new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
    }
    if (entitlement.limit !== null && currentCount >= entitlement.limit) {
      throw new ApplicationError(403, 'FORBIDDEN', 'The current plan limit has been reached');
    }
  }
}

@Injectable()
export class PersonalFinanceAccessGuard implements CanActivate {
  constructor(@Inject(EntitlementsService) private readonly entitlements: EntitlementsService) {}

  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<Request>();
    const principal = request.session.principal;
    if (!principal || !this.entitlements.forRole(principal.role).personalFinanceAccess) {
      throw new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
    }
    return true;
  }
}
