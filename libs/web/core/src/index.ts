export { provideApiCore } from './lib/api/api.providers';
export { ApiClientError, parseApiError } from './lib/api/api-error';
export type { ApiErrorKind, ApiFieldViolation } from './lib/api/api-error';
export {
  IDEMPOTENT_OPERATIONS,
  IDEMPOTENCY_REQUEST,
  idempotencyContext,
  isDeclaredIdempotentRequest,
} from './lib/api/idempotency';
export type { IdempotencyRequest, IdempotentOperation } from './lib/api/idempotency';
export { API_OBSERVABILITY_SINK, API_ROUTE_TEMPLATE } from './lib/api/observability';
export type { ApiObservation, ApiObservabilitySink } from './lib/api/observability';
export { CommandLifecycle, BrowserIdempotencyKeyFactory } from './lib/command/command-lifecycle';
export type { CommandState, IdempotencyKeyFactory } from './lib/command/command-lifecycle';
export { RouteStatusPageComponent } from './lib/routing/route-status-page';
export type { RouteStatus } from './lib/routing/route-status-page';
export {
  administrationGuard,
  authenticatedGuard,
  capabilityGuard,
  CONFIRM_PENDING_CHANGES,
  entryRouteGuard,
  onboardingCompleteGuard,
  onboardingRequiredGuard,
  pendingChangesGuard,
  personalFinanceGuard,
  signedOutGuard,
  verifiedEmailGuard,
} from './lib/session/guards';
export type { Capability, PendingChangesAware } from './lib/session/guards';
export { OnboardingPolicy } from './lib/session/onboarding-policy';
export { SessionState } from './lib/session/session-state';
export type { SessionStatus } from './lib/session/session-state';
export { SessionStore } from './lib/session/session.store';
export { ExactDecimalAdapter } from './lib/values/exact-decimal.adapter';
export type { ExactDecimal } from './lib/values/exact-decimal.adapter';
export {
  calendarDate,
  currencyCode,
  cursorValue,
  formatCalendarDate,
  formatInstant,
  formatMoney,
  formatPercent,
  opaqueCursor,
  recurrenceRule,
  resolveLocale,
  utcInstant,
} from './lib/values/presentation-values';
export type {
  CalendarDate,
  CurrencyCode,
  Freshness,
  OpaqueCursor,
  RecurrenceRule,
  UtcInstant,
} from './lib/values/presentation-values';
