import { HttpContextToken } from '@angular/common/http';
import { InjectionToken } from '@angular/core';

export const API_ROUTE_TEMPLATE = new HttpContextToken<string>(() => 'unmapped-api-route');

export interface ApiObservation {
  readonly durationMs: number;
  readonly method: string;
  readonly requestId: string | null;
  readonly routeTemplate: string;
  readonly status: number;
}

export interface ApiObservabilitySink {
  record(observation: ApiObservation): void;
}

export const API_OBSERVABILITY_SINK = new InjectionToken<ApiObservabilitySink>(
  'API_OBSERVABILITY_SINK',
  {
    factory: (): ApiObservabilitySink => ({
      record: (): void => undefined,
    }),
  },
);
