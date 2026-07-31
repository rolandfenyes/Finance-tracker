import { HttpContext, HttpContextToken } from '@angular/common/http';

export const IDEMPOTENT_OPERATIONS = {
  emergencyContribution: 'EmergencyReserveController_contribution',
  emergencyReverse: 'EmergencyReserveController_reverse',
  emergencyWithdrawal: 'EmergencyReserveController_withdrawal',
  goalContribution: 'GoalsController_contribute',
  goalCorrection: 'GoalsController_correct',
  goalReversal: 'GoalsController_reverse',
  investmentMovement: 'InvestmentsController_movement',
  investmentMovementReversal: 'InvestmentsController_reverseMovement',
  journalCorrection: 'LedgerController_correct',
  journalCreate: 'LedgerController_create',
  journalReversal: 'LedgerController_reverse',
  loanCorrection: 'LoansController_correct',
  loanPayment: 'LoansController_payment',
  loanReversal: 'LoansController_reverse',
  privacyDeletion: 'PrivacyController_createDeletion',
  privacyExport: 'PrivacyController_createExport',
} as const;

export type IdempotentOperation =
  (typeof IDEMPOTENT_OPERATIONS)[keyof typeof IDEMPOTENT_OPERATIONS];

export interface IdempotencyRequest {
  readonly key: string;
  readonly operation: IdempotentOperation;
}

export const IDEMPOTENCY_REQUEST = new HttpContextToken<IdempotencyRequest | null>(() => null);

const DECLARED_ENDPOINTS: Readonly<
  Record<IdempotentOperation, { readonly method: 'POST'; readonly path: RegExp }>
> = {
  EmergencyReserveController_contribution: {
    method: 'POST',
    path: /^\/api\/v1\/emergency-reserve\/contributions$/,
  },
  EmergencyReserveController_reverse: {
    method: 'POST',
    path: /^\/api\/v1\/emergency-reserve\/movements\/[^/]+\/reversals$/,
  },
  EmergencyReserveController_withdrawal: {
    method: 'POST',
    path: /^\/api\/v1\/emergency-reserve\/withdrawals$/,
  },
  GoalsController_contribute: { method: 'POST', path: /^\/api\/v1\/goals\/[^/]+\/contributions$/ },
  GoalsController_correct: {
    method: 'POST',
    path: /^\/api\/v1\/goals\/[^/]+\/contributions\/[^/]+\/corrections$/,
  },
  GoalsController_reverse: {
    method: 'POST',
    path: /^\/api\/v1\/goals\/[^/]+\/contributions\/[^/]+\/reversals$/,
  },
  InvestmentsController_movement: {
    method: 'POST',
    path: /^\/api\/v1\/investments\/[^/]+\/movements$/,
  },
  InvestmentsController_reverseMovement: {
    method: 'POST',
    path: /^\/api\/v1\/investments\/[^/]+\/movements\/[^/]+\/reversals$/,
  },
  LedgerController_correct: {
    method: 'POST',
    path: /^\/api\/v1\/journal\/entries\/[^/]+\/corrections$/,
  },
  LedgerController_create: { method: 'POST', path: /^\/api\/v1\/journal\/entries$/ },
  LedgerController_reverse: {
    method: 'POST',
    path: /^\/api\/v1\/journal\/entries\/[^/]+\/reversals$/,
  },
  LoansController_correct: {
    method: 'POST',
    path: /^\/api\/v1\/loans\/[^/]+\/payments\/[^/]+\/corrections$/,
  },
  LoansController_payment: { method: 'POST', path: /^\/api\/v1\/loans\/[^/]+\/payments$/ },
  LoansController_reverse: {
    method: 'POST',
    path: /^\/api\/v1\/loans\/[^/]+\/payments\/[^/]+\/reversals$/,
  },
  PrivacyController_createDeletion: {
    method: 'POST',
    path: /^\/api\/v1\/privacy\/deletion-requests$/,
  },
  PrivacyController_createExport: { method: 'POST', path: /^\/api\/v1\/privacy\/exports$/ },
};

export function isDeclaredIdempotentRequest(
  request: IdempotencyRequest,
  method: string,
  url: string,
): boolean {
  const endpoint = DECLARED_ENDPOINTS[request.operation];
  const path = url.split('?', 1)[0] ?? url;
  return endpoint.method === method && endpoint.path.test(path);
}

export function idempotencyContext(
  operation: IdempotentOperation,
  callerSuppliedKey: string,
  context = new HttpContext(),
): HttpContext {
  const key = callerSuppliedKey.trim();
  if (key.length === 0 || key.length > 200) {
    throw new Error('Idempotency key must contain between 1 and 200 characters');
  }
  return context.set(IDEMPOTENCY_REQUEST, { key, operation });
}
