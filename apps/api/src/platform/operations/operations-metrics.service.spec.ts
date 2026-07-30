import { OperationsMetricsService } from './operations-metrics.service';

describe('OperationsMetricsService', () => {
  it('exports bounded HTTP and queue metrics without payload or identity labels', async () => {
    const metrics = new OperationsMetricsService();
    metrics.observeRequest('GET', '/api/v1/reports', 200, 0.02);
    metrics.observeRateLimit('api');
    metrics.observeQueue({
      queue: 'mymoneymap-recurrence',
      counts: { wait: 2, failed: 1 },
      oldestPendingSeconds: 31,
    });

    const output = await metrics.metrics();
    expect(output).toContain('mymoneymap_http_requests_total');
    expect(output).toContain('mymoneymap_queue_jobs');
    expect(output).toContain('queue="mymoneymap-recurrence"');
    expect(output).not.toContain('email');
    expect(output).not.toContain('amount');
  });
});
