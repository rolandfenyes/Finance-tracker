import * as Sentry from '@sentry/nestjs';

export interface ScrubbableSentryEvent {
  request?: {
    cookies?: unknown;
    data?: unknown;
    headers?: unknown;
    query_string?: unknown;
    url?: unknown;
  };
  user?: unknown;
  extra?: unknown;
  contexts?: unknown;
  breadcrumbs?: {
    category?: string;
    level?: string;
    message?: string;
    timestamp?: number;
    type?: string;
  }[];
}

export function scrubSentryEvent<T extends ScrubbableSentryEvent>(event: T): T {
  if (event.request) {
    event.request.cookies = undefined;
    event.request.data = undefined;
    event.request.headers = undefined;
    event.request.query_string = undefined;
    event.request.url = undefined;
  }
  event.user = undefined;
  event.extra = undefined;
  event.contexts = undefined;
  event.breadcrumbs = event.breadcrumbs?.map((breadcrumb) => ({
    category: breadcrumb.category,
    level: breadcrumb.level,
    timestamp: breadcrumb.timestamp,
    type: breadcrumb.type,
  }));
  return event;
}

if (process.env.SENTRY_ENABLED === 'true') {
  Sentry.init({
    dsn: process.env.SENTRY_DSN,
    environment: process.env.SENTRY_ENVIRONMENT ?? process.env.NODE_ENV,
    sendDefaultPii: false,
    tracesSampleRate: Number(process.env.SENTRY_TRACES_SAMPLE_RATE ?? '0'),
    beforeSend: scrubSentryEvent,
    beforeSendTransaction: scrubSentryEvent,
  });
}
