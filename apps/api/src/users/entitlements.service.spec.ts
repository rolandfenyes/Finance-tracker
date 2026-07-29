import { ApplicationError } from '../platform/http/application-error';
import { EntitlementsService } from './entitlements.service';

describe('EntitlementsService', () => {
  const service = new EntitlementsService();

  it('exposes the approved free quotas and denies cash-flow editing', () => {
    expect(service.forRole('free')).toEqual({
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
    });
    expect(() => service.assertWithinQuota('free', 'currencies', 0)).not.toThrow();
    expect(() => service.assertWithinQuota('free', 'currencies', 1)).toThrow(ApplicationError);
  });

  it('gives premium the approved unlimited personal-finance capabilities', () => {
    const premium = service.forRole('premium');
    expect(premium.personalFinanceAccess).toBe(true);
    expect(premium.administration).toBe(false);
    expect(premium.cashFlowRuleEditing).toBe(true);
    expect(Object.values(premium.resources)).toEqual(
      Array.from({ length: 5 }, () => ({ allowed: true, limit: null })),
    );
    expect(() => service.assertWithinQuota('premium', 'categories', 1_000_000)).not.toThrow();
  });

  it('allows administration but denies every personal-finance resource to admins', () => {
    const admin = service.forRole('admin');
    expect(admin.personalFinanceAccess).toBe(false);
    expect(admin.administration).toBe(true);
    expect(admin.cashFlowRuleEditing).toBe(false);
    expect(Object.values(admin.resources)).toEqual(
      Array.from({ length: 5 }, () => ({ allowed: false, limit: null })),
    );
    expect(() => service.assertWithinQuota('admin', 'activeGoals', 0)).toThrow(ApplicationError);
  });

  it('returns defensive copies of the centralized policy', () => {
    const first = service.forRole('free');
    first.resources.currencies.limit = 99;
    expect(service.forRole('free').resources.currencies.limit).toBe(1);
  });
});
