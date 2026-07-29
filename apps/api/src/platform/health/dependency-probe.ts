export const DEPENDENCY_PROBE = Symbol('DEPENDENCY_PROBE');

export type DependencyName = 'postgresql' | 'redis';
export type DependencyStatus = 'up' | 'down';

export interface DependencyProbeResult {
  postgresql: DependencyStatus;
  redis: DependencyStatus;
}

export interface DependencyProbe {
  check(): Promise<DependencyProbeResult>;
}
