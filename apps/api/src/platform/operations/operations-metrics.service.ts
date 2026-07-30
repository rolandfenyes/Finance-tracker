import { Injectable } from '@nestjs/common';
import { collectDefaultMetrics, Counter, Gauge, Histogram, Registry } from 'prom-client';

@Injectable()
export class OperationsMetricsService {
  private readonly registry = new Registry();
  private readonly requests = new Counter({
    name: 'mymoneymap_http_requests_total',
    help: 'Completed API requests grouped by bounded route and status class.',
    labelNames: ['method', 'route', 'status_class'],
    registers: [this.registry],
  });
  private readonly requestDuration = new Histogram({
    name: 'mymoneymap_http_request_duration_seconds',
    help: 'API request duration grouped by bounded route and method.',
    labelNames: ['method', 'route'],
    buckets: [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 15],
    registers: [this.registry],
  });
  private readonly rateLimitRejections = new Counter({
    name: 'mymoneymap_http_rate_limit_rejections_total',
    help: 'Requests rejected by the shared Redis-backed API rate limit.',
    labelNames: ['scope'],
    registers: [this.registry],
  });
  private readonly queueJobs = new Gauge({
    name: 'mymoneymap_queue_jobs',
    help: 'BullMQ job counts by queue and state at the latest scrape.',
    labelNames: ['queue', 'state'],
    registers: [this.registry],
  });
  private readonly queueOldestPending = new Gauge({
    name: 'mymoneymap_queue_oldest_pending_seconds',
    help: 'Age of the oldest waiting or delayed BullMQ job.',
    labelNames: ['queue'],
    registers: [this.registry],
  });

  constructor() {
    collectDefaultMetrics({
      register: this.registry,
      prefix: 'mymoneymap_process_',
    });
  }

  observeRequest(method: string, route: string, status: number, durationSeconds: number): void {
    const labels = { method, route };
    this.requests.inc({ ...labels, status_class: `${Math.floor(status / 100)}xx` });
    this.requestDuration.observe(labels, durationSeconds);
  }

  observeRateLimit(scope: 'admin' | 'api'): void {
    this.rateLimitRejections.inc({ scope });
  }

  observeQueue(input: {
    queue: string;
    counts: Readonly<Record<string, number>>;
    oldestPendingSeconds: number;
  }): void {
    for (const [state, count] of Object.entries(input.counts)) {
      this.queueJobs.set({ queue: input.queue, state }, count);
    }
    this.queueOldestPending.set({ queue: input.queue }, input.oldestPendingSeconds);
  }

  contentType(): string {
    return this.registry.contentType;
  }

  metrics(): Promise<string> {
    return this.registry.metrics();
  }
}
