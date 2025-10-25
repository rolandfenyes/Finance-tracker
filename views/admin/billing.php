<?php
$plans = $plans ?? [];
$planOptions = $planOptions ?? [];
$promotions = $promotions ?? [];
$subscriptions = $subscriptions ?? [];
$invoices = $invoices ?? [];
$payments = $payments ?? [];
$roleOptions = $roleOptions ?? [];
$intervalLabels = $intervalLabels ?? [];
$invoiceStatusOptions = $invoiceStatusOptions ?? [];
$paymentStatusOptions = $paymentStatusOptions ?? [];
$paymentTypeOptions = $paymentTypeOptions ?? [];
$subscriptionStatusOptions = $subscriptionStatusOptions ?? [];
$stripeSettings = $stripeSettings ?? [];
$hasStripeKeys = !empty($hasStripeKeys);
$defaultCurrency = $defaultCurrency ?? 'USD';

$totalPlans = count($plans);
$totalPromotions = count($promotions);
$totalSubscriptions = count($subscriptions);
$failedPaymentsCount = 0;
foreach ($payments as $payment) {
    if (($payment['status'] ?? '') === 'failed') {
        $failedPaymentsCount++;
    }
}

$currentPath = htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/admin/billing', PHP_URL_PATH) ?? '/admin/billing', ENT_QUOTES);
?>
<section class="mx-auto max-w-7xl space-y-8">
  <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900 dark:text-white"><?= __('Billing & plans') ?></h1>
      <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
        <?= __('Manage pricing plans, promotions, and customer billing activity from a single workspace.') ?>
      </p>
    </div>
    <div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Plans') ?></div>
        <div class="mt-1 text-xl font-semibold text-slate-900 dark:text-white"><?= (int)$totalPlans ?></div>
      </div>
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Promotions') ?></div>
        <div class="mt-1 text-xl font-semibold text-slate-900 dark:text-white"><?= (int)$totalPromotions ?></div>
      </div>
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Recent subscriptions') ?></div>
        <div class="mt-1 text-xl font-semibold text-slate-900 dark:text-white"><?= (int)$totalSubscriptions ?></div>
      </div>
      <div class="rounded-2xl border border-slate-200/70 bg-white/70 p-3 text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Failed payments') ?></div>
        <div class="mt-1 text-xl font-semibold text-rose-600 dark:text-rose-300"><?= (int)$failedPaymentsCount ?></div>
      </div>
    </div>
  </header>

  <section class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Stripe settings') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Store your Stripe credentials to enable syncing and checkout links.') ?>
        </p>
      </div>
      <span class="inline-flex items-center gap-2 text-sm <?= $hasStripeKeys ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300' ?>">
        <i data-lucide="<?= $hasStripeKeys ? 'check-circle' : 'alert-circle' ?>" class="h-4 w-4"></i>
        <?= $hasStripeKeys ? __('Keys configured') : __('Keys missing') ?>
      </span>
    </div>
    <form action="/admin/billing/settings" method="post" class="mt-6 grid gap-4 lg:grid-cols-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= $currentPath ?>">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe secret key') ?></span>
        <input type="text" name="stripe_secret_key" value="<?= htmlspecialchars((string)($stripeSettings['stripe_secret_key'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe publishable key') ?></span>
        <input type="text" name="stripe_publishable_key" value="<?= htmlspecialchars((string)($stripeSettings['stripe_publishable_key'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Webhook signing secret') ?></span>
        <input type="text" name="stripe_webhook_secret" value="<?= htmlspecialchars((string)($stripeSettings['stripe_webhook_secret'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Default currency') ?></span>
        <input type="text" name="default_currency" maxlength="3" value="<?= htmlspecialchars((string)$defaultCurrency, ENT_QUOTES) ?>" class="mt-1 w-full uppercase rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <div class="lg:col-span-2 flex justify-end">
        <button class="btn btn-primary inline-flex items-center gap-2">
          <i data-lucide="save" class="h-4 w-4"></i>
          <span><?= __('Save settings') ?></span>
        </button>
      </div>
    </form>
  </section>

  <section class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Pricing plans') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Adjust pricing, intervals, and role access for each subscription plan.') ?>
        </p>
      </div>
      <a href="/admin/billing/plans/create" class="btn btn-primary inline-flex items-center gap-2">
        <i data-lucide="plus" class="h-4 w-4"></i>
        <span><?= __('Add plan') ?></span>
      </a>
    </div>
    <?php if (!$plans): ?>
      <div class="rounded-3xl border border-slate-200/70 bg-white/70 p-6 text-sm text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <?= __('No pricing plans have been configured yet.') ?>
      </div>
    <?php else: ?>
      <div class="grid gap-6 lg:grid-cols-2">
        <?php foreach ($plans as $plan): ?>
          <?php
            $interval = $plan['billing_interval'] ?? 'monthly';
            $intervalLabel = $intervalLabels[$interval] ?? ucfirst($interval);
            $isActive = !empty($plan['is_active']);
            $badgeClasses = $isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800/80 dark:text-slate-300';
          ?>
          <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string)$plan['name']) ?></h3>
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $badgeClasses ?>">
                    <?= $isActive ? __('Active') : __('Inactive') ?>
                  </span>
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  <?= __('Code') ?>:
                  <span class="font-mono text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)$plan['code']) ?></span>
                </p>
                <?php if (!empty($plan['description'])): ?>
                  <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    <?= nl2br(htmlspecialchars((string)$plan['description'])) ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="text-right text-sm text-slate-600 dark:text-slate-300">
                <div class="font-semibold text-slate-900 dark:text-white">
                  <?= number_format((float)$plan['price'], 2) ?> <?= htmlspecialchars((string)$plan['currency']) ?>
                </div>
                <div><?= __('Every :interval', ['interval' => (int)$plan['interval_count'] . ' ' . strtolower($intervalLabel)]) ?></div>
                <div><?= __('Role') ?>: <?= htmlspecialchars((string)($plan['role_name'] ?? $plan['role_slug'])) ?></div>
              </div>
            </div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Trial days') ?></dt>
                <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">
                  <?= $plan['trial_days'] !== null ? (int)$plan['trial_days'] : __('None') ?>
                </dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Active subscribers') ?></dt>
                <dd class="mt-0.5 font-medium text-slate-900 dark:text-white"><?= (int)$plan['active_subscriptions'] ?></dd>
              </div>
              <?php if (!empty($plan['stripe_product_id'])): ?>
                <div class="sm:col-span-2">
                  <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Stripe product ID') ?></dt>
                  <dd class="mt-0.5 font-medium text-slate-900 dark:text-white font-mono text-xs break-all"><?= htmlspecialchars((string)$plan['stripe_product_id']) ?></dd>
                </div>
              <?php endif; ?>
              <?php if (!empty($plan['stripe_price_id'])): ?>
                <div class="sm:col-span-2">
                  <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Stripe price ID') ?></dt>
                  <dd class="mt-0.5 font-medium text-slate-900 dark:text-white font-mono text-xs break-all"><?= htmlspecialchars((string)$plan['stripe_price_id']) ?></dd>
                </div>
              <?php endif; ?>
            </dl>
            <div class="mt-6 flex flex-wrap items-center gap-3">
              <a href="/admin/billing/plans/edit?id=<?= (int)$plan['id'] ?>" class="btn btn-muted inline-flex items-center gap-2">
                <i data-lucide="pencil" class="h-4 w-4"></i>
                <span><?= __('Edit plan') ?></span>
              </a>
              <form action="/admin/billing/plans/delete" method="post" class="inline-flex" onsubmit="return confirm('<?= __('Are you sure you want to delete this plan?') ?>');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                <input type="hidden" name="redirect" value="<?= $currentPath ?>">
                <button class="btn btn-danger inline-flex items-center gap-2">
                  <i data-lucide="trash-2" class="h-4 w-4"></i>
                  <span><?= __('Delete plan') ?></span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Promo codes & trials') ?></h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          <?= __('Create targeted discounts or free trials for marketing campaigns.') ?>
        </p>
      </div>
      <a href="/admin/billing/promotions/create" class="btn btn-primary inline-flex items-center gap-2">
        <i data-lucide="ticket" class="h-4 w-4"></i>
        <span><?= __('Add promotion') ?></span>
      </a>
    </div>

    <form action="/admin/billing/promotions/generate-trial" method="post" class="rounded-3xl border border-emerald-200/60 bg-emerald-50/80 p-4 text-sm shadow-sm backdrop-blur dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:flex-1">
          <label class="block text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">
            <?= __('Plan') ?>
          </label>
          <select name="plan_id" class="mt-1 w-full rounded-xl border border-emerald-200/70 bg-white/80 px-3 py-2 text-sm text-emerald-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100">
            <?php foreach ($plans as $plan): ?>
              <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars((string)$plan['name']) ?> (<?= htmlspecialchars((string)$plan['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200"><?= __('Trial days') ?></label>
          <input type="number" min="1" name="trial_days" class="mt-1 w-24 rounded-xl border border-emerald-200/70 bg-white/80 px-3 py-2 text-sm text-emerald-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100">
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200"><?= __('Max redemptions') ?></label>
          <input type="number" min="1" name="max_redemptions" class="mt-1 w-28 rounded-xl border border-emerald-200/70 bg-white/80 px-3 py-2 text-sm text-emerald-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100">
        </div>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="redirect" value="<?= $currentPath ?>">
        <button class="btn btn-emerald inline-flex items-center gap-2">
          <i data-lucide="sparkles" class="h-4 w-4"></i>
          <span><?= __('Generate trial link') ?></span>
        </button>
      </div>
    </form>

    <?php if (!$promotions): ?>
      <div class="rounded-3xl border border-slate-200/70 bg-white/70 p-6 text-sm text-slate-600 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60 dark:text-slate-300">
        <?= __('No promotions configured yet.') ?>
      </div>
    <?php else: ?>
      <div class="grid gap-6 lg:grid-cols-2">
        <?php foreach ($promotions as $promo): ?>
          <article class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars((string)$promo['name']) ?></h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 font-mono break-all"><?= htmlspecialchars((string)$promo['code']) ?></p>
                <?php if (!empty($promo['description'])): ?>
                  <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    <?= nl2br(htmlspecialchars((string)$promo['description'])) ?>
                  </p>
                <?php endif; ?>
              </div>
              <?php if (!empty($promo['redeem_by'])): ?>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                  <?= __('Expires :date', ['date' => date('Y-m-d', strtotime((string)$promo['redeem_by']))]) ?>
                </span>
              <?php endif; ?>
            </div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <?php if ($promo['discount_percent'] !== null): ?>
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Percent off') ?></dt>
                  <dd class="mt-0.5 font-medium text-slate-900 dark:text-white"><?= (float)$promo['discount_percent'] ?>%</dd>
                </div>
              <?php endif; ?>
              <?php if ($promo['discount_amount'] !== null): ?>
                <div>
                  <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Amount off') ?></dt>
                  <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">
                    <?= number_format((float)$promo['discount_amount'], 2) ?> <?= htmlspecialchars((string)($promo['currency'] ?? $defaultCurrency)) ?>
                  </dd>
                </div>
              <?php endif; ?>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Trial days') ?></dt>
                <dd class="mt-0.5 font-medium text-slate-900 dark:text-white"><?= $promo['trial_days'] !== null ? (int)$promo['trial_days'] : __('None') ?></dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Max redemptions') ?></dt>
                <dd class="mt-0.5 font-medium text-slate-900 dark:text-white"><?= $promo['max_redemptions'] !== null ? (int)$promo['max_redemptions'] : __('Unlimited') ?></dd>
              </div>
              <?php if (!empty($promo['plan_code'])): ?>
                <div class="sm:col-span-2">
                  <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= __('Applies to plan') ?></dt>
                  <dd class="mt-0.5 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string)($promo['plan_name'] ?? $promo['plan_code'])) ?></dd>
                </div>
              <?php endif; ?>
            </dl>
            <div class="mt-6 flex flex-wrap items-center gap-3">
              <a href="/admin/billing/promotions/edit?id=<?= (int)$promo['id'] ?>" class="btn btn-muted inline-flex items-center gap-2">
                <i data-lucide="pencil" class="h-4 w-4"></i>
                <span><?= __('Edit promotion') ?></span>
              </a>
              <form action="/admin/billing/promotions/delete" method="post" class="inline-flex" onsubmit="return confirm('<?= __('Are you sure you want to delete this promotion?') ?>');">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="promotion_id" value="<?= (int)$promo['id'] ?>">
                <input type="hidden" name="redirect" value="<?= $currentPath ?>">
                <button class="btn btn-danger inline-flex items-center gap-2">
                  <i data-lucide="trash" class="h-4 w-4"></i>
                  <span><?= __('Delete promotion') ?></span>
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Manual plan assignment') ?></h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Upgrade or downgrade a user and optionally create a matching subscription record.') ?>
    </p>
    <form action="/admin/billing/user-plan" method="post" class="mt-4 grid gap-4 sm:grid-cols-2">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="redirect" value="<?= $currentPath ?>">
      <label class="block text-sm sm:col-span-2">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('User email or ID') ?></span>
        <input type="text" name="user_email" required class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Plan') ?></span>
        <select name="plan_id" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
          <?php foreach ($plans as $plan): ?>
            <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars((string)$plan['name']) ?> (<?= htmlspecialchars((string)$plan['code']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Subscription status') ?></span>
        <select name="subscription_status" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
          <?php foreach ($subscriptionStatusOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>"><?= htmlspecialchars((string)$label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="create_subscription" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
        <span><?= __('Create subscription record') ?></span>
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Override trial days') ?></span>
        <input type="number" min="0" name="trial_days" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm sm:col-span-2">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Internal note') ?></span>
        <textarea name="note" rows="3" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white"></textarea>
      </label>
      <div class="sm:col-span-2 flex justify-end">
        <button class="btn btn-primary inline-flex items-center gap-2">
          <i data-lucide="user-check" class="h-4 w-4"></i>
          <span><?= __('Assign plan') ?></span>
        </button>
      </div>
    </form>
  </section>

  <section class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Recent subscriptions') ?></h2>
    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Monitor active billing cycles, trials, and cancellations.') ?>
    </p>
    <div class="mt-4 overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
          <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <th class="px-3 py-2"><?= __('User') ?></th>
            <th class="px-3 py-2"><?= __('Plan') ?></th>
            <th class="px-3 py-2"><?= __('Status') ?></th>
            <th class="px-3 py-2"><?= __('Amount') ?></th>
            <th class="px-3 py-2"><?= __('Current period') ?></th>
            <th class="px-3 py-2"><?= __('Trial ends') ?></th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <?php foreach ($subscriptions as $subscription): ?>
            <tr class="text-slate-700 dark:text-slate-200">
              <td class="px-3 py-2">
                <div class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string)($subscription['full_name'] ?? $subscription['email'] ?? '')) ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all"><?= htmlspecialchars((string)$subscription['email']) ?></div>
              </td>
              <td class="px-3 py-2">
                <div class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars((string)$subscription['plan_name']) ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all"><?= htmlspecialchars((string)$subscription['plan_code']) ?></div>
              </td>
              <td class="px-3 py-2">
                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800/70 dark:text-slate-200">
                  <?= htmlspecialchars((string)($subscriptionStatusOptions[$subscription['status']] ?? ucfirst((string)$subscription['status']))) ?>
                </span>
              </td>
              <td class="px-3 py-2">
                <?= number_format((float)$subscription['amount'], 2) ?> <?= htmlspecialchars((string)$subscription['currency']) ?>
              </td>
              <td class="px-3 py-2 text-xs">
                <?php if (!empty($subscription['current_period_start'])): ?>
                  <div><?= date('Y-m-d', strtotime((string)$subscription['current_period_start'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($subscription['current_period_end'])): ?>
                  <div class="text-slate-500 dark:text-slate-400">→ <?= date('Y-m-d', strtotime((string)$subscription['current_period_end'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 text-xs">
                <?= !empty($subscription['trial_ends_at']) ? date('Y-m-d', strtotime((string)$subscription['trial_ends_at'])) : '—' ?>
              </td>
              <td class="px-3 py-2 text-right">
                <a href="/admin/users/manage?id=<?= (int)$subscription['user_id'] ?>&return=<?= rawurlencode('/admin/billing') ?>" class="text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-300">
                  <?= __('Manage user') ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Recent invoices') ?></h2>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-400"><?= __('Handle failed invoices and refunds without leaving the console.') ?></p>
      <div class="mt-4 space-y-4">
        <?php foreach ($invoices as $invoice): ?>
          <article class="rounded-2xl border border-slate-200/70 bg-white/70 p-4 text-sm shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-900 dark:text-white">#<?= htmlspecialchars((string)$invoice['invoice_number']) ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all"><?= htmlspecialchars((string)$invoice['email']) ?></div>
              </div>
              <div class="text-right text-sm text-slate-600 dark:text-slate-300">
                <div class="font-semibold text-slate-900 dark:text-white">
                  <?= number_format((float)$invoice['total_amount'], 2) ?> <?= htmlspecialchars((string)$invoice['currency']) ?>
                </div>
                <div><?= __('Issued :date', ['date' => date('Y-m-d', strtotime((string)$invoice['issued_at']))]) ?></div>
              </div>
            </div>
            <form action="/admin/users/invoices/update" method="post" class="mt-3 flex flex-wrap items-center gap-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="user_id" value="<?= (int)$invoice['user_id'] ?>">
              <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
              <input type="hidden" name="redirect" value="<?= $currentPath ?>">
              <select name="status" class="rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
                <?php foreach ($invoiceStatusOptions as $key => $label): ?>
                  <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>" <?= $invoice['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="reason" placeholder="<?= __('Reason (optional)') ?>" class="w-36 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <input type="text" name="note" placeholder="<?= __('Note') ?>" class="flex-1 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <button class="btn btn-muted inline-flex items-center gap-2 text-xs">
                <i data-lucide="check" class="h-4 w-4"></i>
                <span><?= __('Update') ?></span>
              </button>
            </form>
            <a href="/admin/users/manage?id=<?= (int)$invoice['user_id'] ?>&return=<?= rawurlencode('/admin/billing') ?>" class="mt-3 inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-300">
              <i data-lucide="external-link" class="mr-1 h-3.5 w-3.5"></i>
              <?= __('Open user record') ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white"><?= __('Recent payments') ?></h2>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-400"><?= __('Resolve failed charges or issue refunds manually.') ?></p>
      <div class="mt-4 space-y-4">
        <?php foreach ($payments as $payment): ?>
          <article class="rounded-2xl border border-slate-200/70 bg-white/70 p-4 text-sm shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-900 dark:text-white">
                  <?= ucfirst((string)$payment['type']) ?> — <?= number_format((float)$payment['amount'], 2) ?> <?= htmlspecialchars((string)$payment['currency']) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all"><?= htmlspecialchars((string)$payment['email']) ?></div>
              </div>
              <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                <?= __('Processed :date', ['date' => date('Y-m-d', strtotime((string)$payment['processed_at'] ?? (string)$payment['created_at'] ?? 'now'))]) ?>
              </div>
            </div>
            <form action="/admin/users/payments/update" method="post" class="mt-3 flex flex-wrap items-center gap-2">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="user_id" value="<?= (int)$payment['user_id'] ?>">
              <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
              <input type="hidden" name="redirect" value="<?= $currentPath ?>">
              <select name="status" class="rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
                <?php foreach ($paymentStatusOptions as $key => $label): ?>
                  <option value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>" <?= $payment['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="gateway" value="<?= htmlspecialchars((string)($payment['gateway'] ?? ''), ENT_QUOTES) ?>" placeholder="<?= __('Gateway') ?>" class="w-28 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <input type="text" name="reference" value="<?= htmlspecialchars((string)($payment['transaction_reference'] ?? ''), ENT_QUOTES) ?>" placeholder="<?= __('Reference') ?>" class="flex-1 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <input type="text" name="failure_reason" value="<?= htmlspecialchars((string)($payment['failure_reason'] ?? ''), ENT_QUOTES) ?>" placeholder="<?= __('Failure reason') ?>" class="w-40 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <input type="text" name="note" value="<?= htmlspecialchars((string)($payment['notes'] ?? ''), ENT_QUOTES) ?>" placeholder="<?= __('Note') ?>" class="flex-1 rounded-xl border border-slate-200/70 bg-white/80 px-3 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
              <button class="btn btn-muted inline-flex items-center gap-2 text-xs">
                <i data-lucide="check" class="h-4 w-4"></i>
                <span><?= __('Update') ?></span>
              </button>
            </form>
            <a href="/admin/users/manage?id=<?= (int)$payment['user_id'] ?>&return=<?= rawurlencode('/admin/billing') ?>" class="mt-3 inline-flex items-center text-xs font-semibold text-emerald-600 hover:text-emerald-500 dark:text-emerald-300">
              <i data-lucide="external-link" class="mr-1 h-3.5 w-3.5"></i>
              <?= __('Open user record') ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</section>
