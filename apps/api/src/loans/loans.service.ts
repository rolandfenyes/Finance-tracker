import { Inject, Injectable } from '@nestjs/common';
import { createHash, randomUUID } from 'node:crypto';
import type { Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { FxConversionService } from '../currency/fx-conversion.service';
import { LedgerRepository } from '../ledger/ledger.repository';
import type { JournalEntry } from '../ledger/ledger.types';
import type { DatabaseSchema } from '../platform/database/database.types';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { RoundingPolicy } from '../platform/decimal/rounding-policy';
import type { JsonValue } from '../platform/events/outbox.port';
import { ApplicationError } from '../platform/http/application-error';
import {
  IdempotencyKey,
  IdempotencyOperation,
  RequestFingerprint,
} from '../platform/idempotency/idempotency';
import { IdempotencyService } from '../platform/idempotency/idempotency.service';
import { EntityId } from '../platform/identifiers/entity-id';
import { CalendarDate } from '../platform/time/calendar-date';
import { CLOCK, type Clock } from '../platform/time/clock';
import { InvalidRecurrenceRuleError, parseRecurrenceRule } from '../recurrence/recurrence-rule';
import type { MaterializedRule, RecurringRuleWrite } from '../recurrence/recurrence.repository';
import { EntitlementsService } from '../users/entitlements.service';
import type {
  CreateLoanDto,
  CreateLoanPaymentDto,
  CreateLoanRecurringRuleDto,
  ReverseLoanPaymentDto,
  UpdateLoanDto,
} from './loans.dto';
import { LoansRepository, type LoanWrite } from './loans.repository';
import type { Loan, LockedLoan } from './loans.types';

const INTERNAL = RoundingPolicy.create(36, 'HALF_EVEN');

export interface IdempotentLoan {
  value: Loan;
  replayed: boolean;
}

@Injectable()
export class LoansService {
  constructor(
    @Inject(LoansRepository) private readonly repository: LoansRepository,
    @Inject(LedgerRepository) private readonly ledger: LedgerRepository,
    @Inject(FxConversionService) private readonly fx: FxConversionService,
    @Inject(IdempotencyService) private readonly idempotency: IdempotencyService,
    @Inject(EntitlementsService) private readonly entitlements: EntitlementsService,
    @Inject(CLOCK) private readonly clock: Clock,
  ) {}

  async loans(userId: string): Promise<{ items: Loan[] }> {
    return { items: await this.repository.list(userId) };
  }

  async create(userId: string, role: UserRole, dto: CreateLoanDto): Promise<{ items: Loan[] }> {
    const values = normalizeLoan(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        if (!user || user.role !== role || role === 'admin') throw forbidden();
        this.entitlements.assertWithinQuota(
          role,
          'activeLoans',
          await this.repository.countActive(userId, transaction),
        );
        await this.assertCurrency(transaction, userId, values.currency, 'Loan');
        const id = randomUUID();
        const now = this.clock.now().toDate();
        const accountId = await this.ledger.createModuleAccount(
          transaction,
          userId,
          'loan_liability',
          id,
          now,
        );
        await this.repository.create(transaction, userId, id, accountId, values, now);
      });
      return this.loans(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async update(userId: string, loanId: string, dto: UpdateLoanDto): Promise<{ items: Loan[] }> {
    assertNonEmpty(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if (loan.archivedAt !== null) throw conflict('Archived loans are locked');
        const principal = exact(dto.principal ?? loan.principal, 'Principal');
        if (!principal.isPositive()) throw semantic('Principal must be greater than zero');
        const paid = ExactDecimal.create(loan.principal).subtract(
          ExactDecimal.create(loan.outstandingPrincipal),
        );
        if (principal.compare(paid) < 0) {
          throw semantic('Principal cannot be lower than already posted principal repayments');
        }
        const rate = exact(dto.nominalAnnualRate ?? loan.nominalAnnualRate, 'Nominal annual rate');
        if (rate.isNegative()) throw semantic('Nominal annual rate cannot be negative');
        const startsOn = dto.startsOn ? CalendarDate.create(dto.startsOn).toString() : undefined;
        const endsOn =
          dto.endsOn === undefined
            ? undefined
            : dto.endsOn === null
              ? null
              : CalendarDate.create(dto.endsOn).toString();
        if (
          startsOn !== undefined &&
          endsOn !== undefined &&
          endsOn !== null &&
          endsOn < startsOn
        ) {
          throw semantic('Loan end date cannot precede its start date');
        }
        await this.repository.update(
          transaction,
          userId,
          loanId,
          {
            ...(dto.title === undefined ? {} : { title: dto.title.trim() }),
            ...(dto.principal === undefined ? {} : { principal: principal.toString() }),
            ...(dto.nominalAnnualRate === undefined ? {} : { nominalAnnualRate: rate.toString() }),
            ...(dto.termMonths === undefined ? {} : { termMonths: dto.termMonths }),
            ...(startsOn === undefined ? {} : { startsOn }),
            ...(endsOn === undefined ? {} : { endsOn }),
            ...(dto.paymentDay === undefined ? {} : { paymentDay: dto.paymentDay }),
            ...(dto.extraPaymentScenario === undefined
              ? {}
              : {
                  extraPaymentScenario: nonNegative(
                    dto.extraPaymentScenario,
                    'Extra-payment scenario',
                  ),
                }),
            ...(dto.insuranceMonthly === undefined
              ? {}
              : { insuranceMonthly: nonNegative(dto.insuranceMonthly, 'Monthly insurance') }),
          },
          this.clock.now().toDate(),
        );
      });
      return this.loans(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async archive(userId: string, loanId: string): Promise<{ items: Loan[] }> {
    try {
      await this.repository.transaction(async (transaction) => {
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if (!ExactDecimal.create(loan.outstandingPrincipal).isZero()) {
          throw conflict('Only a fully repaid loan can be archived');
        }
        const now = this.clock.now().toDate();
        await this.repository.setLifecycle(
          transaction,
          userId,
          loanId,
          loan.completedAt ?? now,
          loan.archivedAt ?? now,
          now,
        );
      });
      return this.loans(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async delete(userId: string, loanId: string): Promise<void> {
    try {
      await this.repository.transaction(async (transaction) => {
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if ((await this.repository.references(userId, loanId, transaction)) > 0) {
          throw conflict('A loan with payment history must be archived instead of deleted');
        }
        await this.repository.deleteEmpty(transaction, userId, loanId, loan.liabilityAccountId);
      });
    } catch (error) {
      throw translate(error);
    }
  }

  payment(
    userId: string,
    loanId: string,
    rawKey: string | undefined,
    dto: CreateLoanPaymentDto,
  ): Promise<IdempotentLoan> {
    return this.postManual(userId, loanId, rawKey, dto, null);
  }

  async correctPayment(
    userId: string,
    loanId: string,
    paymentId: string,
    rawKey: string | undefined,
    dto: CreateLoanPaymentDto,
  ): Promise<IdempotentLoan> {
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `loans.payments.correct:${paymentId}`, key, dto),
      async (transaction) => {
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if (loan.archivedAt !== null) throw conflict('Archived loans are locked');
        const original = await this.repository.payment(transaction, userId, loanId, paymentId);
        if (!original) throw paymentNotFound();
        if (original.reversedByJournalEntryId !== null) {
          throw conflict('The loan payment was already reversed or corrected');
        }
        await this.reverseJournal(
          transaction,
          userId,
          original.id,
          original.journalEntryId,
          dto.paidOn,
          derivedHash(key.toHash(), 'reversal'),
          this.clock.now().toDate(),
        );
        const value = await this.postPayment(
          transaction,
          userId,
          loanId,
          dto,
          randomUUID(),
          derivedHash(key.toHash(), 'replacement'),
          'manual',
          null,
          paymentId,
          original.journalEntryId,
        );
        return loanJson(value);
      },
    );
    return { value: result.value.loan as unknown as Loan, replayed: result.replayed };
  }

  async reversePayment(
    userId: string,
    loanId: string,
    paymentId: string,
    rawKey: string | undefined,
    dto: ReverseLoanPaymentDto,
  ): Promise<IdempotentLoan> {
    CalendarDate.create(dto.postedOn);
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, `loans.payments.reverse:${paymentId}`, key, dto),
      async (transaction) => {
        await this.requiredLoan(transaction, userId, loanId);
        const payment = await this.repository.payment(transaction, userId, loanId, paymentId);
        if (!payment) throw paymentNotFound();
        if (payment.reversedByJournalEntryId !== null) {
          throw conflict('The loan payment was already reversed or corrected');
        }
        await this.reverseJournal(
          transaction,
          userId,
          payment.id,
          payment.journalEntryId,
          dto.postedOn,
          key.toHash(),
          this.clock.now().toDate(),
          dto.note,
        );
        const now = this.clock.now().toDate();
        await this.repository.setLifecycle(transaction, userId, loanId, null, null, now);
        return loanJson(await this.requiredResponseLoan(transaction, userId, loanId));
      },
    );
    return { value: result.value.loan as unknown as Loan, replayed: result.replayed };
  }

  async createRule(
    userId: string,
    role: UserRole,
    loanId: string,
    dto: CreateLoanRecurringRuleDto,
  ): Promise<{ items: Loan[] }> {
    const values = schedule(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const user = await this.repository.lockUser(transaction, userId);
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if (!user || user.role !== role || role === 'admin') throw forbidden();
        if (loan.completedAt !== null || loan.archivedAt !== null) {
          throw conflict('Completed or archived loans cannot be scheduled');
        }
        this.entitlements.assertWithinQuota(
          role,
          'activeScheduledItems',
          await this.repository.countRules(userId, transaction),
        );
        await this.assertCurrency(transaction, userId, dto.currency, 'Schedule');
        await this.repository.createRule(
          transaction,
          userId,
          { ...values, loanId },
          this.clock.now().toDate(),
        );
      });
      return this.loans(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async updateRule(
    userId: string,
    loanId: string,
    dto: CreateLoanRecurringRuleDto,
  ): Promise<{ items: Loan[] }> {
    const values = schedule(dto);
    try {
      await this.repository.transaction(async (transaction) => {
        const loan = await this.requiredLoan(transaction, userId, loanId);
        if (loan.completedAt !== null || loan.archivedAt !== null) {
          throw conflict('Completed or archived loans cannot be scheduled');
        }
        await this.assertCurrency(transaction, userId, dto.currency, 'Schedule');
        if (
          !(await this.repository.updateRule(
            transaction,
            userId,
            loanId,
            values,
            this.clock.now().toDate(),
          ))
        ) {
          throw ruleNotFound();
        }
      });
      return this.loans(userId);
    } catch (error) {
      throw translate(error);
    }
  }

  async deleteRule(userId: string, loanId: string): Promise<void> {
    if (!(await this.repository.deleteRule(userId, loanId))) throw ruleNotFound();
  }

  async postScheduledPayment(
    transaction: Transaction<DatabaseSchema>,
    rule: MaterializedRule,
    occurrenceId: string,
    dueOn: string,
  ): Promise<void> {
    if (rule.loanId === null) return;
    const loan = await this.requiredLoan(transaction, rule.userId, rule.loanId);
    if (loan.completedAt !== null || loan.archivedAt !== null) return;
    const policy = await this.repository.currencyPolicy(loan.currency, transaction);
    const rounding = RoundingPolicy.create(policy.minorUnit, policy.roundingMode);
    const interestLoan = ExactDecimal.create(loan.outstandingPrincipal)
      .multiply(ExactDecimal.create(loan.nominalAnnualRate))
      .divide(ExactDecimal.create('100'), INTERNAL)
      .divide(ExactDecimal.create('12'), INTERNAL)
      .round(rounding);
    const interestNative = await this.convertRequired(
      interestLoan.toString(),
      loan.currency,
      rule.currency,
      dueOn,
    );
    const feeNative = await this.convertRequired(
      loan.insuranceMonthly,
      loan.currency,
      rule.currency,
      dueOn,
    );
    const amount = exact(rule.amount, 'Scheduled payment amount');
    const principal = amount
      .subtract(ExactDecimal.create(interestNative.convertedAmount))
      .subtract(ExactDecimal.create(feeNative.convertedAmount));
    if (principal.isNegative()) {
      throw semantic('Scheduled payment is lower than its interest and insurance components');
    }
    await this.postPayment(
      transaction,
      rule.userId,
      rule.loanId,
      {
        amount: amount.toString(),
        currency: rule.currency,
        principalComponent: principal.toString(),
        interestComponent: interestNative.convertedAmount,
        feeComponent: feeNative.convertedAmount,
        paidOn: dueOn,
        note: 'Scheduled loan repayment',
      },
      randomUUID(),
      deterministicHash(`loan-scheduled:${occurrenceId}`),
      'scheduled',
      occurrenceId,
      null,
    );
  }

  private async postManual(
    userId: string,
    loanId: string,
    rawKey: string | undefined,
    dto: CreateLoanPaymentDto,
    correctsPaymentId: string | null,
  ): Promise<IdempotentLoan> {
    const key = requiredKey(rawKey);
    const result = await this.idempotency.execute(
      execution(userId, 'loans.payments.create', key, dto),
      async (transaction) =>
        loanJson(
          await this.postPayment(
            transaction,
            userId,
            loanId,
            dto,
            randomUUID(),
            key.toHash(),
            'manual',
            null,
            correctsPaymentId,
          ),
        ),
    );
    return { value: result.value.loan as unknown as Loan, replayed: result.replayed };
  }

  private async postPayment(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    dto: CreateLoanPaymentDto,
    paymentId: string,
    keyHash: string,
    source: 'manual' | 'scheduled',
    occurrenceId: string | null,
    correctsPaymentId: string | null,
    replacesEntryId?: string,
  ): Promise<Loan> {
    const loan = await this.requiredLoan(transaction, userId, loanId);
    if (loan.archivedAt !== null || (loan.completedAt !== null && correctsPaymentId === null)) {
      throw conflict('Completed or archived loans cannot receive payments');
    }
    CalendarDate.create(dto.paidOn);
    await this.assertCurrency(transaction, userId, dto.currency, 'Payment');
    const amount = exact(dto.amount, 'Payment amount');
    const principal = exact(dto.principalComponent, 'Principal component');
    const interest = exact(dto.interestComponent, 'Interest component');
    const fee = exact(dto.feeComponent, 'Fee component');
    if (
      !amount.isPositive() ||
      principal.isNegative() ||
      interest.isNegative() ||
      fee.isNegative()
    ) {
      throw semantic('Payment and component amounts must satisfy their non-negative invariants');
    }
    if (!principal.add(interest).add(fee).equals(amount)) {
      throw semantic('Principal, interest, and fee components must equal the payment amount');
    }
    const totalConversion = await this.convertRequired(
      amount.toString(),
      dto.currency,
      loan.currency,
      dto.paidOn,
    );
    const interestConversion = await this.convertRequired(
      interest.toString(),
      dto.currency,
      loan.currency,
      dto.paidOn,
    );
    const feeConversion = await this.convertRequired(
      fee.toString(),
      dto.currency,
      loan.currency,
      dto.paidOn,
    );
    const loanPrincipal = ExactDecimal.create(totalConversion.convertedAmount)
      .subtract(ExactDecimal.create(interestConversion.convertedAmount))
      .subtract(ExactDecimal.create(feeConversion.convertedAmount));
    if (loanPrincipal.isNegative()) {
      throw semantic('FX-rounded payment components cannot produce a negative principal amount');
    }
    if (loanPrincipal.compare(ExactDecimal.create(loan.outstandingPrincipal)) > 0) {
      throw semantic('Payment principal exceeds the exact outstanding loan principal');
    }
    const now = this.clock.now().toDate();
    const entry = await this.ledger.post(transaction, {
      userId,
      actorUserId: userId,
      economicType: 'loan_repayment',
      amount: amount.toString(),
      currency: dto.currency,
      postedOn: dto.paidOn,
      effectiveAt: now,
      createdAt: now,
      sourceAccountId: await this.repository.defaultCashAccount(transaction, userId),
      destinationAccountId: loan.liabilityAccountId,
      note: dto.note,
      sourceModule: 'loans',
      sourceReferenceId: paymentId,
      idempotencyKeyHash: keyHash,
      replacesEntryId,
    });
    await this.fx.snapshotPostedEntry(transaction, entry, userId, dto.paidOn, now);
    await this.repository.insertPayment(transaction, {
      id: paymentId,
      userId,
      loanId,
      journalEntryId: entry.id,
      amount: amount.toString(),
      currency: dto.currency,
      principalComponent: principal.toString(),
      interestComponent: interest.toString(),
      feeComponent: fee.toString(),
      loanPrincipalComponent: loanPrincipal.toString(),
      loanInterestComponent: interestConversion.convertedAmount,
      loanFeeComponent: feeConversion.convertedAmount,
      loanCurrency: loan.currency,
      conversionStatus: totalConversion.status,
      conversionRate: totalConversion.conversionRate,
      conversionProvider: totalConversion.provider,
      rateAt: new Date(totalConversion.rateAt),
      fetchedAt: new Date(totalConversion.fetchedAt),
      paidOn: dto.paidOn,
      source,
      recurringOccurrenceId: occurrenceId,
      note: dto.note?.trim() ?? null,
      correctsPaymentId,
      createdAt: now,
    });
    const remaining = ExactDecimal.create(loan.outstandingPrincipal).subtract(loanPrincipal);
    await this.repository.setLifecycle(
      transaction,
      userId,
      loanId,
      remaining.isZero() ? now : null,
      undefined,
      now,
    );
    return this.requiredResponseLoan(transaction, userId, loanId);
  }

  private async reverseJournal(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    paymentId: string,
    entryId: string,
    postedOn: string,
    keyHash: string,
    now: Date,
    note?: string,
  ): Promise<JournalEntry> {
    const original = await this.ledger.findOwnedEntry(transaction, userId, entryId);
    const reversal = await this.ledger.reverse(transaction, original, {
      userId,
      actorUserId: userId,
      postedOn,
      effectiveAt: now,
      createdAt: now,
      note,
      idempotencyKeyHash: keyHash,
      sourceModule: 'loans',
      sourceReferenceId: paymentId,
    });
    await this.fx.copyReversalSnapshot(transaction, original.id, reversal.id, userId, now);
    await this.repository.markReversed(transaction, userId, paymentId, reversal.id);
    return reversal;
  }

  private async convertRequired(
    amount: string,
    source: string,
    target: string,
    date: string,
  ): Promise<{
    status: 'available' | 'stale';
    convertedAmount: string;
    conversionRate: string;
    provider: string;
    rateAt: string;
    fetchedAt: string;
  }> {
    const result = await this.fx.convertObserved(amount, source, target, date);
    if (
      result.status === 'unavailable' ||
      result.convertedAmount === undefined ||
      result.conversionRate === undefined ||
      result.provider === undefined ||
      result.rateAt === undefined ||
      result.fetchedAt === undefined
    ) {
      throw semantic('An observed FX conversion is required for the payment date');
    }
    return {
      status: result.status,
      convertedAmount: result.convertedAmount,
      conversionRate: result.conversionRate,
      provider: result.provider,
      rateAt: result.rateAt,
      fetchedAt: result.fetchedAt,
    };
  }

  private async assertCurrency(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    currency: string,
    label: string,
  ): Promise<void> {
    if (!(await this.repository.currencyOwned(userId, currency, transaction))) {
      throw semantic(`${label} currency must be selected by the current user`);
    }
  }

  private async requiredLoan(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
  ): Promise<LockedLoan> {
    const loan = await this.repository.lockLoan(transaction, userId, loanId);
    if (!loan) throw notFound();
    return loan;
  }

  private async requiredResponseLoan(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
  ): Promise<Loan> {
    const loan = await this.repository.loan(userId, loanId, transaction);
    if (!loan) throw notFound();
    return loan;
  }
}

function normalizeLoan(dto: CreateLoanDto): LoanWrite {
  const principal = exact(dto.principal, 'Principal');
  if (!principal.isPositive()) throw semantic('Principal must be greater than zero');
  const rate = exact(dto.nominalAnnualRate, 'Nominal annual rate');
  if (rate.isNegative()) throw semantic('Nominal annual rate cannot be negative');
  const startsOn = CalendarDate.create(dto.startsOn).toString();
  const endsOn = dto.endsOn ? CalendarDate.create(dto.endsOn).toString() : null;
  if (endsOn !== null && endsOn < startsOn) {
    throw semantic('Loan end date cannot precede its start date');
  }
  return {
    title: dto.title.trim(),
    principal: principal.toString(),
    currency: dto.currency,
    nominalAnnualRate: rate.toString(),
    termMonths: dto.termMonths,
    startsOn,
    endsOn,
    paymentDay: dto.paymentDay ?? null,
    extraPaymentScenario: nonNegative(dto.extraPaymentScenario ?? '0', 'Extra-payment scenario'),
    insuranceMonthly: nonNegative(dto.insuranceMonthly ?? '0', 'Monthly insurance'),
  };
}

function schedule(dto: CreateLoanRecurringRuleDto): RecurringRuleWrite {
  const amount = exact(dto.amount, 'Scheduled payment amount');
  if (!amount.isPositive()) throw semantic('Scheduled payment amount must be greater than zero');
  try {
    return {
      title: dto.title.trim(),
      amount: amount.toString(),
      currency: dto.currency,
      economicType: 'expense',
      startsOn: CalendarDate.create(dto.startsOn).toString(),
      rrule: parseRecurrenceRule(dto.rrule).canonical,
      categoryId: null,
      loanId: null,
    };
  } catch (error) {
    if (error instanceof InvalidRecurrenceRuleError) throw semantic(error.message);
    throw error;
  }
}

function nonNegative(value: string, label: string): string {
  const parsed = exact(value, label);
  if (parsed.isNegative()) throw semantic(`${label} cannot be negative`);
  return parsed.toString();
}

function exact(value: string, label: string): ExactDecimal {
  try {
    return ExactDecimal.create(value);
  } catch {
    throw semantic(`${label} must be an exact base-10 decimal string`);
  }
}

function assertNonEmpty(value: object): void {
  if (Object.values(value).every((item) => item === undefined)) {
    throw new ApplicationError(400, 'BAD_REQUEST', 'At least one field is required');
  }
}

function requiredKey(value: string | undefined): IdempotencyKey {
  if (!value) throw new ApplicationError(400, 'BAD_REQUEST', 'Idempotency-Key header is required');
  try {
    return IdempotencyKey.create(value);
  } catch {
    throw new ApplicationError(400, 'BAD_REQUEST', 'Idempotency-Key header is invalid');
  }
}

function execution(
  userId: string,
  operation: string,
  key: IdempotencyKey,
  request: object,
): {
  scopeId: EntityId;
  operation: IdempotencyOperation;
  key: IdempotencyKey;
  requestFingerprint: RequestFingerprint;
} {
  return {
    scopeId: EntityId.create(userId),
    operation: IdempotencyOperation.create(operation),
    key,
    requestFingerprint: RequestFingerprint.fromCanonicalRequest(JSON.stringify(request)),
  };
}

function derivedHash(hash: string, purpose: string): string {
  return deterministicHash(`${hash}:${purpose}`);
}

function deterministicHash(value: string): string {
  return createHash('sha256').update(value, 'utf8').digest('hex');
}

function loanJson(loan: Loan): Record<string, JsonValue> {
  return { loan: loan as unknown as JsonValue };
}

function notFound(message = 'Loan was not found'): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', message);
}

function paymentNotFound(): ApplicationError {
  return notFound('Loan payment was not found');
}

function ruleNotFound(): ApplicationError {
  return notFound('Loan recurring rule was not found');
}

function forbidden(): ApplicationError {
  return new ApplicationError(403, 'FORBIDDEN', 'Personal-finance access is not permitted');
}

function conflict(message: string): ApplicationError {
  return new ApplicationError(409, 'CONFLICT', message);
}

function semantic(message: string): ApplicationError {
  return new ApplicationError(422, 'UNPROCESSABLE_ENTITY', message);
}

function translate(error: unknown): Error {
  if (error instanceof ApplicationError) return error;
  const code =
    typeof error === 'object' && error !== null && 'code' in error ? String(error.code) : '';
  if (code === '23505') return conflict('Loan, payment, or schedule state already exists');
  if (code === '23503' || code === '23514') {
    return semantic('Loan ownership or financial invariant failed');
  }
  if (code === '55000') return conflict('Posted financial history is immutable');
  return error instanceof Error ? error : new Error('Loan persistence failed');
}
