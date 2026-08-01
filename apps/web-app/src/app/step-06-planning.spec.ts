/* eslint-disable @nx/enforce-module-boundaries -- Test-only imports execute lazy feature specs. */
import { PLANNING_ROUTES, buildSupportedRRule } from '@mymoneymap/feature-planning';

await import('../../../../libs/web/feature-planning/src/lib/planning.facade.spec');
await import('../../../../libs/web/feature-planning/src/lib/rrule.spec');

describe('Step 06 planning integration', () => {
  it('owns only the approved planning route surface', () => {
    const paths = PLANNING_ROUTES[0]?.children?.map((route) => route.path);
    expect(paths).toEqual([
      '',
      'budget',
      'categories',
      'income',
      'schedules',
      'schedules/new',
      'schedules/:id/edit',
    ]);
  });

  it('retains exact strings while building a request rule rather than forecast dates', () => {
    expect(buildSupportedRRule({ frequency: 'MONTHLY', interval: '1', byMonthDay: '31' })).toBe(
      'FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=31',
    );
  });
});
