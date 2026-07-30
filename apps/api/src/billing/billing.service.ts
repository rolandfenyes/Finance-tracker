/* eslint-disable @typescript-eslint/explicit-function-return-type, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unnecessary-type-assertion */
import { Inject, Injectable } from '@nestjs/common';
import { randomUUID } from 'node:crypto';
import type { Pool, PoolClient } from 'pg';
import { ExactDecimal } from '../platform/decimal/exact-decimal';
import { POSTGRES_POOL } from '../platform/database/database.constants';
import { ApplicationError } from '../platform/http/application-error';
import type {
  AssignSubscriptionDto,
  PaymentDto,
  PlanDto,
  PromotionDto,
  TrialPromotionDto,
  UpdateInvoiceDto,
} from './billing.dto';

@Injectable()
export class BillingService {
  constructor(@Inject(POSTGRES_POOL) private readonly pool: Pool) {}

  async summary(limit: number) {
    const [counts, subscriptions, invoices, payments] = await Promise.all([
      this.pool.query(
        `SELECT
           (SELECT count(*) FROM mymoneymap.billing_plans)::text plans,
           (SELECT count(*) FROM mymoneymap.billing_promotions)::text promotions,
           (SELECT count(*) FROM mymoneymap.user_subscriptions)::text subscriptions,
           (SELECT count(*) FROM mymoneymap.user_invoices)::text invoices,
           (SELECT count(*) FROM mymoneymap.user_payments)::text payments`,
      ),
      this.pool.query(
        `SELECT id,user_id,plan_code,plan_name,status,billing_interval,interval_count,
                amount::text,currency,started_at,current_period_start,current_period_end,
                cancel_at,canceled_at,trial_ends_at,notes,created_at,updated_at
           FROM mymoneymap.user_subscriptions ORDER BY created_at DESC,id DESC LIMIT $1`,
        [limit],
      ),
      this.pool.query(
        `SELECT id,user_id,subscription_id,invoice_number,status,total_amount::text,currency,
                issued_at,due_at,paid_at,failure_reason,refund_reason,notes,created_at,updated_at
           FROM mymoneymap.user_invoices ORDER BY issued_at DESC,id DESC LIMIT $1`,
        [limit],
      ),
      this.pool.query(
        `SELECT id,user_id,invoice_id,type,status,amount::text,currency,gateway,
                transaction_reference,failure_reason,notes,processed_at,created_at,updated_at
           FROM mymoneymap.user_payments ORDER BY processed_at DESC,id DESC LIMIT $1`,
        [limit],
      ),
    ]);
    const count = counts.rows[0] as Record<string, string>;
    return {
      mode: 'administrative_records_only',
      providerCapabilities: {
        checkout: false,
        portal: false,
        webhooks: false,
        customerCancellation: false,
      },
      counts: Object.fromEntries(Object.entries(count).map(([key, value]) => [key, Number(value)])),
      subscriptions: subscriptions.rows.map(mapRow),
      invoices: invoices.rows.map(mapRow),
      payments: payments.rows.map(mapRow),
    };
  }

  async plans() {
    const result = await this.pool.query(
      `SELECT id,code,name,description,price::text,currency,billing_interval,interval_count,
              role_slug,trial_days,is_active,stripe_product_id,stripe_price_id,metadata,
              created_at,updated_at
         FROM mymoneymap.billing_plans ORDER BY code`,
    );
    return result.rows.map(mapRow);
  }

  async plan(id: string) {
    const result = await this.pool.query(
      `SELECT id,code,name,description,price::text,currency,billing_interval,interval_count,
              role_slug,trial_days,is_active,stripe_product_id,stripe_price_id,metadata,
              created_at,updated_at
         FROM mymoneymap.billing_plans WHERE id=$1`,
      [id],
    );
    if (!result.rows[0]) throw notFound('Billing plan');
    return mapRow(result.rows[0]);
  }

  async createPlan(actor: string, dto: PlanDto) {
    validateMetadata(dto.metadata);
    return this.transaction(async (client) => {
      const id = randomUUID();
      const result = await client.query(
        `INSERT INTO mymoneymap.billing_plans
           (id,code,name,description,price,currency,billing_interval,interval_count,role_slug,
            trial_days,is_active,stripe_product_id,stripe_price_id,metadata,created_at,updated_at)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,now(),now())
         RETURNING id,code,name,description,price::text,currency,billing_interval,interval_count,
                   role_slug,trial_days,is_active,stripe_product_id,stripe_price_id,metadata,
                   created_at,updated_at`,
        planValues(id, dto),
      );
      await audit(client, actor, 'billing.plan_created', 'billing_plan', id, {
        code: dto.code,
        roleSlug: dto.roleSlug,
      });
      return mapRow(result.rows[0]!);
    });
  }

  async updatePlan(actor: string, id: string, dto: PlanDto) {
    validateMetadata(dto.metadata);
    return this.transaction(async (client) => {
      await lock(client, 'billing_plans', id, 'Billing plan');
      const result = await client.query(
        `UPDATE mymoneymap.billing_plans SET
           code=$2,name=$3,description=$4,price=$5,currency=$6,billing_interval=$7,
           interval_count=$8,role_slug=$9,trial_days=$10,is_active=$11,
           stripe_product_id=$12,stripe_price_id=$13,metadata=$14,updated_at=now()
         WHERE id=$1
         RETURNING id,code,name,description,price::text,currency,billing_interval,interval_count,
                   role_slug,trial_days,is_active,stripe_product_id,stripe_price_id,metadata,
                   created_at,updated_at`,
        planValues(id, dto),
      );
      await audit(client, actor, 'billing.plan_updated', 'billing_plan', id, {
        code: dto.code,
        roleSlug: dto.roleSlug,
      });
      return mapRow(result.rows[0]!);
    });
  }

  async deletePlan(actor: string, id: string): Promise<void> {
    await this.transaction(async (client) => {
      const existing = await lock(client, 'billing_plans', id, 'Billing plan');
      const active = await client.query(
        `SELECT 1 FROM mymoneymap.user_subscriptions
          WHERE plan_code=$1 AND status IN ('active','trialing','past_due') LIMIT 1`,
        [existing.code],
      );
      if (active.rows[0]) {
        throw new ApplicationError(
          409,
          'CONFLICT',
          'A plan with current subscriptions cannot be deleted',
        );
      }
      await client.query('DELETE FROM mymoneymap.billing_plans WHERE id=$1', [id]);
      await audit(client, actor, 'billing.plan_deleted', 'billing_plan', id, {
        code: existing.code,
      });
    });
  }

  async promotions() {
    const result = await this.pool.query(
      `SELECT id,code,name,description,discount_percent::text,discount_amount::text,currency,
              max_redemptions,redeem_by,trial_days,plan_code,stripe_coupon_id,
              stripe_promo_code_id,metadata,created_at,updated_at
         FROM mymoneymap.billing_promotions ORDER BY code`,
    );
    return result.rows.map(mapRow);
  }

  async promotion(id: string) {
    const result = await this.pool.query(
      `SELECT id,code,name,description,discount_percent::text,discount_amount::text,currency,
              max_redemptions,redeem_by,trial_days,plan_code,stripe_coupon_id,
              stripe_promo_code_id,metadata,created_at,updated_at
         FROM mymoneymap.billing_promotions WHERE id=$1`,
      [id],
    );
    if (!result.rows[0]) throw notFound('Billing promotion');
    return mapRow(result.rows[0]);
  }

  async createPromotion(actor: string, dto: PromotionDto) {
    validatePromotion(dto);
    return this.writePromotion(actor, randomUUID(), dto, false);
  }

  async updatePromotion(actor: string, id: string, dto: PromotionDto) {
    validatePromotion(dto);
    return this.writePromotion(actor, id, dto, true);
  }

  async deletePromotion(actor: string, id: string): Promise<void> {
    await this.transaction(async (client) => {
      const existing = await lock(client, 'billing_promotions', id, 'Billing promotion');
      await client.query('DELETE FROM mymoneymap.billing_promotions WHERE id=$1', [id]);
      await audit(client, actor, 'billing.promotion_deleted', 'billing_promotion', id, {
        code: existing.code,
      });
    });
  }

  async trialPromotion(actor: string, dto: TrialPromotionDto) {
    const plan = await this.pool.query<{ trial_days: number | null; name: string }>(
      'SELECT trial_days,name FROM mymoneymap.billing_plans WHERE code=$1 AND is_active',
      [dto.planCode],
    );
    if (!plan.rows[0]) throw notFound('Active billing plan');
    const trialDays = dto.trialDays ?? plan.rows[0].trial_days;
    if (!trialDays || trialDays < 1) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        'The selected plan has no positive trial period',
      );
    }
    const token = randomUUID().replaceAll('-', '').slice(0, 12).toUpperCase();
    return this.createPromotion(actor, {
      code: `TRIAL-${token}`,
      name: `Trial for ${plan.rows[0].name}`,
      description: 'Administrative trial record',
      trialDays,
      maxRedemptions: dto.maxRedemptions ?? null,
      planCode: dto.planCode,
      metadata: {},
    });
  }

  async assign(actor: string, userId: string, dto: AssignSubscriptionDto) {
    return this.transaction(async (client) => {
      const user = await lock(client, 'users', userId, 'User');
      if (user.role === 'admin') {
        throw new ApplicationError(
          409,
          'CONFLICT',
          'Administrator entitlements cannot be assigned from a billing plan',
        );
      }
      const selected = await client.query<Record<string, unknown>>(
        'SELECT * FROM mymoneymap.billing_plans WHERE id=$1 AND is_active FOR UPDATE',
        [dto.planId],
      );
      const plan = selected.rows[0];
      if (!plan) throw notFound('Active billing plan');
      const now = new Date();
      const periodEnd = calculatePeriodEnd(
        now,
        String(plan.billing_interval),
        Number(plan.interval_count),
      );
      const trialEnds =
        dto.status === 'trialing' && plan.trial_days
          ? new Date(now.getTime() + Number(plan.trial_days) * 86_400_000)
          : null;
      const id = randomUUID();
      await client.query(
        `INSERT INTO mymoneymap.user_subscriptions
           (id,user_id,plan_code,plan_name,status,billing_interval,interval_count,amount,currency,
            started_at,current_period_start,current_period_end,cancel_at,canceled_at,trial_ends_at,
            notes,created_at,updated_at)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$10,$11,$12,$12,$13,$14,$10,$10)`,
        [
          id,
          userId,
          plan.code,
          plan.name,
          dto.status,
          plan.billing_interval,
          plan.interval_count,
          plan.price,
          plan.currency,
          now,
          periodEnd,
          ['canceled', 'expired'].includes(dto.status) ? now : null,
          trialEnds,
          dto.notes ?? null,
        ],
      );
      await client.query('UPDATE mymoneymap.users SET role=$2,updated_at=$3 WHERE id=$1', [
        userId,
        plan.role_slug,
        now,
      ]);
      await audit(client, actor, 'billing.subscription_assigned', 'subscription', id, {
        userId,
        planCode: plan.code,
        fromRole: user.role,
        toRole: plan.role_slug,
        status: dto.status,
      });
      return {
        id,
        userId,
        planCode: plan.code,
        planName: plan.name,
        status: dto.status,
        amount: String(plan.price),
        currency: plan.currency,
        role: plan.role_slug,
        startedAt: now.toISOString(),
        currentPeriodEnd: periodEnd?.toISOString() ?? null,
        trialEndsAt: trialEnds?.toISOString() ?? null,
      };
    });
  }

  async updateInvoice(actor: string, id: string, dto: UpdateInvoiceDto) {
    return this.transaction(async (client) => {
      await lock(client, 'user_invoices', id, 'Invoice');
      const result = await client.query(
        `UPDATE mymoneymap.user_invoices SET status=$2,paid_at=$3,failure_reason=$4,
                refund_reason=$5,notes=$6,updated_at=now()
          WHERE id=$1
          RETURNING id,user_id,subscription_id,invoice_number,status,total_amount::text,currency,
                    issued_at,due_at,paid_at,failure_reason,refund_reason,notes,created_at,updated_at`,
        [
          id,
          dto.status,
          dto.paidAt ?? null,
          dto.failureReason ?? null,
          dto.refundReason ?? null,
          dto.notes ?? null,
        ],
      );
      await audit(client, actor, 'billing.invoice_updated', 'invoice', id, { status: dto.status });
      return mapRow(result.rows[0]!);
    });
  }

  async createPayment(actor: string, dto: PaymentDto) {
    return this.writePayment(actor, randomUUID(), dto, false);
  }

  async updatePayment(actor: string, id: string, dto: PaymentDto) {
    return this.writePayment(actor, id, dto, true);
  }

  private async writePromotion(actor: string, id: string, dto: PromotionDto, update: boolean) {
    return this.transaction(async (client) => {
      if (update) await lock(client, 'billing_promotions', id, 'Billing promotion');
      const values = [
        id,
        dto.code,
        dto.name,
        dto.description ?? null,
        dto.discountPercent ?? null,
        dto.discountAmount ?? null,
        dto.currency ?? null,
        dto.maxRedemptions ?? null,
        dto.redeemBy ?? null,
        dto.trialDays ?? null,
        dto.planCode ?? null,
        dto.stripeCouponId ?? null,
        dto.stripePromoCodeId ?? null,
        dto.metadata,
      ];
      const result = await client.query(
        update
          ? `UPDATE mymoneymap.billing_promotions SET code=$2,name=$3,description=$4,
               discount_percent=$5,discount_amount=$6,currency=$7,max_redemptions=$8,
               redeem_by=$9,trial_days=$10,plan_code=$11,stripe_coupon_id=$12,
               stripe_promo_code_id=$13,metadata=$14,updated_at=now() WHERE id=$1
             RETURNING *,discount_percent::text,discount_amount::text`
          : `INSERT INTO mymoneymap.billing_promotions
               (id,code,name,description,discount_percent,discount_amount,currency,max_redemptions,
                redeem_by,trial_days,plan_code,stripe_coupon_id,stripe_promo_code_id,metadata,created_at,updated_at)
             VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,now(),now())
             RETURNING *,discount_percent::text,discount_amount::text`,
        values,
      );
      await audit(
        client,
        actor,
        update ? 'billing.promotion_updated' : 'billing.promotion_created',
        'billing_promotion',
        id,
        { code: dto.code, planCode: dto.planCode ?? null },
      );
      return mapRow(result.rows[0]!);
    });
  }

  private async writePayment(actor: string, id: string, dto: PaymentDto, update: boolean) {
    return this.transaction(async (client) => {
      if (update) await lock(client, 'user_payments', id, 'Payment');
      await lock(client, 'users', dto.userId, 'User');
      if (dto.invoiceId) {
        const invoice = await client.query(
          'SELECT currency,total_amount::text FROM mymoneymap.user_invoices WHERE id=$1 AND user_id=$2',
          [dto.invoiceId, dto.userId],
        );
        if (!invoice.rows[0]) throw notFound('Owned invoice');
        if (invoice.rows[0].currency !== dto.currency) {
          throw new ApplicationError(409, 'CONFLICT', 'Payment and invoice currencies must match');
        }
      }
      const values = [
        id,
        dto.userId,
        dto.invoiceId ?? null,
        dto.type,
        dto.status,
        dto.amount,
        dto.currency,
        dto.gateway ?? null,
        dto.transactionReference ?? null,
        dto.failureReason ?? null,
        dto.notes ?? null,
        dto.processedAt,
      ];
      const result = await client.query(
        update
          ? `UPDATE mymoneymap.user_payments SET user_id=$2,invoice_id=$3,type=$4,status=$5,
               amount=$6,currency=$7,gateway=$8,transaction_reference=$9,failure_reason=$10,
               notes=$11,processed_at=$12,updated_at=now() WHERE id=$1
             RETURNING *,amount::text`
          : `INSERT INTO mymoneymap.user_payments
               (id,user_id,invoice_id,type,status,amount,currency,gateway,transaction_reference,
                failure_reason,notes,processed_at,created_at,updated_at)
             VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,now(),now())
             RETURNING *,amount::text`,
        values,
      );
      await audit(
        client,
        actor,
        update ? 'billing.payment_updated' : 'billing.payment_created',
        'payment',
        id,
        {
          userId: dto.userId,
          invoiceId: dto.invoiceId ?? null,
          status: dto.status,
          type: dto.type,
        },
      );
      return mapRow(result.rows[0]!);
    });
  }

  private async transaction<T>(work: (client: PoolClient) => Promise<T>): Promise<T> {
    const client = await this.pool.connect();
    try {
      await client.query('BEGIN');
      const result = await work(client);
      await client.query('COMMIT');
      return result;
    } catch (error) {
      await client.query('ROLLBACK');
      if ((error as { code?: string }).code === '23505') {
        throw new ApplicationError(409, 'CONFLICT', 'Billing code or reference already exists');
      }
      if ((error as { code?: string }).code === '23503') {
        throw new ApplicationError(
          400,
          'BAD_REQUEST',
          'Referenced billing record or currency does not exist',
        );
      }
      throw error;
    } finally {
      client.release();
    }
  }
}

function planValues(id: string, dto: PlanDto): unknown[] {
  return [
    id,
    dto.code,
    dto.name,
    dto.description ?? null,
    dto.price,
    dto.currency,
    dto.billingInterval,
    dto.intervalCount,
    dto.roleSlug,
    dto.trialDays ?? null,
    dto.isActive,
    dto.stripeProductId ?? null,
    dto.stripePriceId ?? null,
    dto.metadata,
  ];
}

function validatePromotion(dto: PromotionDto): void {
  validateMetadata(dto.metadata);
  if (
    dto.discountPercent === undefined &&
    dto.discountAmount === undefined &&
    dto.trialDays === undefined
  ) {
    throw new ApplicationError(400, 'BAD_REQUEST', 'A discount or trial period is required');
  }
  if (dto.discountAmount !== undefined && !dto.currency) {
    throw new ApplicationError(400, 'BAD_REQUEST', 'Fixed discounts require a currency');
  }
  if (
    dto.discountPercent &&
    ExactDecimal.create(dto.discountPercent).compare(ExactDecimal.create('100')) > 0
  ) {
    throw new ApplicationError(400, 'BAD_REQUEST', 'Discount percent cannot exceed 100');
  }
}

function validateMetadata(metadata: Record<string, unknown>): void {
  for (const [key, value] of Object.entries(metadata)) {
    if (/(secret|token|password|credential|api.?key)/i.test(key)) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        'Secrets are not permitted in billing metadata',
      );
    }
    if (value !== null && !['string', 'boolean'].includes(typeof value)) {
      throw new ApplicationError(
        400,
        'BAD_REQUEST',
        'Billing metadata must be flat and non-secret',
      );
    }
  }
}

async function lock(
  client: PoolClient,
  table: string,
  id: string,
  label: string,
): Promise<Record<string, unknown>> {
  const allowed = new Set([
    'billing_plans',
    'billing_promotions',
    'users',
    'user_invoices',
    'user_payments',
  ]);
  if (!allowed.has(table)) throw new Error('Invalid lock table');
  const result = await client.query(`SELECT * FROM mymoneymap.${table} WHERE id=$1 FOR UPDATE`, [
    id,
  ]);
  if (!result.rows[0]) throw notFound(label);
  return result.rows[0] as Record<string, unknown>;
}

async function audit(
  client: PoolClient,
  actor: string,
  action: string,
  targetType: string,
  targetId: string,
  details: Record<string, unknown>,
): Promise<void> {
  await client.query(
    `INSERT INTO mymoneymap.privileged_audit_events
       (id,actor_user_id,action,target_type,target_id,details,created_at)
     VALUES ($1,$2,$3,$4,$5,$6,now())`,
    [randomUUID(), actor, action, targetType, targetId, details],
  );
}

function calculatePeriodEnd(start: Date, interval: string, count: number): Date | null {
  if (interval === 'lifetime') return null;
  const result = new Date(start);
  if (interval === 'weekly') result.setUTCDate(result.getUTCDate() + count * 7);
  if (interval === 'monthly') result.setUTCMonth(result.getUTCMonth() + count);
  if (interval === 'yearly') result.setUTCFullYear(result.getUTCFullYear() + count);
  return result;
}

function mapRow(row: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(row).map(([key, value]) => [
      key.replace(/_([a-z])/g, (_match, letter: string) => letter.toUpperCase()),
      value instanceof Date ? value.toISOString() : value,
    ]),
  );
}

function notFound(label: string): ApplicationError {
  return new ApplicationError(404, 'NOT_FOUND', `${label} was not found`);
}
