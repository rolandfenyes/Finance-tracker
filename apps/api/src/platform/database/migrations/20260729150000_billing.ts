import { sql, type Kysely } from 'kysely';
import { APPLICATION_SCHEMA } from '../database.constants';

export async function up(database: Kysely<unknown>): Promise<void> {
  await sql`
    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.billing_plans`)} (
      id uuid PRIMARY KEY,
      code varchar(80) NOT NULL UNIQUE,
      name varchar(160) NOT NULL,
      description varchar(2000),
      price numeric(36,12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code),
      billing_interval varchar(16) NOT NULL,
      interval_count integer NOT NULL,
      role_slug varchar(16) NOT NULL,
      trial_days integer,
      is_active boolean NOT NULL DEFAULT true,
      stripe_product_id varchar(255),
      stripe_price_id varchar(255),
      metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT billing_plans_code_check CHECK (code ~ '^[a-z0-9][a-z0-9_-]{0,79}$'),
      CONSTRAINT billing_plans_name_check CHECK (char_length(btrim(name)) BETWEEN 1 AND 160),
      CONSTRAINT billing_plans_price_check CHECK (price >= 0),
      CONSTRAINT billing_plans_interval_check CHECK (billing_interval IN ('weekly','monthly','yearly','lifetime')),
      CONSTRAINT billing_plans_interval_count_check CHECK (interval_count > 0),
      CONSTRAINT billing_plans_role_check CHECK (role_slug IN ('free','premium')),
      CONSTRAINT billing_plans_trial_check CHECK (trial_days IS NULL OR trial_days >= 0),
      CONSTRAINT billing_plans_metadata_check CHECK (jsonb_typeof(metadata) = 'object')
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.billing_promotions`)} (
      id uuid PRIMARY KEY,
      code varchar(80) NOT NULL UNIQUE,
      name varchar(160) NOT NULL,
      description varchar(2000),
      discount_percent numeric(5,2),
      discount_amount numeric(36,12),
      currency char(3) REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code),
      max_redemptions integer,
      redeem_by timestamptz,
      trial_days integer,
      plan_code varchar(80) REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.billing_plans`)} (code) ON UPDATE CASCADE ON DELETE SET NULL,
      stripe_coupon_id varchar(255),
      stripe_promo_code_id varchar(255),
      metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT billing_promotions_code_check CHECK (code ~ '^[A-Z0-9][A-Z0-9_-]{0,79}$'),
      CONSTRAINT billing_promotions_name_check CHECK (char_length(btrim(name)) BETWEEN 1 AND 160),
      CONSTRAINT billing_promotions_percent_check CHECK (discount_percent IS NULL OR discount_percent BETWEEN 0 AND 100),
      CONSTRAINT billing_promotions_amount_check CHECK (discount_amount IS NULL OR discount_amount >= 0),
      CONSTRAINT billing_promotions_currency_check CHECK ((discount_amount IS NULL) OR (currency IS NOT NULL)),
      CONSTRAINT billing_promotions_value_check CHECK (discount_percent IS NOT NULL OR discount_amount IS NOT NULL OR trial_days IS NOT NULL),
      CONSTRAINT billing_promotions_redemptions_check CHECK (max_redemptions IS NULL OR max_redemptions >= 0),
      CONSTRAINT billing_promotions_trial_check CHECK (trial_days IS NULL OR trial_days >= 0),
      CONSTRAINT billing_promotions_metadata_check CHECK (jsonb_typeof(metadata) = 'object')
    );

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.user_subscriptions`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      plan_code varchar(80) NOT NULL,
      plan_name varchar(160) NOT NULL,
      status varchar(16) NOT NULL,
      billing_interval varchar(16) NOT NULL,
      interval_count integer NOT NULL,
      amount numeric(36,12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code),
      started_at timestamptz NOT NULL,
      current_period_start timestamptz,
      current_period_end timestamptz,
      cancel_at timestamptz,
      canceled_at timestamptz,
      trial_ends_at timestamptz,
      notes varchar(4000),
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT user_subscriptions_status_check CHECK (status IN ('active','trialing','past_due','canceled','expired')),
      CONSTRAINT user_subscriptions_interval_check CHECK (billing_interval IN ('weekly','monthly','yearly','lifetime')),
      CONSTRAINT user_subscriptions_interval_count_check CHECK (interval_count > 0),
      CONSTRAINT user_subscriptions_amount_check CHECK (amount >= 0),
      CONSTRAINT user_subscriptions_period_check CHECK (current_period_end IS NULL OR current_period_start IS NULL OR current_period_end >= current_period_start),
      CONSTRAINT user_subscriptions_trial_check CHECK (trial_ends_at IS NULL OR trial_ends_at >= started_at),
      CONSTRAINT user_subscriptions_id_user_unique UNIQUE (id,user_id)
    );
    CREATE INDEX user_subscriptions_user_index ON ${sql.table(`${APPLICATION_SCHEMA}.user_subscriptions`)} (user_id,created_at DESC,id DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.user_invoices`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      subscription_id uuid,
      invoice_number varchar(120) NOT NULL UNIQUE,
      status varchar(16) NOT NULL,
      total_amount numeric(36,12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code),
      issued_at timestamptz NOT NULL,
      due_at timestamptz,
      paid_at timestamptz,
      failure_reason varchar(2000),
      refund_reason varchar(2000),
      notes varchar(4000),
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT user_invoices_status_check CHECK (status IN ('draft','open','paid','failed','past_due','refunded','void')),
      CONSTRAINT user_invoices_amount_check CHECK (total_amount >= 0),
      CONSTRAINT user_invoices_due_check CHECK (due_at IS NULL OR due_at >= issued_at),
      CONSTRAINT user_invoices_paid_check CHECK (paid_at IS NULL OR paid_at >= issued_at),
      CONSTRAINT user_invoices_id_user_unique UNIQUE (id,user_id),
      CONSTRAINT user_invoices_subscription_owner_fk FOREIGN KEY (subscription_id,user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_subscriptions`)} (id,user_id) ON DELETE SET NULL (subscription_id)
    );
    CREATE INDEX user_invoices_user_index ON ${sql.table(`${APPLICATION_SCHEMA}.user_invoices`)} (user_id,issued_at DESC,id DESC);

    CREATE TABLE ${sql.table(`${APPLICATION_SCHEMA}.user_payments`)} (
      id uuid PRIMARY KEY,
      user_id uuid NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.users`)} (id) ON DELETE CASCADE,
      invoice_id uuid,
      type varchar(16) NOT NULL,
      status varchar(16) NOT NULL,
      amount numeric(36,12) NOT NULL,
      currency char(3) NOT NULL REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.currencies`)} (code),
      gateway varchar(120),
      transaction_reference varchar(255),
      failure_reason varchar(2000),
      notes varchar(4000),
      processed_at timestamptz NOT NULL,
      created_at timestamptz NOT NULL,
      updated_at timestamptz NOT NULL,
      CONSTRAINT user_payments_type_check CHECK (type IN ('charge','refund','adjustment')),
      CONSTRAINT user_payments_status_check CHECK (status IN ('pending','succeeded','failed','canceled')),
      CONSTRAINT user_payments_amount_check CHECK (amount >= 0),
      CONSTRAINT user_payments_invoice_owner_fk FOREIGN KEY (invoice_id,user_id)
        REFERENCES ${sql.table(`${APPLICATION_SCHEMA}.user_invoices`)} (id,user_id) ON DELETE SET NULL (invoice_id)
    );
    CREATE INDEX user_payments_user_index ON ${sql.table(`${APPLICATION_SCHEMA}.user_payments`)} (user_id,processed_at DESC,id DESC);
    CREATE INDEX user_payments_invoice_index ON ${sql.table(`${APPLICATION_SCHEMA}.user_payments`)} (invoice_id,processed_at DESC,id DESC);

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      DROP CONSTRAINT privileged_audit_action_check,
      DROP CONSTRAINT privileged_audit_target_type_check,
      ADD CONSTRAINT privileged_audit_action_check CHECK (action IN (
        'feedback.updated','feedback.responded','system.settings_updated','integration.upserted',
        'integration.deleted','user.role_updated','user.status_updated','user.password_reset_requested',
        'user.email_verification_requested','user.email_change_requested','billing.plan_created',
        'billing.plan_updated','billing.plan_deleted','billing.promotion_created',
        'billing.promotion_updated','billing.promotion_deleted','billing.subscription_assigned',
        'billing.invoice_updated','billing.payment_created','billing.payment_updated'
      )),
      ADD CONSTRAINT privileged_audit_target_type_check CHECK (
        target_type IN ('feedback','system_settings','integration','user','billing_plan',
                        'billing_promotion','subscription','invoice','payment')
      );
  `.execute(database);
}

export async function down(database: Kysely<unknown>): Promise<void> {
  await sql`
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      DISABLE TRIGGER privileged_audit_events_immutable;
    DELETE FROM ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      WHERE action LIKE 'billing.%';
    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      ENABLE TRIGGER privileged_audit_events_immutable;

    ALTER TABLE ${sql.table(`${APPLICATION_SCHEMA}.privileged_audit_events`)}
      DROP CONSTRAINT privileged_audit_action_check,
      DROP CONSTRAINT privileged_audit_target_type_check,
      ADD CONSTRAINT privileged_audit_action_check CHECK (action IN (
        'feedback.updated','feedback.responded','system.settings_updated','integration.upserted',
        'integration.deleted','user.role_updated','user.status_updated','user.password_reset_requested',
        'user.email_verification_requested','user.email_change_requested'
      )),
      ADD CONSTRAINT privileged_audit_target_type_check CHECK (
        target_type IN ('feedback','system_settings','integration','user')
      );
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.user_payments`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.user_invoices`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.user_subscriptions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.billing_promotions`)};
    DROP TABLE IF EXISTS ${sql.table(`${APPLICATION_SCHEMA}.billing_plans`)};
  `.execute(database);
}
