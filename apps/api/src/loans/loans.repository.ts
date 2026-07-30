import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import { sql, type Kysely, type Transaction } from 'kysely';
import type { UserRole } from '../identity/identity.types';
import { DATABASE } from '../platform/database/database.constants';
import type { DatabaseSchema } from '../platform/database/database.types';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import type { RecurringRuleWrite } from '../recurrence/recurrence.repository';
import type { Loan, LoanPayment, LoanRecurringRule, LockedLoan } from './loans.types';
import { derivedOutstanding, projectLoanSchedule, standardMonthlyAnnuity } from './loan-calculator';

type Executor = Kysely<DatabaseSchema> | Transaction<DatabaseSchema>;

export interface LoanWrite {
  title: string;
  principal: string;
  currency: string;
  nominalAnnualRate: string;
  termMonths: number;
  startsOn: string;
  endsOn: string | null;
  paymentDay: number | null;
  extraPaymentScenario: string;
  insuranceMonthly: string;
}

export interface PaymentWrite {
  id: string;
  userId: string;
  loanId: string;
  journalEntryId: string;
  amount: string;
  currency: string;
  principalComponent: string;
  interestComponent: string;
  feeComponent: string;
  loanPrincipalComponent: string;
  loanInterestComponent: string;
  loanFeeComponent: string;
  loanCurrency: string;
  conversionStatus: 'available' | 'stale';
  conversionRate: string;
  conversionProvider: string;
  rateAt: Date;
  fetchedAt: Date;
  paidOn: string;
  source: 'manual' | 'scheduled';
  recurringOccurrenceId: string | null;
  note: string | null;
  correctsPaymentId: string | null;
  createdAt: Date;
}

@Injectable()
export class LoansRepository {
  constructor(@Inject(DATABASE) private readonly database: Kysely<DatabaseSchema>) {}

  transaction<T>(work: (transaction: Transaction<DatabaseSchema>) => Promise<T>): Promise<T> {
    return this.database.transaction().execute(work);
  }

  async lockUser(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<{ role: UserRole } | null> {
    return (
      (await transaction
        .selectFrom('mymoneymap.users')
        .select('role')
        .where('id', '=', userId)
        .forUpdate()
        .executeTakeFirst()) ?? null
    );
  }

  async countActive(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.loans')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .where('archived_at', 'is', null)
      .where('completed_at', 'is', null)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  currencyOwned(userId: string, currency: string, executor: Executor): Promise<boolean> {
    return executor
      .selectFrom('mymoneymap.user_currencies')
      .select('code')
      .where('user_id', '=', userId)
      .where('code', '=', currency)
      .executeTakeFirst()
      .then(Boolean);
  }

  async currencyPolicy(
    currency: string,
    executor: Executor = this.database,
  ): Promise<{ minorUnit: number; roundingMode: 'DOWN' | 'UP' | 'HALF_UP' | 'HALF_EVEN' }> {
    const row = await executor
      .selectFrom('mymoneymap.currencies')
      .select(['minor_unit', 'rounding_mode'])
      .where('code', '=', currency)
      .executeTakeFirstOrThrow();
    return { minorUnit: row.minor_unit, roundingMode: row.rounding_mode };
  }

  async create(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    id: string,
    accountId: string,
    values: LoanWrite,
    now: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.loans')
      .values({
        id,
        user_id: userId,
        title: values.title,
        principal: values.principal,
        currency: values.currency,
        nominal_annual_rate: values.nominalAnnualRate,
        term_months: values.termMonths,
        starts_on: values.startsOn,
        ends_on: values.endsOn,
        payment_day: values.paymentDay,
        extra_payment_scenario: values.extraPaymentScenario,
        insurance_monthly: values.insuranceMonthly,
        estimate_version: 'standard_nominal_monthly_annuity_v1',
        liability_account_id: accountId,
        completed_at: null,
        archived_at: null,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async list(userId: string, executor: Executor = this.database): Promise<Loan[]> {
    const rows = await executor
      .selectFrom('mymoneymap.loans')
      .selectAll()
      .where('user_id', '=', userId)
      .orderBy('archived_at')
      .orderBy('starts_on', 'desc')
      .orderBy('id', 'desc')
      .execute();
    return Promise.all(
      rows.map(async (row) => {
        const [payments, recurringRule, policy] = await Promise.all([
          this.payments(userId, row.id, executor),
          this.loanRule(userId, row.id, executor),
          this.currencyPolicy(row.currency, executor),
        ]);
        return {
          id: row.id,
          title: row.title,
          principal: exactText(row.principal),
          outstandingPrincipal: derivedOutstanding(row.principal, payments),
          currency: row.currency,
          nominalAnnualRate: exactText(row.nominal_annual_rate),
          termMonths: row.term_months,
          startsOn: dateText(row.starts_on),
          endsOn: row.ends_on === null ? null : dateText(row.ends_on),
          paymentDay: row.payment_day,
          extraPaymentScenario: exactText(row.extra_payment_scenario),
          insuranceMonthly: exactText(row.insurance_monthly),
          estimate: {
            version: 'standard_nominal_monthly_annuity_v1',
            label: 'Standard fixed nominal-rate monthly annuity illustration',
            rateLabel: 'Nominal annual rate',
            isApr: false,
            monthlyPayment: standardMonthlyAnnuity(
              row.principal,
              row.nominal_annual_rate,
              row.term_months,
              policy.minorUnit,
              policy.roundingMode,
            ),
            assumptions: [
              'Equal monthly periods',
              'Fixed nominal annual rate divided by twelve',
              'Contract fees and irregular day-count conventions are not modeled',
              'Extra payment is a projection scenario until posted',
            ],
          },
          projectedSchedule: projectLoanSchedule({
            principal: row.principal,
            nominalAnnualRate: row.nominal_annual_rate,
            termMonths: row.term_months,
            startsOn: dateText(row.starts_on),
            paymentDay: row.payment_day,
            extraPaymentScenario: row.extra_payment_scenario,
            insuranceMonthly: row.insurance_monthly,
            currencyScale: policy.minorUnit,
            roundingMode: policy.roundingMode,
          }),
          payments,
          recurringRule,
          completedAt: row.completed_at?.toISOString() ?? null,
          archivedAt: row.archived_at?.toISOString() ?? null,
          createdAt: row.created_at.toISOString(),
          updatedAt: row.updated_at.toISOString(),
        };
      }),
    );
  }

  async loan(
    userId: string,
    loanId: string,
    executor: Executor = this.database,
  ): Promise<Loan | null> {
    return (await this.list(userId, executor)).find(({ id }) => id === loanId) ?? null;
  }

  async lockLoan(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
  ): Promise<LockedLoan | null> {
    const row = await transaction
      .selectFrom('mymoneymap.loans')
      .selectAll()
      .where('id', '=', loanId)
      .where('user_id', '=', userId)
      .forUpdate()
      .executeTakeFirst();
    if (!row) return null;
    return {
      id: row.id,
      userId: row.user_id,
      title: row.title,
      principal: exactText(row.principal),
      outstandingPrincipal: derivedOutstanding(
        row.principal,
        await this.payments(userId, loanId, transaction),
      ),
      currency: row.currency,
      nominalAnnualRate: exactText(row.nominal_annual_rate),
      insuranceMonthly: exactText(row.insurance_monthly),
      liabilityAccountId: row.liability_account_id,
      completedAt: row.completed_at,
      archivedAt: row.archived_at,
    };
  }

  async update(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    values: Partial<LoanWrite>,
    now: Date,
  ): Promise<void> {
    await transaction
      .updateTable('mymoneymap.loans')
      .set({
        ...(values.title === undefined ? {} : { title: values.title }),
        ...(values.principal === undefined ? {} : { principal: values.principal }),
        ...(values.nominalAnnualRate === undefined
          ? {}
          : { nominal_annual_rate: values.nominalAnnualRate }),
        ...(values.termMonths === undefined ? {} : { term_months: values.termMonths }),
        ...(values.startsOn === undefined ? {} : { starts_on: values.startsOn }),
        ...(values.endsOn === undefined ? {} : { ends_on: values.endsOn }),
        ...(values.paymentDay === undefined ? {} : { payment_day: values.paymentDay }),
        ...(values.extraPaymentScenario === undefined
          ? {}
          : { extra_payment_scenario: values.extraPaymentScenario }),
        ...(values.insuranceMonthly === undefined
          ? {}
          : { insurance_monthly: values.insuranceMonthly }),
        updated_at: now,
      })
      .where('id', '=', loanId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  setLifecycle(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    completedAt: Date | null,
    archivedAt: Date | null | undefined,
    now: Date,
  ): Promise<unknown> {
    return transaction
      .updateTable('mymoneymap.loans')
      .set({
        completed_at: completedAt,
        ...(archivedAt === undefined ? {} : { archived_at: archivedAt }),
        updated_at: now,
      })
      .where('id', '=', loanId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async deleteEmpty(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    accountId: string,
  ): Promise<void> {
    await transaction
      .deleteFrom('mymoneymap.recurring_rules')
      .where('user_id', '=', userId)
      .where('loan_id', '=', loanId)
      .execute();
    await transaction
      .deleteFrom('mymoneymap.loans')
      .where('id', '=', loanId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    await transaction
      .deleteFrom('mymoneymap.ledger_accounts')
      .where('id', '=', accountId)
      .where('user_id', '=', userId)
      .execute();
  }

  async payments(userId: string, loanId: string, executor: Executor): Promise<LoanPayment[]> {
    const rows = await executor
      .selectFrom('mymoneymap.loan_payments')
      .selectAll()
      .where('user_id', '=', userId)
      .where('loan_id', '=', loanId)
      .orderBy('paid_on', 'desc')
      .orderBy('created_at', 'desc')
      .orderBy('id', 'desc')
      .execute();
    return rows.map((row) => ({
      id: row.id,
      journalEntryId: row.journal_entry_id,
      amount: exactText(row.amount),
      currency: row.currency,
      principalComponent: exactText(row.principal_component),
      interestComponent: exactText(row.interest_component),
      feeComponent: exactText(row.fee_component),
      loanPrincipalComponent: exactText(row.loan_principal_component),
      loanInterestComponent: exactText(row.loan_interest_component),
      loanFeeComponent: exactText(row.loan_fee_component),
      loanCurrency: row.loan_currency,
      conversion: {
        status: row.conversion_status,
        rate: exactText(row.conversion_rate),
        provider: row.conversion_provider,
        rateAt: row.rate_at.toISOString(),
        fetchedAt: row.fetched_at.toISOString(),
      },
      paidOn: dateText(row.paid_on),
      source: row.source,
      recurringOccurrenceId: row.recurring_occurrence_id,
      note: row.note,
      reversedByJournalEntryId: row.reversed_by_journal_entry_id,
      correctsPaymentId: row.corrects_payment_id,
      createdAt: row.created_at.toISOString(),
    }));
  }

  async payment(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    paymentId: string,
  ): Promise<LoanPayment | null> {
    return (
      (await this.payments(userId, loanId, transaction)).find(({ id }) => id === paymentId) ?? null
    );
  }

  insertPayment(transaction: Transaction<DatabaseSchema>, value: PaymentWrite): Promise<unknown> {
    return transaction
      .insertInto('mymoneymap.loan_payments')
      .values({
        id: value.id,
        user_id: value.userId,
        loan_id: value.loanId,
        journal_entry_id: value.journalEntryId,
        amount: value.amount,
        currency: value.currency,
        principal_component: value.principalComponent,
        interest_component: value.interestComponent,
        fee_component: value.feeComponent,
        loan_principal_component: value.loanPrincipalComponent,
        loan_interest_component: value.loanInterestComponent,
        loan_fee_component: value.loanFeeComponent,
        loan_currency: value.loanCurrency,
        conversion_status: value.conversionStatus,
        conversion_rate: value.conversionRate,
        conversion_provider: value.conversionProvider,
        rate_at: value.rateAt,
        fetched_at: value.fetchedAt,
        paid_on: value.paidOn,
        source: value.source,
        recurring_occurrence_id: value.recurringOccurrenceId,
        note: value.note,
        reversed_by_journal_entry_id: null,
        corrects_payment_id: value.correctsPaymentId,
        created_at: value.createdAt,
      })
      .execute();
  }

  markReversed(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    paymentId: string,
    reversalId: string,
  ): Promise<unknown> {
    return transaction
      .updateTable('mymoneymap.loan_payments')
      .set({ reversed_by_journal_entry_id: reversalId })
      .where('id', '=', paymentId)
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
  }

  async references(userId: string, loanId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.loan_payments')
      .select(sql<number>`count(*)`.as('count'))
      .where('user_id', '=', userId)
      .where('loan_id', '=', loanId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async countRules(userId: string, executor: Executor): Promise<number> {
    const row = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .select(({ fn }) => fn.countAll<number>().as('count'))
      .where('user_id', '=', userId)
      .executeTakeFirstOrThrow();
    return Number(row.count);
  }

  async defaultCashAccount(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
  ): Promise<string> {
    return (
      await transaction
        .selectFrom('mymoneymap.ledger_accounts')
        .select('id')
        .where('user_id', '=', userId)
        .where('kind', '=', 'cash')
        .executeTakeFirstOrThrow()
    ).id;
  }

  async loanRule(
    userId: string,
    loanId: string,
    executor: Executor,
  ): Promise<LoanRecurringRule | null> {
    const row = await executor
      .selectFrom('mymoneymap.recurring_rules')
      .selectAll()
      .where('user_id', '=', userId)
      .where('loan_id', '=', loanId)
      .executeTakeFirst();
    return row
      ? {
          id: row.id,
          title: row.title,
          amount: exactText(row.amount),
          currency: row.currency,
          economicType: 'expense',
          startsOn: dateText(row.starts_on),
          rrule: row.rrule,
          loanId,
          createdAt: row.created_at.toISOString(),
          updatedAt: row.updated_at.toISOString(),
        }
      : null;
  }

  async createRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    values: RecurringRuleWrite & { loanId: string },
    now: Date,
  ): Promise<void> {
    await transaction
      .insertInto('mymoneymap.recurring_rules')
      .values({
        id: randomUUID(),
        user_id: userId,
        title: values.title,
        amount: values.amount,
        currency: values.currency,
        economic_type: 'expense',
        starts_on: values.startsOn,
        rrule: values.rrule,
        category_id: null,
        goal_id: null,
        loan_id: values.loanId,
        created_at: now,
        updated_at: now,
      })
      .execute();
  }

  async updateRule(
    transaction: Transaction<DatabaseSchema>,
    userId: string,
    loanId: string,
    values: RecurringRuleWrite,
    now: Date,
  ): Promise<boolean> {
    const row = await transaction
      .updateTable('mymoneymap.recurring_rules')
      .set({
        title: values.title,
        amount: values.amount,
        currency: values.currency,
        starts_on: values.startsOn,
        rrule: values.rrule,
        updated_at: now,
      })
      .where('user_id', '=', userId)
      .where('loan_id', '=', loanId)
      .returning('id')
      .executeTakeFirst();
    if (!row) return false;
    await transaction
      .deleteFrom('mymoneymap.recurring_occurrences')
      .where('user_id', '=', userId)
      .where('rule_id', '=', row.id)
      .execute();
    return true;
  }

  async deleteRule(userId: string, loanId: string): Promise<boolean> {
    return (
      (await this.database
        .deleteFrom('mymoneymap.recurring_rules')
        .where('user_id', '=', userId)
        .where('loan_id', '=', loanId)
        .returning('id')
        .executeTakeFirst()) !== undefined
    );
  }
}

function dateText(value: string | Date): string {
  if (typeof value === 'string') return value;
  return [
    String(value.getFullYear()).padStart(4, '0'),
    String(value.getMonth() + 1).padStart(2, '0'),
    String(value.getDate()).padStart(2, '0'),
  ].join('-');
}

function exactText(value: string): string {
  return ExactDecimal.create(value).toString();
}
