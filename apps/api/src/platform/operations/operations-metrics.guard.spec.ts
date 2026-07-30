import { ConfigService } from '@nestjs/config';
import { NotFoundException, type ExecutionContext } from '@nestjs/common';
import { OperationsMetricsGuard } from './operations-metrics.guard';

function contextWithToken(token?: string): ExecutionContext {
  return {
    switchToHttp: () => ({
      getRequest: () => ({
        header: (name: string) => (name === 'x-operations-token' ? token : undefined),
      }),
    }),
  } as ExecutionContext;
}

describe('OperationsMetricsGuard', () => {
  it('hides the internal endpoint when metrics are disabled', () => {
    const guard = new OperationsMetricsGuard(
      new ConfigService({ OPERATIONS_METRICS_ENABLED: false }),
    );
    expect(() => guard.canActivate(contextWithToken())).toThrow(NotFoundException);
  });

  it('uses an independent constant-time token gate', () => {
    const token = 'synthetic-operations-token-at-least-32-characters';
    const guard = new OperationsMetricsGuard(
      new ConfigService({
        OPERATIONS_METRICS_ENABLED: true,
        OPERATIONS_METRICS_TOKEN: token,
      }),
    );
    expect(() => guard.canActivate(contextWithToken('wrong'))).toThrow(NotFoundException);
    expect(guard.canActivate(contextWithToken(token))).toBe(true);
  });
});
