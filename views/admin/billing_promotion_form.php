<?php
$promotion = $promotion ?? [
    'id' => null,
    'code' => '',
    'name' => '',
    'description' => '',
    'discount_percent' => null,
    'discount_amount' => null,
    'currency' => '',
    'max_redemptions' => null,
    'redeem_by' => '',
    'trial_days' => null,
    'plan_code' => null,
    'stripe_coupon_id' => '',
    'stripe_promo_code_id' => '',
];
$plans = $plans ?? [];
$mode = $mode ?? 'create';
$action = $mode === 'edit' ? '/admin/billing/promotions/update' : '/admin/billing/promotions';
?>
<section class="mx-auto max-w-4xl space-y-6">
  <header>
    <a href="/admin/billing" class="inline-flex items-center gap-2 text-sm text-emerald-600 hover:text-emerald-500 dark:text-emerald-300">
      <i data-lucide="arrow-left" class="h-4 w-4"></i>
      <span><?= __('Back to billing') ?></span>
    </a>
    <h1 class="mt-4 text-3xl font-semibold text-slate-900 dark:text-white">
      <?= $mode === 'edit' ? __('Edit promotion') : __('Create promotion') ?>
    </h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
      <?= __('Configure coupons, discounts, and trial access for onboarding campaigns.') ?>
    </p>
  </header>

  <form action="<?= $action ?>" method="post" class="grid gap-4 rounded-3xl border border-slate-200/70 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-slate-800/70 dark:bg-slate-900/60">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <?php if (!empty($promotion['id'])): ?>
      <input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>">
    <?php endif; ?>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Name') ?></span>
        <input type="text" name="name" value="<?= htmlspecialchars((string)$promotion['name'], ENT_QUOTES) ?>" required class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Code') ?></span>
        <input type="text" name="code" value="<?= htmlspecialchars((string)$promotion['code'], ENT_QUOTES) ?>" required class="mt-1 w-full uppercase rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <label class="block text-sm">
      <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Description') ?></span>
      <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white"><?= htmlspecialchars((string)($promotion['description'] ?? ''), ENT_QUOTES) ?></textarea>
    </label>
    <div class="grid gap-4 sm:grid-cols-3">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Discount percent') ?></span>
        <input type="number" step="0.01" min="0" max="100" name="discount_percent" value="<?= $promotion['discount_percent'] !== null ? htmlspecialchars((string)$promotion['discount_percent'], ENT_QUOTES) : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Discount amount') ?></span>
        <input type="number" step="0.01" min="0" name="discount_amount" value="<?= $promotion['discount_amount'] !== null ? htmlspecialchars((string)$promotion['discount_amount'], ENT_QUOTES) : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Currency') ?></span>
        <input type="text" name="currency" maxlength="3" value="<?= htmlspecialchars((string)($promotion['currency'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full uppercase rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Trial days') ?></span>
        <input type="number" name="trial_days" min="0" value="<?= $promotion['trial_days'] !== null ? (int)$promotion['trial_days'] : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Max redemptions') ?></span>
        <input type="number" name="max_redemptions" min="0" value="<?= $promotion['max_redemptions'] !== null ? (int)$promotion['max_redemptions'] : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Redeem by') ?></span>
        <input type="datetime-local" name="redeem_by" value="<?= $promotion['redeem_by'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string)$promotion['redeem_by'])), ENT_QUOTES) : '' ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <label class="block text-sm">
      <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Applies to plan') ?></span>
      <select name="plan_id" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
        <option value=""><?= __('Any plan') ?></option>
        <?php foreach ($plans as $plan): ?>
          <option value="<?= (int)$plan['id'] ?>" <?= ($promotion['plan_code'] ?? null) === ($plan['code'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string)$plan['name']) ?> (<?= htmlspecialchars((string)$plan['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe coupon ID') ?></span>
        <input type="text" name="stripe_coupon_id" value="<?= htmlspecialchars((string)($promotion['stripe_coupon_id'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
      <label class="block text-sm">
        <span class="font-medium text-slate-700 dark:text-slate-200"><?= __('Stripe promo code ID') ?></span>
        <input type="text" name="stripe_promo_code_id" value="<?= htmlspecialchars((string)($promotion['stripe_promo_code_id'] ?? ''), ENT_QUOTES) ?>" class="mt-1 w-full rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white">
      </label>
    </div>
    <div class="flex justify-end gap-3">
      <a href="/admin/billing" class="btn btn-muted inline-flex items-center gap-2">
        <i data-lucide="x" class="h-4 w-4"></i>
        <span><?= __('Cancel') ?></span>
      </a>
      <button class="btn btn-primary inline-flex items-center gap-2">
        <i data-lucide="save" class="h-4 w-4"></i>
        <span><?= $mode === 'edit' ? __('Update promotion') : __('Create promotion') ?></span>
      </button>
    </div>
  </form>
</section>
